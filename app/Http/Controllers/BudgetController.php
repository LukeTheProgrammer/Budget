<?php

namespace App\Http\Controllers;

use App\Enums\FlowType;
use App\Http\Requests\Budgets\UpdateBudgetsRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\SessionPeriod;
use App\Support\UserCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Render the budgets dashboard: every category with its recurring monthly
     * budget and actual spend over the active period, plus the pacing context
     * (days elapsed / remaining) the detail panel uses to project month-end.
     */
    public function index(Request $request): Response
    {
        $period = SessionPeriod::fromSession($request->session()->get('session_period'));
        [$start, $end] = $period->window();
        $months = $period->months();
        $userId = $request->user()->id;

        $categories = Category::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'monthly_budget_cents']);

        $spending = Transaction::query()
            ->spendingByCategory($userId, $start, $end)
            ->get()
            ->keyBy('category_id');

        $recentByCategory = $this->recentTransactionsByCategory($userId, $start, $end);
        $trendByCategory = $this->monthlyTrendByCategory($userId);

        return Inertia::render('budgets', [
            'currency' => UserCurrency::for($userId),
            'months' => $months,
            'pacing' => $this->pacing($start, $end),
            'categories' => $categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'monthly_budget_cents' => $category->monthly_budget_cents,
                    'actual_cents' => (int) ($spending->get($category->id)->total_cents ?? 0),
                    'recent_transactions' => $recentByCategory->get($category->id, collect())
                        ->map(fn (object $row): array => [
                            'id' => (int) $row->id,
                            'description' => $row->description,
                            'merchant_id' => (int) $row->merchant_id,
                            'merchant_name' => $row->merchant_name,
                            'amount_cents' => (int) $row->amount_cents,
                            'posted_at' => Carbon::parse($row->posted_at)->toDateString(),
                        ])
                        ->values()
                        ->all(),
                    'monthly_trend' => $trendByCategory->get($category->id, []),
                ])
                ->all(),
        ]);
    }

    /**
     * Build the trailing 12-month spending trend for every category, in
     * chronological order with months that have no spending zero-filled so each
     * timeline is continuous. Independent of the selected period.
     *
     * @return Collection<int, list<array{month: string, label: string, total_cents: int}>>
     */
    private function monthlyTrendByCategory(int $userId): Collection
    {
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonthsNoOverflow(11)->startOfMonth();

        $totals = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->join('merchants', 'merchants.id', '=', 'transactions.merchant_id')
            ->where('accounts.user_id', $userId)
            ->whereIn('transactions.flow_type', FlowType::spendingValues())
            ->whereNotNull('merchants.category_id')
            ->whereBetween('transactions.posted_at', [$start, $end])
            ->groupBy('merchants.category_id', 'month')
            ->select([
                'merchants.category_id as category_id',
                DB::raw("DATE_FORMAT(transactions.posted_at, '%Y-%m') as month"),
                DB::raw('SUM(transactions.amount_cents) as total_cents'),
            ])
            ->get()
            ->groupBy('category_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('month'));

        return $totals->map(function (Collection $byMonth): array {
            $months = [];
            $cursor = Carbon::now()->subMonthsNoOverflow(11)->startOfMonth();
            $end = Carbon::now()->endOfMonth();

            while ($cursor->lessThanOrEqualTo($end)) {
                $key = $cursor->format('Y-m');

                $months[] = [
                    'month' => $key,
                    'label' => $cursor->format('M'),
                    'total_cents' => (int) ($byMonth->get($key)->total_cents ?? 0),
                ];

                $cursor->addMonthNoOverflow();
            }

            return $months;
        });
    }

    /**
     * The 10 most recent spending transactions for each of the user's
     * categories within the active period, grouped by category id. Uses a
     * window function so every category is filled in a single query rather
     * than one query per category.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function recentTransactionsByCategory(int $userId, Carbon $start, Carbon $end): Collection
    {
        $ranked = DB::query()
            ->fromSub(function ($query) use ($userId, $start, $end): void {
                $query
                    ->from('transactions')
                    ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                    ->join('merchants', 'merchants.id', '=', 'transactions.merchant_id')
                    ->whereNull('transactions.deleted_at')
                    ->where('accounts.user_id', $userId)
                    ->whereIn('transactions.flow_type', FlowType::spendingValues())
                    ->whereBetween('transactions.posted_at', [$start, $end])
                    ->whereNotNull('merchants.category_id')
                    ->select([
                        'merchants.category_id',
                        'transactions.id',
                        'transactions.description',
                        'transactions.amount_cents',
                        'transactions.posted_at',
                        'merchants.id as merchant_id',
                        'merchants.name as merchant_name',
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY merchants.category_id ORDER BY transactions.posted_at DESC, transactions.id DESC) as rn'),
                    ]);
            }, 'ranked')
            ->where('rn', '<=', 30)
            ->get();

        return $ranked->groupBy('category_id');
    }

    /**
     * Persist the recurring monthly budgets, scoped to the user's own
     * categories. A null amount clears the budget for that category.
     */
    public function update(UpdateBudgetsRequest $request): RedirectResponse
    {
        $userId = $request->user()->id;

        foreach ($request->validated('budgets') as $row) {
            Category::query()
                ->where('user_id', $userId)
                ->whereKey($row['category_id'])
                ->update(['monthly_budget_cents' => $row['amount_cents'] ?? null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budgets saved.')]);

        return back();
    }

    /**
     * Build the pacing context for the active period: how many days it spans,
     * how many have already elapsed (clamped to the window), and how many
     * remain. The detail panel uses this to place the "today's pace" marker
     * and to project month-end spend.
     *
     * @return array{days_in_period: int, days_elapsed: int, days_left: int}
     */
    private function pacing(Carbon $start, Carbon $end): array
    {
        $now = Carbon::now();

        $daysInPeriod = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        $elapsedTo = $now->lessThan($start)
            ? $start
            : ($now->greaterThan($end) ? $end : $now);

        $daysElapsed = (int) $start->copy()->startOfDay()->diffInDays($elapsedTo->copy()->startOfDay()) + 1;
        $daysElapsed = max(1, min($daysElapsed, $daysInPeriod));

        return [
            'days_in_period' => $daysInPeriod,
            'days_elapsed' => $daysElapsed,
            'days_left' => max(0, $daysInPeriod - $daysElapsed),
        ];
    }
}
