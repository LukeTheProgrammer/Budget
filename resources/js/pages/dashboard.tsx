import { Head, usePage } from '@inertiajs/react';
import { BudgetSummaryCard } from '@/components/dashboard/budget-summary';
import { CategoryBreakdown } from '@/components/dashboard/category-breakdown';
import type { CategoryBreakdownRow } from '@/components/dashboard/category-breakdown';
import { SpendingTrend } from '@/components/dashboard/spending-trend';
import type { TrendPoint } from '@/components/dashboard/spending-trend';
import { SummaryCards } from '@/components/dashboard/summary-cards';
import type { SpendingSummary } from '@/components/dashboard/summary-cards';
import { TransactionsTable } from '@/components/dashboard/transactions-table';
import type { TransactionRow } from '@/components/dashboard/transactions-table';
import { dashboard } from '@/routes';
import type { BudgetSummary } from '@/types';

export type DashboardProps = {
    currency: string;
    summary: SpendingSummary;
    budget: BudgetSummary | null;
    categories: CategoryBreakdownRow[];
    trend: TrendPoint[];
    recent_transactions: TransactionRow[];
    largest_transactions: TransactionRow[];
};

export default function Dashboard({
    currency,
    summary,
    budget,
    categories,
    trend,
    recent_transactions,
    largest_transactions,
}: DashboardProps) {
    const period = usePage().props.period;
    const hasSpending =
        summary.total_cents > 0 || summary.transaction_count > 0;
    const trendHasData = trend.some((point) => point.total_cents > 0);
    const hasTransactions =
        recent_transactions.length > 0 || largest_transactions.length > 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Spending</h1>
                        <p className="text-sm text-muted-foreground">
                            {period.label}
                        </p>
                    </div>
                </div>

                {budget && (
                    <BudgetSummaryCard budget={budget} currency={currency} />
                )}

                {trendHasData && (
                    <SpendingTrend trend={trend} currency={currency} />
                )}

                {hasSpending ? (
                    <>
                        {categories.length > 0 && (
                            <CategoryBreakdown
                                categories={categories}
                                currency={currency}
                            />
                        )}
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-12 text-center">
                        <p className="font-medium">
                            No spending in this period
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Try a different period or import some transactions
                            to get started.
                        </p>
                    </div>
                )}

                {hasTransactions && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <TransactionsTable
                            title="Recent transactions"
                            transactions={recent_transactions}
                            emptyMessage="No recent transactions."
                        />
                        <TransactionsTable
                            title="Largest this period"
                            transactions={largest_transactions}
                            emptyMessage="No spending in this period."
                        />
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
