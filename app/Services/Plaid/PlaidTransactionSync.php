<?php

namespace App\Services\Plaid;

use App\Enums\FlowTypeSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Services\Merchants\NameResolver;
use App\Services\Transactions\FlowTypeClassifier;
use App\Services\Transactions\TransferPairer;
use Illuminate\Support\Facades\DB;

/**
 * Pulls incremental transaction changes for a Plaid connection via
 * `/transactions/sync` and upserts them into the shared `transactions` table,
 * reusing the existing `import_hash` + `updateOrCreate` mechanism and the
 * merchant/category resolution used by the CSV importer.
 */
class PlaidTransactionSync
{
    public function __construct(
        private PlaidClient $plaid,
        private NameResolver $nameResolver,
        private FlowTypeClassifier $flowTypeClassifier,
        private TransferPairer $transferPairer,
    ) {}

    /**
     * Sync all available transaction changes for the connection, persisting the
     * cursor so subsequent syncs only fetch new changes.
     */
    public function sync(PlaidConnection $connection): void
    {
        $this->nameResolver->forUser($connection->user_id);
        $this->flowTypeClassifier->forUser($connection->user_id);

        /** @var array<string, Account> $accounts plaid_account_id => Account */
        $accounts = $connection->accounts()
            ->whereNotNull('plaid_account_id')
            ->get()
            ->keyBy('plaid_account_id')
            ->all();

        $cursor = $connection->transactions_cursor;

        do {
            $batch = $this->plaid->syncTransactions($connection->access_token, $cursor);

            foreach ([...$batch['added'], ...$batch['modified']] as $plaidTransaction) {
                $account = $accounts[$plaidTransaction['account_id']] ?? null;

                if ($account !== null) {
                    $this->upsert($account, $plaidTransaction);
                }
            }

            foreach ($batch['removed'] as $removed) {
                Transaction::query()
                    ->where('import_hash', $this->hash($removed['transaction_id']))
                    ->delete();
            }

            $cursor = $batch['next_cursor'];
            $connection->update(['transactions_cursor' => $cursor]);
        } while ($batch['has_more']);

        // Pair transfers once the whole batch has landed: the counterpart leg
        // may arrive in the same sync, so per-row pairing would miss it.
        $this->transferPairer->pairForUser($connection->user_id);
    }

    /**
     * Upsert a single Plaid transaction onto its account, keyed on import_hash.
     *
     * @param  array<string, mixed>  $plaidTransaction
     */
    private function upsert(Account $account, array $plaidTransaction): void
    {
        $rawName = $plaidTransaction['merchant_name']
            ?? $plaidTransaction['name']
            ?? 'Unknown';

        $categoryName = $plaidTransaction['personal_finance_category']['primary'] ?? null;
        $merchant = $this->resolveMerchant($account->user_id, $rawName, $categoryName);

        $importHash = $this->hash($plaidTransaction['transaction_id']);

        // Plaid uses positive amounts for outflows, matching the app's
        // "positive = spend" convention; refunds/credits stay negative.
        $amountCents = (int) round(((float) $plaidTransaction['amount']) * 100);
        $description = $plaidTransaction['name'] ?? $rawName;

        $props = [
            'account_id' => $account->id,
            'merchant_id' => $merchant->id,
            'amount_cents' => $amountCents,
            'currency' => $plaidTransaction['iso_currency_code'] ?? $account->currency,
            'description' => $description,
            'posted_at' => $plaidTransaction['authorized_date'] ?? $plaidTransaction['date'],
        ];

        $existing = Transaction::query()
            ->where('import_hash', $importHash)
            ->first();

        // A flow type the user set by hand survives every re-sync; only
        // automatic classifications are recomputed.
        if ($existing?->flow_type_source !== FlowTypeSource::User) {
            $props['flow_type'] = $this->flowTypeClassifier->classify($account, $merchant, $amountCents, $description);
            $props['flow_type_source'] = FlowTypeSource::Auto;
        }

        Transaction::updateOrCreate(['import_hash' => $importHash], $props);
    }

    /**
     * A stable de-duplication hash derived from Plaid's transaction id, which is
     * stable across pending-to-posted changes.
     */
    private function hash(string $transactionId): string
    {
        return hash('sha256', 'plaid|' . $transactionId);
    }

    /**
     * Resolve the merchant for a raw descriptor, reusing the DB-backed
     * NameResolver (aliases + rules) and falling back to an alias lookup, then
     * auto-creating an unconfirmed merchant with a self-alias.
     */
    private function resolveMerchant(int $userId, string $rawName, ?string $categoryName): Merchant
    {
        $merchant = $this->nameResolver->resolve($rawName);

        if ($merchant !== null) {
            return $this->backfillCategory($merchant, $categoryName);
        }

        $alias = MerchantAlias::query()
            ->where('user_id', $userId)
            ->where('normalized_name', mb_strtolower(trim($rawName)))
            ->first();

        if ($alias?->merchant !== null) {
            return $this->backfillCategory($alias->merchant, $categoryName);
        }

        return DB::transaction(function () use ($userId, $rawName, $categoryName): Merchant {
            $merchant = Merchant::create([
                'user_id' => $userId,
                'category_id' => $this->resolveCategory($userId, $categoryName)?->id,
                'name' => $rawName,
            ]);

            $merchant->aliases()->create(['user_id' => $userId, 'name' => $rawName]);

            return $merchant;
        });
    }

    /**
     * Assign a category to a merchant that has none yet, leaving manual
     * assignments untouched.
     */
    private function backfillCategory(Merchant $merchant, ?string $categoryName): Merchant
    {
        if ($merchant->category_id !== null || $categoryName === null) {
            return $merchant;
        }

        $category = $this->resolveCategory($merchant->user_id, $categoryName);

        if ($category !== null) {
            $merchant->update(['category_id' => $category->id]);
        }

        return $merchant;
    }

    /**
     * Find or create the user's category for the given name (case-insensitive).
     */
    private function resolveCategory(int $userId, ?string $categoryName): ?Category
    {
        if ($categoryName === null) {
            return null;
        }

        return Category::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
            ->first()
            ?? Category::create(['user_id' => $userId, 'name' => $categoryName]);
    }
}
