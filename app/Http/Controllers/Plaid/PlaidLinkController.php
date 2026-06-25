<?php

namespace App\Http\Controllers\Plaid;

use App\Enums\PlaidConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plaid\ExchangePublicTokenRequest;
use App\Jobs\SyncPlaidConnection;
use App\Models\PlaidConnection;
use App\Services\Plaid\PlaidAccountSync;
use App\Services\Plaid\PlaidClient;
use Illuminate\Http\RedirectResponse;

class PlaidLinkController extends Controller
{
    /**
     * Exchange a public_token from a completed Plaid Link flow for an access
     * token, persist the connection and its accounts, and return to the
     * connections page. Accounts are synced synchronously so they appear
     * immediately, while the (potentially large) initial transaction import is
     * dispatched to the queue.
     */
    public function store(
        ExchangePublicTokenRequest $request,
        PlaidClient $plaid,
        PlaidAccountSync $accountSync,
    ): RedirectResponse {
        $exchange = $plaid->exchangePublicToken((string) $request->string('public_token'));

        $connection = PlaidConnection::create([
            'user_id' => $request->user()->id,
            'plaid_item_id' => $exchange['item_id'],
            'access_token' => $exchange['access_token'],
            'institution_id' => $request->input('institution.institution_id'),
            'institution_name' => $request->input('institution.name'),
            'status' => PlaidConnectionStatus::Active,
        ]);

        $accountSync->sync($connection);

        SyncPlaidConnection::dispatch($connection);

        $name = $connection->institution_name ?? 'your bank';

        return redirect()->route('connections.index')->with('status', "Linked {$name}.");
    }
}
