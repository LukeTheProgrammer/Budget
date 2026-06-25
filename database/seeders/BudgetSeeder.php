<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Set each category's budget to the monthly average spend on that category,
     * derived from the transactions, merchants, and categories already in the
     * database. Only outflows (positive amounts) count as spending, and the
     * average is taken over the number of distinct months that have spending.
     */
    public function run(): void
    {
        Category::query()->each(function (Category $category): void {
            $row = Transaction::query()
                ->join('merchants', 'merchants.id', '=', 'transactions.merchant_id')
                ->where('merchants.category_id', $category->id)
                ->where('transactions.amount_cents', '>', 0)
                ->selectRaw('SUM(transactions.amount_cents) as total_cents')
                ->selectRaw("COUNT(DISTINCT DATE_FORMAT(transactions.posted_at, '%Y-%m')) as month_count")
                ->first();

            $totalCents = (int) ($row->total_cents ?? 0);
            $monthCount = (int) ($row->month_count ?? 0);

            $averageCents = $monthCount > 0 ? intdiv($totalCents, $monthCount) : 0;

            $category->update([
                'monthly_budget_cents' => (int) (ceil($averageCents / 1000) * 1000),
            ]);
        });
    }
}
