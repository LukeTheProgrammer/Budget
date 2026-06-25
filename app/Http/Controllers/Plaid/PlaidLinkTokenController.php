<?php

namespace App\Http\Controllers\Plaid;

use App\Http\Controllers\Controller;
use App\Models\PlaidConnection;
use App\Services\Plaid\PlaidClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaidLinkTokenController extends Controller
{
    /**
     * Create a Plaid link_token used to initialize Plaid Link on the client.
     *
     * When a `connection` id is provided (and owned by the user), an
     * update-mode token is produced for re-authenticating that connection.
     */
    public function store(Request $request, PlaidClient $plaid): JsonResponse
    {
        $connection = null;
        $connectionId = $request->integer('connection');

        if ($connectionId !== 0) {
            $connection = PlaidConnection::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($connectionId);
        }

        $linkToken = $plaid->createLinkToken($request->user(), $connection);

        return response()->json(['link_token' => $linkToken]);
    }
}
