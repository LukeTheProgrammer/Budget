<?php

namespace App\Services\Budgets;

use App\Enums\FlowType;
use App\Models\Category;
use App\Models\Transaction;

/**
 * Derives each category's recurring monthly budget from the user's spending
 * history: the monthly average spend on that category, rounded up to the next
 * whole ten currency units.
 */
class BudgetRecalculator
{
    /**
     * Recalculate the monthly budget of every category belonging to the user.
     * Only spending flow types count, and the average is taken over the number
     * of distinct months that have spending on the category.
     */
    public function recalculateForUser(int $userId): void
    {
        $totals = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->join('merchants', 'merchants.id', '=', 'transactions.merchant_id')
            ->where('accounts.user_id', $userId)
            ->whereIn('transactions.flow_type', FlowType::spendingValues())
            ->whereNotNull('merchants.category_id')
            ->groupBy('merchants.category_id')
            ->select('merchants.category_id')
            ->selectRaw('SUM(transactions.amount_cents) as total_cents')
            ->selectRaw("COUNT(DISTINCT DATE_FORMAT(transactions.posted_at, '%Y-%m')) as month_count")
            ->get()
            ->keyBy('category_id');

        Category::query()
            ->where('user_id', $userId)
            ->each(function (Category $category) use ($totals): void {
                $row = $totals->get($category->id);

                $totalCents = (int) ($row->total_cents ?? 0);
                $monthCount = (int) ($row->month_count ?? 0);

                $averageCents = $monthCount > 0 ? intdiv($totalCents, $monthCount) : 0;

                $category->update([
                    'monthly_budget_cents' => (int) (ceil($averageCents / 1000) * 1000),
                ]);
            });
    }
}
