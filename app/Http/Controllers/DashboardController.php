<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\SessionPeriod;
use App\Support\UserCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Render the spending-insights dashboard for the authenticated user, scoped
     * to the session-wide reporting period shared across the app.
     */
    public function index(Request $request): Response
    {
        $period = SessionPeriod::fromSession($request->session()->get('session_period'));
        [$start, $end] = $period->window();
        [$previousStart, $previousEnd] = $this->previousWindow($start, $end);

        $userId = $request->user()->id;

        $summary = $this->summary($userId, $start, $end, $previousStart, $previousEnd);

        return Inertia::render('dashboard', [
            'currency' => UserCurrency::for($userId),
            'has_accounts' => $request->user()->accounts()->exists(),
            'accountTypes' => AccountType::options(),
            'summary' => $summary,
            'budget' => $this->budget($userId, $period->months(), $summary['total_cents']),
            'categories' => $this->categories($userId, $start, $end),
            'trend' => $this->trend($userId),
            'recent_transactions' => $this->transactionRows(
                Transaction::query()->recentSpending($userId)->get()
            ),
            'largest_transactions' => $this->transactionRows(
                Transaction::query()->largestSpending($userId, $start, $end)->get()
            ),
            'cash_flow' => $this->cashFlow($userId, $start, $end, $summary['total_cents']),
        ]);
    }

    /**
     * What came in, what went out, and what is left for the period. Transfers
     * between the user's own accounts are money that was never earned or spent,
     * so they move none of the three figures.
     *
     * @return array{income_cents: int, spending_cents: int, net_cents: int}
     */
    private function cashFlow(int $userId, Carbon $start, Carbon $end, int $spendingCents): array
    {
        $income = (int) Transaction::query()
            ->incomeSummary($userId, $start, $end)
            ->value('total_cents');

        return [
            'income_cents' => $income,
            'spending_cents' => $spendingCents,
            'net_cents' => $income - $spendingCents,
        ];
    }

    /**
     * Map transaction models to the dashboard's display row shape.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array{id: int, merchant_label: string, category_name: string|null, posted_at: string, amount_cents: int, currency: string}>
     */
    private function transactionRows($transactions): array
    {
        return $transactions
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'merchant_label' => $transaction->merchant?->name ?? 'Unknown',
                'category_name' => $transaction->category?->name,
                'posted_at' => $transaction->posted_at->toDateString(),
                'amount_cents' => $transaction->amount_cents,
                'currency' => $transaction->currency,
            ])
            ->all();
    }

    /**
     * Build the trailing 12-month spending trend, in chronological order, with
     * months that have no spending zero-filled so the timeline is continuous
     * (FR-006). Independent of the selected period.
     *
     * @return list<array{month: string, label: string, total_cents: int}>
     */
    private function trend(int $userId): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonthsNoOverflow(11)->startOfMonth();

        $totals = Transaction::query()
            ->monthlySpendingTrend($userId, $start, $end)
            ->get()
            ->keyBy('month');

        $months = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');

            $months[] = [
                'month' => $key,
                'label' => $cursor->format('M'),
                'total_cents' => (int) ($totals->get($key)->total_cents ?? 0),
            ];

            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * Build the spending-by-category breakdown for the period: every category
     * ranked by spend, each with its share of the period total. A NULL category
     * is reported as "Uncategorized" (FR-005).
     *
     * @return list<array{category_id: int|null, category_name: string, color: string|null, total_cents: int, percent: float}>
     */
    private function categories(int $userId, Carbon $start, Carbon $end): array
    {
        $rows = Transaction::query()->spendingByCategory($userId, $start, $end)->get();
        $periodTotal = (int) $rows->sum('total_cents');

        return $rows
            ->map(fn ($row): array => [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'category_name' => $row->category_name ?? 'Uncategorized',
                'color' => $row->color,
                'total_cents' => (int) $row->total_cents,
                'percent' => $periodTotal > 0
                    ? round(((int) $row->total_cents / $periodTotal) * 100, 1)
                    : 0.0,
            ])
            ->all();
    }

    /**
     * Build the period summary: total spent, transaction count, and the change
     * relative to the immediately preceding equal-length window.
     *
     * @return array{total_cents: int, transaction_count: int, previous_total_cents: int, change_percent: float|null}
     */
    private function summary(int $userId, Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd): array
    {
        $current = Transaction::query()->spendingSummary($userId, $start, $end)->first();
        $previous = Transaction::query()->spendingSummary($userId, $previousStart, $previousEnd)->first();

        $totalCents = (int) ($current->total_cents ?? 0);
        $previousTotalCents = (int) ($previous->total_cents ?? 0);

        return [
            'total_cents' => $totalCents,
            'transaction_count' => (int) ($current->transaction_count ?? 0),
            'previous_total_cents' => $previousTotalCents,
            'change_percent' => $previousTotalCents > 0
                ? round((($totalCents - $previousTotalCents) / $previousTotalCents) * 100, 1)
                : null,
        ];
    }

    /**
     * Resolve the immediately preceding window of equal calendar length, used
     * for the change-vs-previous-period comparison.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousWindow(Carbon $start, Carbon $end): array
    {
        $months = $start->diffInMonths($end->copy()->addDay()) ?: 1;

        return [
            $start->copy()->subMonthsNoOverflow($months)->startOfMonth(),
            $start->copy()->subMonthNoOverflow()->endOfMonth(),
        ];
    }

    /**
     * Build the overall budget summary for the period: the total recurring
     * monthly budget scaled to the window versus what was actually spent.
     * Returns null when the user has set no budgets, so the dashboard can hide
     * the widget.
     *
     * @return array{budgeted_cents: int, spent_cents: int, remaining_cents: int, percent: float|null}|null
     */
    private function budget(int $userId, int $months, int $spentCents): ?array
    {
        $totalMonthlyBudget = (int) Category::query()
            ->where('user_id', $userId)
            ->sum('monthly_budget_cents');

        if ($totalMonthlyBudget === 0) {
            return null;
        }

        $budgetedCents = $totalMonthlyBudget * $months;

        return [
            'budgeted_cents' => $budgetedCents,
            'spent_cents' => $spentCents,
            'remaining_cents' => $budgetedCents - $spentCents,
            'percent' => $budgetedCents > 0
                ? round(($spentCents / $budgetedCents) * 100, 1)
                : null,
        ];
    }
}
