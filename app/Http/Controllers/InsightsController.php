<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\SessionPeriod;
use App\Support\UserCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    /**
     * Render the monthly review: what pushed spending past the category caps in
     * the active period, and what changed versus the immediately preceding
     * window of equal length.
     */
    public function index(Request $request): Response
    {
        $period = SessionPeriod::fromSession($request->session()->get('session_period'));
        [$start, $end] = $period->window();
        [$previousStart, $previousEnd] = $this->previousWindow($start, $end);
        $months = $period->months();
        $userId = $request->user()->id;

        $categories = Category::query()
            ->where('user_id', $userId)
            ->get(['id', 'name', 'color', 'monthly_budget_cents']);

        $currentSpend = $this->categorySpend($userId, $start, $end);
        $previousSpend = $this->categorySpend($userId, $previousStart, $previousEnd);

        $overage = $this->overage($categories, $currentSpend, $previousSpend, $months);

        $currentTotal = (int) $currentSpend->sum('total_cents');
        $previousTotal = (int) $previousSpend->sum('total_cents');

        return Inertia::render('insights', [
            'currency' => UserCurrency::for($userId),
            'previous_label' => $this->windowLabel($previousStart, $previousEnd),
            'has_budgets' => $categories->whereNotNull('monthly_budget_cents')->isNotEmpty(),
            'summary' => [
                'total_overage_cents' => $overage['total_cents'],
                'categories_over' => $overage['categories_over'],
                'categories_total' => $categories->count(),
                'vs_previous_cents' => $currentTotal - $previousTotal,
                'vs_previous_percent' => $previousTotal > 0
                    ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1)
                    : null,
                'biggest_contributor' => $overage['contributors'][0] ?? null,
                'largest_charge' => $this->largestCharge($userId, $start, $end),
            ],
            'over_categories' => $overage['contributors'],
            'composition' => $overage['composition'],
            'changes' => $this->changes($currentSpend, $previousSpend),
        ]);
    }

    /**
     * Aggregate spending per category for the window, keyed by category id, with
     * the transaction count alongside the summed cents. A NULL category id keys
     * the "Uncategorized" bucket.
     *
     * @return Collection<int|string, object>
     */
    private function categorySpend(int $userId, Carbon $start, Carbon $end): Collection
    {
        return Transaction::query()
            ->spendingByCategory($userId, $start, $end)
            ->addSelect(DB::raw('COUNT(*) as transaction_count'))
            ->get()
            ->keyBy('category_id');
    }

    /**
     * Compute the overage picture for the period: each budgeted category's spend
     * against its period-scaled cap, the categories that ran over ranked by
     * overage, the total overage, and that total broken down by source.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int|string, object>  $currentSpend
     * @param  Collection<int|string, object>  $previousSpend
     * @return array{total_cents: int, categories_over: int, contributors: list<array<string, mixed>>, composition: list<array<string, mixed>>}
     */
    private function overage(Collection $categories, Collection $currentSpend, Collection $previousSpend, int $months): array
    {
        $contributors = $categories
            ->filter(fn (Category $category): bool => $category->monthly_budget_cents !== null)
            ->map(function (Category $category) use ($currentSpend, $previousSpend, $months): array {
                $cap = (int) $category->monthly_budget_cents * $months;
                $spent = (int) ($currentSpend->get($category->id)->total_cents ?? 0);
                $previous = (int) ($previousSpend->get($category->id)->total_cents ?? 0);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'spent_cents' => $spent,
                    'cap_cents' => $cap,
                    'over_cents' => max(0, $spent - $cap),
                    'change_cents' => $spent - $previous,
                    'transaction_count' => (int) ($currentSpend->get($category->id)->transaction_count ?? 0),
                ];
            })
            ->filter(fn (array $row): bool => $row['over_cents'] > 0)
            ->sortByDesc('over_cents')
            ->values();

        $totalOverage = (int) $contributors->sum('over_cents');

        $composition = $contributors
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'color' => $row['color'],
                'over_cents' => $row['over_cents'],
                'percent' => $totalOverage > 0
                    ? round(($row['over_cents'] / $totalOverage) * 100, 1)
                    : 0.0,
            ])
            ->all();

        return [
            'total_cents' => $totalOverage,
            'categories_over' => $contributors->count(),
            'contributors' => $contributors->all(),
            'composition' => $composition,
        ];
    }

    /**
     * Build the category-level diff between the current and previous windows:
     * every category whose spend moved, ranked by the size of the change, with
     * the increase/decrease/net totals the footer reports.
     *
     * @param  Collection<int|string, object>  $currentSpend
     * @param  Collection<int|string, object>  $previousSpend
     * @return array{rows: list<array<string, mixed>>, increases_cents: int, decreases_cents: int, net_cents: int}
     */
    private function changes(Collection $currentSpend, Collection $previousSpend): array
    {
        $keys = $currentSpend->keys()->merge($previousSpend->keys())->unique();

        $rows = $keys
            ->map(function ($key) use ($currentSpend, $previousSpend): array {
                $current = $currentSpend->get($key);
                $previous = $previousSpend->get($key);

                $currentCents = (int) ($current->total_cents ?? 0);
                $previousCents = (int) ($previous->total_cents ?? 0);

                return [
                    'name' => $current->category_name ?? $previous->category_name ?? 'Uncategorized',
                    'color' => $current->color ?? $previous->color ?? null,
                    'previous_cents' => $previousCents,
                    'current_cents' => $currentCents,
                    'change_cents' => $currentCents - $previousCents,
                ];
            })
            ->filter(fn (array $row): bool => $row['change_cents'] !== 0)
            ->sortByDesc(fn (array $row): int => abs($row['change_cents']))
            ->values();

        return [
            'rows' => $rows->take(8)->all(),
            'increases_cents' => (int) $rows->where('change_cents', '>', 0)->sum('change_cents'),
            'decreases_cents' => (int) $rows->where('change_cents', '<', 0)->sum('change_cents'),
            'net_cents' => (int) $rows->sum('change_cents'),
        ];
    }

    /**
     * The single largest spending transaction in the window, for the hero stat.
     *
     * @return array{amount_cents: int, merchant: string, category: string|null}|null
     */
    private function largestCharge(int $userId, Carbon $start, Carbon $end): ?array
    {
        $transaction = Transaction::query()->largestSpending($userId, $start, $end, 1)->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'amount_cents' => $transaction->amount_cents,
            'merchant' => $transaction->merchant?->name ?? 'Unknown',
            'category' => $transaction->merchant?->category?->name,
        ];
    }

    /**
     * The immediately preceding window of equal calendar length, used as the
     * comparison baseline for the diff and the month-over-month stat.
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
     * A short human-readable label for a window, e.g. "May" or "Mar – May".
     */
    private function windowLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameMonth($end)) {
            return $start->format('F');
        }

        return "{$start->format('M')} – {$end->format('M')}";
    }
}
