<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * Determine whether the user can view the account.
     */
    public function view(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the account. Ownership is required;
     * linked accounts are still updatable (name only — enforced by the form
     * request), so linkage is not checked here.
     */
    public function update(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the account. Only manual accounts
     * may be deleted here; linked accounts are removed via the Connections
     * disconnect flow.
     */
    public function delete(User $user, Account $account): bool
    {
        return $account->user_id === $user->id && ! $account->isLinked();
    }
}
