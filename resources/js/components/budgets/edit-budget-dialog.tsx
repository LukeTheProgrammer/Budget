import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { update } from '@/routes/budgets';
import type { BudgetCategoryRow, BudgetStatus } from '@/types';
import { monogram, STATUS_COLOR } from './budget-shared';

/** Convert an integer cent amount to a plain dollar string for an input field. */
function centsToInput(cents: number | null): string {
    return cents === null ? '' : (cents / 100).toString();
}

/** Convert a dollar input string to integer cents, or null when blank. */
function inputToCents(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

export function EditBudgetDialog({
    category,
    currency,
    months,
    onClose,
}: {
    category: BudgetCategoryRow | null;
    currency: string;
    months: number;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={category !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                {category && (
                    <EditBudgetForm
                        key={category.id}
                        category={category}
                        currency={currency}
                        months={months}
                        onSaved={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function EditBudgetForm({
    category,
    currency,
    months,
    onSaved,
}: {
    category: BudgetCategoryRow;
    currency: string;
    months: number;
    onSaved: () => void;
}) {
    const { data, setData, patch, processing, transform } = useForm({
        amount: centsToInput(category.monthly_budget_cents),
    });

    const enteredCents = inputToCents(data.amount);
    const budgeted = enteredCents === null ? 0 : enteredCents * months;
    const ratio = budgeted > 0 ? category.actual_cents / budgeted : 0;
    const percent = Math.round(ratio * 100);
    const status: BudgetStatus =
        budgeted > 0 && category.actual_cents > budgeted ? 'over' : ratio >= 0.85 ? 'warn' : 'ok';
    const color = STATUS_COLOR[status];

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        transform(() => ({
            budgets: [
                {
                    category_id: category.id,
                    amount_cents: inputToCents(data.amount),
                },
            ],
        }));

        patch(update().url, {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    }

    return (
        <form onSubmit={submit} className="space-y-5">
            <DialogHeader className="flex-row items-center gap-3 space-y-0">
                <span
                    className="grid size-10 shrink-0 place-items-center rounded-xl font-mono text-base"
                    style={{
                        backgroundColor: STATUS_COLOR.ok.bg,
                        color: STATUS_COLOR.ok.text,
                    }}
                >
                    {monogram(category.name)}
                </span>
                <div className="text-left">
                    <DialogTitle>Edit · {category.name}</DialogTitle>
                    <DialogDescription>Set the recurring monthly budget for this category.</DialogDescription>
                </div>
            </DialogHeader>

            <div className="space-y-2">
                <Label htmlFor="edit-budget-amount">Monthly budget ({currency})</Label>
                <Input
                    id="edit-budget-amount"
                    type="number"
                    inputMode="decimal"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    autoFocus
                    value={data.amount}
                    onChange={(event) => setData('amount', event.target.value)}
                />
                <div className="flex justify-between text-[11.5px] text-muted-foreground">
                    <span>Spent so far: {formatMoney(category.actual_cents, currency)}</span>
                    {budgeted > 0 && <span>{percent}% used</span>}
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-muted ring-1 ring-border ring-inset">
                    {budgeted > 0 && (
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${Math.min(percent, 100)}%`,
                                backgroundColor: color.fill,
                            }}
                        />
                    )}
                </div>
                <p className="text-[11.5px] text-muted-foreground">Leave blank to clear the budget.</p>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={processing}>
                    Save changes
                </Button>
            </DialogFooter>
        </form>
    );
}
