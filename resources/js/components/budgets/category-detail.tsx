import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import MerchantController from '@/actions/App/Http/Controllers/Merchants/MerchantController';
import { CategoryDetailBar } from '@/components/budgets/category-detail-bar';
import { CategoryTrend } from '@/components/budgets/category-trend';
import { EditMerchantDialog } from '@/components/merchants/edit-merchant-dialog';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import type {
    BudgetPacing,
    BudgetTransaction,
    DerivedCategory,
    Merchant,
    MerchantTag,
} from '@/types';
import { ACCENT, STATUS_COLOR, STATUS_LABEL } from './budget-shared';

export function CategoryDetail({
    category,
    currency,
    pacing,
    onEdit,
}: {
    category: DerivedCategory;
    currency: string;
    pacing: BudgetPacing;
    onEdit: () => void;
}) {
    const { row, spent, budgeted, remaining, percent, status } = category;

    if (budgeted === null || status === null) {
        return (
            <main className="flex flex-col items-center justify-center gap-3 rounded-xl border bg-card p-12 text-center shadow-sm">
                <h1 className="text-2xl font-bold -tracking-[0.025em]">
                    {row.name}
                </h1>
                <p className="text-sm text-muted-foreground">
                    Spent {formatMoney(spent, currency)} this period — no
                    monthly budget set yet.
                </p>
                <Button type="button" onClick={onEdit}>
                    <Pencil className="size-3.5" /> Set a budget
                </Button>
            </main>
        );
    }

    const color = STATUS_COLOR[status];
    const over = remaining !== null && remaining < 0;
    // When over budget the bar represents total spend, so the budgeted portion
    // is shown in green and the overage as a trailing red segment. Otherwise the
    // green fill simply tracks spend against the budget.
    const budgetPct = over ? (budgeted / spent) * 100 : (percent ?? 0);
    const overPct = over ? 100 - budgetPct : 0;
    const monthFrac = pacing.days_elapsed / pacing.days_in_period;
    const pacePos = Math.round(monthFrac * 100);

    return (
        <main className="flex min-h-0 flex-col overflow-auto rounded-xl border bg-card px-8 py-7 shadow-sm">
            <div className="flex items-start justify-between">
                <div className="flex flex-col gap-2">
                    <div className="flex items-start justify-start">
                        <h1 className="text-3xl font-bold tracking-tight">{row.name}</h1>
                        <Button className="ml-3" type="button" variant="outline" onClick={onEdit}>
                            <Pencil className="size-3.5" /> {/* Edit budget */}
                        </Button>
                    </div>
                    <div className="flex items-start justify-start">
                        <StatusBadge status={STATUS_LABEL[status]} bg={color.bg} text={color.text} fill={color.fill} />
                    </div>
                </div>

                <div className="flex justify-end items-start">
                    <div className="text-[54px] text-end leading-none font-bold tracking-[-0.03em] tabular-nums">
                        {formatMoney(spent, currency)}
                    </div>
                </div>
            </div>

            <div className="mt-6">
                <CategoryDetailBar budgetPercent={budgetPct} pacePercent={pacePos} isOver={over} overPercent={overPct} />

                <div className="mt-2 flex justify-between font-mono text-[11.5px] text-muted-foreground">
                    <span>{formatMoney(0, currency)}</span>
                    <span>{formatMoney(budgeted, currency)}</span>
                </div>
            </div>

            {row.monthly_trend.length > 0 && (
                <CategoryTrend
                    trend={row.monthly_trend}
                    budgetCents={row.monthly_budget_cents ?? 0}
                    currency={currency}
                />
            )}

            <RecentTransactions
                transactions={row.recent_transactions}
                currency={currency}
            />
        </main>
    );
}

type EditingMerchant = {
    merchant: Merchant;
    availableTags: MerchantTag[];
};

function StatusBadge({
    status,
    bg,
    text,
    fill,
}: {
    status: string;
    bg: string;
    text: string;
    fill: string;
}) {
    return (
        <span
            className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[12.5px] font-semibold"
            style={{
                backgroundColor: bg,
                color: text,
            }}
        >
            <span
                className="size-1.75 rounded-full"
                style={{ backgroundColor: fill }}
            />
            {status}
        </span>
    );
}

function RecentTransactions({
    transactions,
    currency,
}: {
    transactions: BudgetTransaction[];
    currency: string;
}) {
    const sortedTransactions = [...transactions].sort(
        (a, b) => b.amount_cents - a.amount_cents,
    );

    const [editing, setEditing] = useState<EditingMerchant | null>(null);

    const loadMerchant = async (merchantId: number): Promise<void> => {
        const response = await fetch(MerchantController.show.url(merchantId), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data: { merchant: Merchant; available_tags: MerchantTag[] } =
            await response.json();
        setEditing({
            merchant: data.merchant,
            availableTags: data.available_tags,
        });
    };

    // The dialog mutates the merchant through Inertia visits that reload the
    // budgets page rather than this separately fetched copy, so refresh the
    // open merchant whenever a visit finishes to keep aliases/rules/tags current.
    useEffect(() => {
        const editingId = editing?.merchant.id;

        if (editingId === undefined) {
            return;
        }

        return router.on('finish', () => {
            void loadMerchant(editingId);
        });
    }, [editing?.merchant.id]);

    return (
        <div className="mt-7">
            <div className="font-mono text-[11px] tracking-[0.13em] text-muted-foreground uppercase">
                Top transactions
            </div>

            {sortedTransactions.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    No transactions in this category yet.
                </p>
            ) : (
                <table className="mt-3 w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-[11.5px] text-muted-foreground">
                            <th className="py-2 pr-3 font-medium">Date</th>
                            <th className="py-2 pr-3 font-medium">Merchant</th>
                            <th className="py-2 pr-3 font-medium">&nbsp;</th>
                            <th className="py-2 pl-3 text-right font-medium">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {sortedTransactions.map((transaction) => (
                            <tr
                                key={transaction.id}
                                className="border-b last:border-0"
                            >
                                <td className="py-2.5 pr-3 whitespace-nowrap text-muted-foreground tabular-nums">
                                    {formatDate(transaction.posted_at)}
                                </td>
                                <td className="py-2.5 pr-3">
                                    {transaction.merchant_id !== null ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                loadMerchant(
                                                    transaction.merchant_id!,
                                                )
                                            }
                                            className="truncate font-medium hover:underline"
                                        >
                                            {transaction.merchant_name ??
                                                'Unknown merchant'}
                                        </button>
                                    ) : (
                                        <div className="truncate font-medium">
                                            {transaction.merchant_name ??
                                                'Unknown merchant'}
                                        </div>
                                    )}
                                </td>
                                <td className="py-2.5 pr-3">
                                    {transaction.description && (
                                        <div className="truncate text-[12px] text-muted-foreground">
                                            {transaction.description}
                                        </div>
                                    )}
                                </td>
                                <td className="py-2.5 pl-3 text-right font-semibold tabular-nums">
                                    {formatMoney(
                                        transaction.amount_cents,
                                        currency,
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {editing && (
                <EditMerchantDialog
                    merchant={editing.merchant}
                    availableTags={editing.availableTags}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                />
            )}
        </div>
    );
}

/** Format a `YYYY-MM-DD` date string for compact display (e.g. "Jun 9"). */
function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
