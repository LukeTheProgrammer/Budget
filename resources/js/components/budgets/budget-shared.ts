import type { BudgetCategoryRow, BudgetStatus, DerivedCategory } from '@/types';

/** Fixed accent semantics for budget status, mapped from the design tokens. */
export const STATUS_COLOR: Record<
    BudgetStatus,
    { fill: string; bg: string; text: string }
> = {
    ok: {
        fill: 'oklch(0.58 0.075 158)',
        bg: 'oklch(0.95 0.03 158)',
        text: 'oklch(0.40 0.075 158)',
    },
    warn: {
        fill: 'oklch(0.70 0.12 72)',
        bg: 'oklch(0.95 0.05 80)',
        text: 'oklch(0.5 0.1 65)',
    },
    over: {
        fill: 'oklch(0.57 0.16 28)',
        bg: 'oklch(0.95 0.045 32)',
        text: 'oklch(0.57 0.16 28)',
    },
};

export const ACCENT = 'oklch(0.52 0.085 158)';

export const STATUS_LABEL: Record<BudgetStatus, string> = {
    ok: 'On track',
    warn: 'Approaching limit',
    over: 'Over budget',
};

export function deriveCategory(
    row: BudgetCategoryRow,
    months: number,
): DerivedCategory {
    const spent = row.actual_cents;

    if (row.monthly_budget_cents === null) {
        return {
            row,
            spent,
            budgeted: null,
            remaining: null,
            ratio: null,
            percent: null,
            status: null,
        };
    }

    const budgeted = row.monthly_budget_cents * months;
    const ratio = budgeted > 0 ? spent / budgeted : 0;
    const status: BudgetStatus =
        spent > budgeted ? 'over' : ratio >= 0.85 ? 'warn' : 'ok';

    return {
        row,
        spent,
        budgeted,
        remaining: budgeted - spent,
        ratio,
        percent: Math.round(ratio * 100),
        status,
    };
}

export function monogram(name: string): string {
    return name.replace(/&/g, '').trim().slice(0, 1).toUpperCase() || '?';
}
