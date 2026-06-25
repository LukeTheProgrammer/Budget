<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MerchantGrouper
{
    /**
     * Merge one or more of a user's merchants into a single primary merchant.
     *
     * Every absorbed merchant's transactions are reassigned to the primary, its
     * raw name (and any existing aliases) are retained as aliases of the primary,
     * and the absorbed record is then deleted. The whole operation runs in a
     * single transaction so no transaction is ever orphaned.
     *
     * @param  list<int>  $merchantIds  The full set of merchant ids to group (must include the primary).
     * @param  string|null  $name  Optional clean name to rename the surviving primary to.
     *
     * @throws InvalidArgumentException when fewer than two distinct merchants are
     *                                  supplied, the primary is not part of the set,
     *                                  or any merchant does not belong to the user.
     */
    public function group(User $user, int $primaryId, array $merchantIds, ?string $name = null): Merchant
    {
        $ids = array_values(array_unique(array_merge($merchantIds, [$primaryId])));

        if (count($ids) < 2) {
            throw new InvalidArgumentException('At least two distinct merchants are required to group.');
        }

        return DB::transaction(function () use ($user, $primaryId, $ids, $name): Merchant {
            /** @var Collection<int, Merchant> $merchants */
            $merchants = Merchant::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $ids)
                ->with('aliases')
                ->lockForUpdate()
                ->get();

            if ($merchants->count() !== count($ids)) {
                throw new InvalidArgumentException('One or more merchants do not belong to the user.');
            }

            $primary = $merchants->firstWhere('id', $primaryId);

            if (! $primary instanceof Merchant) {
                throw new InvalidArgumentException('The primary merchant must be part of the group.');
            }

            // Names already represented on the primary. Its own name is held as a
            // self-alias, so the alias collection is the complete picture.
            $seen = $primary->aliases->pluck('normalized_name')->all();

            foreach ($merchants as $merchant) {
                if ($merchant->id === $primary->id) {
                    continue;
                }

                Transaction::where('merchant_id', $merchant->id)
                    ->update(['merchant_id' => $primary->id]);

                // Retain the absorbed merchant's raw name as an alias of the primary.
                $this->retainAlias($user->id, $primary->id, $merchant->name, $seen);

                // Carry over the absorbed merchant's existing aliases.
                foreach ($merchant->aliases as $alias) {
                    $this->retainAlias($user->id, $primary->id, $alias->name, $seen);
                }

                $merchant->delete();
            }

            if ($name !== null && trim($name) !== '') {
                $primary->update(['name' => $name]);
            }

            return $primary->refresh();
        });
    }

    /**
     * Create an alias on the primary merchant unless its normalized form is
     * already represented. Tracks seen normalized names to avoid duplicates.
     *
     * @param  list<string>  $seen
     */
    private function retainAlias(int $userId, int $primaryId, string $name, array &$seen): void
    {
        $normalized = mb_strtolower(trim($name));

        if (in_array($normalized, $seen, true)) {
            return;
        }

        MerchantAlias::create([
            'user_id' => $userId,
            'merchant_id' => $primaryId,
            'name' => $name,
        ]);

        $seen[] = $normalized;
    }
}
