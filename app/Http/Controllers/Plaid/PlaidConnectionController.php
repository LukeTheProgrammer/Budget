<?php

namespace App\Http\Controllers\Plaid;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Services\Plaid\PlaidClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlaidConnectionController extends Controller
{
    /**
     * List the authenticated user's Plaid connections and their linked accounts.
     */
    public function index(Request $request): Response
    {
        $connections = PlaidConnection::query()
            ->where('user_id', $request->user()->id)
            ->with(['accounts' => fn ($query) => $query->orderBy('name')])
            ->orderBy('institution_name')
            ->get()
            ->map(fn (PlaidConnection $connection): array => [
                'id' => $connection->id,
                'institution_name' => $connection->institution_name,
                'status' => $connection->status->value,
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'accounts' => $connection->accounts->map(fn (Account $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'last_four' => $account->last_four,
                    'balance_cents' => $account->balance_cents,
                    'currency' => $account->currency,
                ])->values(),
            ])->values();

        return Inertia::render('settings-connections', [
            'connections' => $connections,
        ]);
    }

    /**
     * Disconnect a connection: revoke access at Plaid, detach its accounts (so
     * their transactions are retained), and delete the connection.
     */
    public function destroy(Request $request, PlaidConnection $connection, PlaidClient $plaid): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        try {
            $plaid->removeItem($connection->access_token);
        } catch (Throwable) {
            // Even if Plaid rejects the removal (e.g. the Item is already gone),
            // continue tearing down the local connection so it stops syncing.
        }

        $connection->accounts()->update(['plaid_connection_id' => null]);

        $name = $connection->institution_name ?? 'your bank';
        $connection->delete();

        return back()->with('status', "Disconnected {$name}.");
    }
}
