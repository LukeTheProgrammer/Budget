<?php

namespace App\Http\Controllers\Transactions;

use App\Enums\FlowType;
use App\Enums\FlowTypeSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\UpdateFlowTypeRequest;
use App\Models\Transaction;
use App\Services\Transactions\TransferPairer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TransactionFlowTypeController extends Controller
{
    /**
     * Reclassify a transaction. The new flow type is marked as user-set, so no
     * later import or sync will overwrite it, and — unless the user opts out —
     * it is also taught to the merchant so the next statement carrying the same
     * row arrives already classified correctly.
     */
    public function update(UpdateFlowTypeRequest $request, Transaction $transaction, TransferPairer $pairer): RedirectResponse
    {
        $flowType = $request->flowType();
        $wasTransfer = $transaction->flow_type === FlowType::Transfer;

        DB::transaction(function () use ($request, $transaction, $flowType): void {
            $transaction->update([
                'flow_type' => $flowType,
                'flow_type_source' => FlowTypeSource::User,
            ]);

            if ($request->appliesToMerchant() && $transaction->merchant !== null) {
                $transaction->merchant->update(['default_flow_type' => $flowType]);
            }
        });

        if ($wasTransfer && $flowType !== FlowType::Transfer) {
            // It is no longer half of an internal movement, so the link to the
            // other leg is meaningless — drop it from both sides.
            $pairer->unpair($transaction);
        }

        if ($flowType === FlowType::Transfer) {
            $pairer->pairForUser($transaction->account->user_id);
        }

        return back();
    }
}
