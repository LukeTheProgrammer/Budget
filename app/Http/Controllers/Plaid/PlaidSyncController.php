<?php

namespace App\Http\Controllers\Plaid;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPlaidConnection;
use App\Models\PlaidConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlaidSyncController extends Controller
{
    /**
     * Trigger an on-demand sync for one of the user's connections, importing new
     * transactions and refreshed balances since the last sync.
     */
    public function store(Request $request, PlaidConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        SyncPlaidConnection::dispatch($connection);

        $name = $connection->institution_name ?? 'your bank';

        return back()->with('status', "Sync started for {$name}.");
    }
}
