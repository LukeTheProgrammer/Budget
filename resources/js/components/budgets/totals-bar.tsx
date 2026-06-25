import { ACCENT, STATUS_COLOR } from '@/components/budgets/budget-shared';
import { formatMoney } from '@/lib/format';

export function TotalsBar({
    totals,
    currency,
}: {
    totals: {
        budgeted: number;
        spent: number;
        remaining: number;
        percent: number;
    };
    currency: string;
}) {
    const over = totals.spent > totals.budgeted;
    // When over budget the bar represents total spend, so the budgeted portion
    // is shown in green and the overage as a trailing red segment. Otherwise the
    // green fill simply tracks spend against the budget.
    const budgetPct = over
        ? (totals.budgeted / totals.spent) * 100
        : totals.percent;
    const overPct = over ? 100 - budgetPct : 0;

    return (
        <div className="ml-auto w-full min-w-0">
            <div className="flex h-1.75 overflow-hidden rounded-full bg-muted ring-1 ring-border ring-inset">
                <div
                    className="h-full"
                    style={{
                        width: `${Math.min(budgetPct, 100)}%`,
                        backgroundColor: ACCENT,
                    }}
                />
                {over && (
                    <div
                        className="h-full"
                        style={{
                            width: `${overPct}%`,
                            backgroundColor: STATUS_COLOR.over.fill,
                        }}
                    />
                )}
            </div>
            <div className="mt-1.5 flex justify-between text-[14px] text-muted-foreground">
                <span>
                    {formatMoney(totals.spent, currency, true)} /{' '}
                    {formatMoney(totals.budgeted, currency, true)}
                </span>
                <span className="flex items-center gap-2">
                    {over && (
                        <span
                            className="font-medium"
                            style={{ color: STATUS_COLOR.over.text }}
                        >
                            {formatMoney(-totals.remaining, currency)} over
                        </span>
                    )}

                    {!over && (
                        <span className="font-medium" style={{ color: ACCENT }}>
                            {formatMoney(totals.remaining, currency)} under
                        </span>
                    )}
                </span>
            </div>
        </div>
    );
}
