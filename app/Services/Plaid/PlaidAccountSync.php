<?php

namespace App\Services\Plaid;

use App\Models\Account;
use App\Models\PlaidConnection;

/**
 * Fetches the accounts exposed by a Plaid connection and upserts them into the
 * shared `accounts` table, keyed on the connection + Plaid account id.
 */
class PlaidAccountSync
{
    public function __construct(private PlaidClient $plaid) {}

    /**
     * Sync all accounts (and current balances) for the given connection.
     */
    public function sync(PlaidConnection $connection): void
    {
        foreach ($this->plaid->getAccounts($connection->access_token) as $account) {
            Account::updateOrCreate(
                [
                    'plaid_connection_id' => $connection->id,
                    'plaid_account_id' => $account['account_id'],
                ],
                [
                    'user_id' => $connection->user_id,
                    'name' => $account['name'] ?? 'Account',
                    'type' => $account['type'] ?? null,
                    'last_four' => $account['mask'] ?? null,
                    'currency' => $account['balances']['iso_currency_code'] ?? 'USD',
                    'balance_cents' => $this->toCents($account['balances']['current'] ?? null),
                ],
            );
        }
    }

    /**
     * Convert a Plaid decimal balance to integer minor units.
     */
    private function toCents(int|float|null $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        return (int) round($amount * 100);
    }
}
