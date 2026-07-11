<?php

namespace App\Services\Transactions;

use App\Enums\FlowType;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Links the two legs of a transfer between a user's own accounts — the debit in
 * checking and the credit in savings are one movement, not two.
 *
 * Runs once at the end of an import batch rather than per row: the counterpart
 * leg may live in a file that has not been imported yet, so pairing has to look
 * at the whole picture. Re-running is safe and never double-links.
 *
 * Pairing is a display and integrity nicety, not a correctness requirement — an
 * unpaired transfer is already excluded from spending and income by its flow
 * type.
 */
class TransferPairer
{
    /**
     * The number of days apart the two legs of one transfer may post. A
     * transfer initiated on a Friday commonly credits the other account on the
     * following Monday.
     */
    private const WINDOW_DAYS = 3;

    /**
     * Pair every unpaired transfer belonging to the user. Returns the number of
     * pairs created.
     */
    public function pairForUser(int $userId): int
    {
        $unpaired = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('accounts.user_id', $userId)
            ->where('transactions.flow_type', FlowType::Transfer)
            ->whereNull('transactions.transfer_pair_id')
            ->orderBy('transactions.posted_at')
            ->orderBy('transactions.id')
            ->select('transactions.*')
            ->get();

        /** @var array<int, true> $claimed */
        $claimed = [];
        $pairs = 0;

        foreach ($unpaired as $transaction) {
            if (isset($claimed[$transaction->id])) {
                continue;
            }

            $partner = $this->findPartner($transaction, $unpaired, $claimed);

            if ($partner === null) {
                continue;
            }

            $claimed[$transaction->id] = true;
            $claimed[$partner->id] = true;

            DB::transaction(function () use ($transaction, $partner): void {
                $transaction->update(['transfer_pair_id' => $partner->id]);
                $partner->update(['transfer_pair_id' => $transaction->id]);
            });

            $pairs++;
        }

        return $pairs;
    }

    /**
     * Break the link between a transaction and its counterpart, clearing both
     * sides. A no-op when the transaction is not paired.
     */
    public function unpair(Transaction $transaction): void
    {
        if ($transaction->transfer_pair_id === null) {
            return;
        }

        $partnerId = $transaction->transfer_pair_id;

        DB::transaction(function () use ($transaction, $partnerId): void {
            $transaction->update(['transfer_pair_id' => null]);

            Transaction::query()
                ->whereKey($partnerId)
                ->update(['transfer_pair_id' => null]);
        });
    }

    /**
     * The best counterpart for a transfer among the remaining unclaimed ones:
     * equal magnitude, opposite direction, same currency, a different account,
     * and posted within the window. The closest-dated candidate wins, ties
     * broken by the lower id so the result is deterministic.
     *
     * @param  Collection<int, Transaction>  $candidates
     * @param  array<int, true>  $claimed
     */
    private function findPartner(Transaction $transaction, Collection $candidates, array $claimed): ?Transaction
    {
        return $candidates
            ->reject(fn (Transaction $candidate): bool => isset($claimed[$candidate->id]) || $candidate->id === $transaction->id)
            ->filter(fn (Transaction $candidate): bool => $candidate->account_id !== $transaction->account_id
                && $candidate->currency === $transaction->currency
                && $candidate->amount_cents === -$transaction->amount_cents
                && abs($candidate->posted_at->diffInDays($transaction->posted_at)) <= self::WINDOW_DAYS)
            ->sortBy([
                fn (Transaction $candidate): int => (int) abs($candidate->posted_at->diffInDays($transaction->posted_at)),
                fn (Transaction $candidate): int => $candidate->id,
            ])
            ->first();
    }
}
