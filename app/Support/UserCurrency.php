<?php

namespace App\Support;

use App\Models\Account;

class UserCurrency
{
    /**
     * Resolve the single currency to display for a user, assuming one currency
     * per user for this iteration. Falls back to USD when the user has no
     * accounts.
     */
    public static function for(int $userId): string
    {
        return Account::query()
            ->where('user_id', $userId)
            ->value('currency') ?? 'USD';
    }
}
