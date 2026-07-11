<?php

namespace App\Console\Commands;

use App\Enums\FlowType;
use App\Enums\FlowTypeSource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\FlowTypeClassifier;
use App\Services\Transactions\TransferPairer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transactions:classify-flow-types {--user= : Only classify this user\'s transactions}')]
#[Description('Assign a flow type (expense, income, transfer, refund) to transactions imported before flow types existed')]
class ClassifyTransactionFlowTypes extends Command
{
    /**
     * Reclassify every automatically classified transaction and re-pair
     * transfers. Transactions the user has classified by hand are never
     * touched. Safe to re-run — useful after the classifier's descriptor list
     * is tuned.
     */
    public function handle(FlowTypeClassifier $classifier, TransferPairer $pairer): int
    {
        $users = User::query()
            ->when($this->option('user'), fn ($query) => $query->whereKey($this->option('user')))
            ->get();

        if ($users->isEmpty()) {
            $this->components->warn('No matching users.');

            return self::SUCCESS;
        }

        /** @var array<string, int> $counts */
        $counts = array_fill_keys(array_column(FlowType::cases(), 'value'), 0);
        $pairs = 0;

        foreach ($users as $user) {
            // Start from an empty merchant-expense set and let it build up in
            // date order: the stored flow types are all still at the column
            // default and prove nothing yet.
            $classifier->forUser($user->id, primeFromExisting: false);

            Transaction::query()
                ->whereHas('account', fn ($account) => $account->where('user_id', $user->id))
                ->where('flow_type_source', FlowTypeSource::Auto)
                ->with(['account', 'merchant'])
                ->orderBy('posted_at')
                ->orderBy('id')
                ->chunkById(500, function ($transactions) use ($classifier, &$counts): void {
                    foreach ($transactions as $transaction) {
                        if ($transaction->account === null || $transaction->merchant === null) {
                            continue;
                        }

                        $flowType = $classifier->classify(
                            $transaction->account,
                            $transaction->merchant,
                            $transaction->amount_cents,
                            $transaction->description,
                        );

                        $transaction->update(['flow_type' => $flowType]);
                        $counts[$flowType->value]++;
                    }
                });

            $pairs += $pairer->pairForUser($user->id);
        }

        foreach ($counts as $value => $count) {
            $this->components->twoColumnDetail(FlowType::from($value)->label(), (string) $count);
        }

        $this->components->twoColumnDetail('Transfer pairs linked', (string) $pairs);

        return self::SUCCESS;
    }
}
