<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountStoreRequest;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Show the user's accounts and the options needed to manage them.
     */
    public function index(Request $request): Response
    {
        $accounts = $request->user()->accounts()
            ->with('plaidConnection')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type?->value,
                'last_four' => $account->last_four,
                'currency' => $account->currency,
                'balance_cents' => $account->balance_cents,
                'is_linked' => $account->isLinked(),
                'institution_name' => $account->plaidConnection?->institution_name,
            ])
            ->values();

        return Inertia::render('settings-accounts', [
            'accounts' => $accounts,
            'accountTypes' => AccountType::options(),
        ]);
    }

    /**
     * Create a new manual account for the authenticated user.
     */
    public function store(AccountStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->accounts()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'last_four' => $data['last_four'] ?? null,
            'balance_cents' => $this->toCents($data['balance'] ?? null),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('accounts.index');
    }

    /**
     * Update an account the user owns. Linked accounts only accept a new name.
     */
    public function update(AccountUpdateRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();

        $account->name = $data['name'];

        if (! $account->isLinked()) {
            $account->type = $data['type'] ?? null;
            $account->currency = $data['currency'] ?? 'USD';
            $account->last_four = $data['last_four'] ?? null;
            $account->balance_cents = $this->toCents($data['balance'] ?? null);
        }

        $account->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('accounts.index');
    }

    /**
     * Soft-delete a manual account and hide its transactions along with it.
     */
    public function destroy(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $account), 403);

        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('accounts.index');
    }

    /**
     * Convert a decimal balance in major currency units to integer cents.
     */
    private function toCents(int|float|string|null $balance): ?int
    {
        if ($balance === null || $balance === '') {
            return null;
        }

        return (int) round((float) $balance * 100);
    }
}
