<?php

namespace Database\Seeders;

use App\Enums\MerchantRuleType;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\MerchantRule;
use App\Models\User;
use App\Services\Merchants\DefaultMerchantDefinitions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the database with the default resolution knowledge: each pattern becomes
 * a regex MerchantRule and each enumerated group becomes a set of exact
 * MerchantAliases, both attached to a confirmed merchant. After this runs the
 * database — not PHP source — is the single source of truth the resolver reads.
 */
class MerchantRuleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::query()->firstOrFail();
        $definitions = new DefaultMerchantDefinitions;

        foreach ($definitions->patterns as $name => $pattern) {
            $merchant = $this->merchant($user->id, $name);

            MerchantRule::firstOrCreate([
                'user_id' => $user->id,
                'merchant_id' => $merchant->id,
                'match_type' => MerchantRuleType::Regex,
                'pattern' => $pattern,
            ]);
        }

        foreach ($definitions->groups as $name => $rawNames) {
            $merchant = $this->merchant($user->id, $name);

            foreach ($rawNames as $rawName) {
                $this->alias($user->id, $merchant->id, $rawName);
            }
        }
    }

    /**
     * Find or create a confirmed merchant by name, ensuring it carries a
     * self-alias so its clean name resolves exactly.
     */
    private function merchant(int $userId, string $name): Merchant
    {
        $merchant = Merchant::firstOrCreate(
            ['user_id' => $userId, 'name' => $name],
            ['confirmed_at' => now()],
        );

        $this->alias($userId, $merchant->id, $name);

        return $merchant;
    }

    /**
     * Create an alias keyed by its normalized form, de-duplicating per user.
     */
    private function alias(int $userId, int $merchantId, string $name): void
    {
        MerchantAlias::firstOrCreate(
            ['user_id' => $userId, 'normalized_name' => mb_strtolower(trim($name))],
            ['merchant_id' => $merchantId, 'name' => $name],
        );
    }
}
