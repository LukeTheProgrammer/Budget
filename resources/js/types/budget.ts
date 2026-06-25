export type BudgetTransaction = {
    id: number;
    description: string | null;
    merchant_id: number | null;
    merchant_name: string | null;
    amount_cents: number;
    posted_at: string;
};

export type CategoryTrendPoint = {
    month: string;
    label: string;
    total_cents: number;
};

export type BudgetCategoryRow = {
    id: number;
    name: string;
    color: string | null;
    monthly_budget_cents: number | null;
    actual_cents: number;
    recent_transactions: BudgetTransaction[];
    monthly_trend: CategoryTrendPoint[];
};

export type BudgetPacing = {
    days_in_period: number;
    days_elapsed: number;
    days_left: number;
};

export type BudgetSummary = {
    budgeted_cents: number;
    spent_cents: number;
    remaining_cents: number;
    percent: number | null;
};

export type BudgetsPageProps = {
    currency: string;
    months: number;
    pacing: BudgetPacing;
    categories: BudgetCategoryRow[];
};

export type SortMode = 'alpha' | 'budget' | 'spent';

export type BudgetStatus = 'ok' | 'warn' | 'over';

/**
 * A category enriched with the period-scaled budget, actual spend, and the
 * derived ratio/status the dashboard renders. `budgeted` is null when the
 * category has no recurring monthly budget set.
 */
export type DerivedCategory = {
    row: BudgetCategoryRow;
    spent: number;
    budgeted: number | null;
    remaining: number | null;
    ratio: number | null;
    percent: number | null;
    status: BudgetStatus | null;
};
