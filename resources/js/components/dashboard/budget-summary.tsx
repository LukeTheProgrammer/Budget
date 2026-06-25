import { ACCENT, STATUS_COLOR } from '@/components/budgets/budget-shared';
import { Card, CardContent } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import type { BudgetSummary } from '@/types';

export function BudgetSummaryCard({
    budget,
    currency,
}: {
    budget: BudgetSummary;
    currency: string;
}) {
    const overBudget = budget.remaining_cents < 0;
    // When over budget the bar represents total spend, so the budgeted portion
    // is shown as the accent fill and the overage as a trailing red segment.
    // Otherwise the accent fill simply tracks spend against the budget.
    const budgetPercent = overBudget
        ? (budget.budgeted_cents / budget.spent_cents) * 100
        : (budget.percent ?? 0);
    const overPercent = overBudget ? 100 - budgetPercent : 0;

    return (
        <Card>
            <CardContent className="space-y-3">
                <div className="flex items-baseline justify-between">
                    <span className="text-2xl font-semibold tabular-nums">
                        {formatMoney(budget.spent_cents, currency)}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        of {formatMoney(budget.budgeted_cents, currency)}
                    </span>
                </div>

                <div className="flex h-7.5 overflow-hidden rounded-[9px] bg-muted ring-1 ring-border ring-inset">
                    <div
                        className="h-full"
                        style={{
                            width: `${Math.min(budgetPercent, 100)}%`,
                            backgroundColor: ACCENT,
                        }}
                    />
                    {overBudget && (
                        <div
                            className="h-full"
                            style={{
                                width: `${overPercent}%`,
                                backgroundColor: STATUS_COLOR.over.fill,
                            }}
                        />
                    )}
                </div>

                <p className="text-sm text-muted-foreground">
                    {overBudget
                        ? `${formatMoney(Math.abs(budget.remaining_cents), currency)} over budget`
                        : `${formatMoney(budget.remaining_cents, currency)} remaining`}
                </p>
            </CardContent>
        </Card>
    );
}
