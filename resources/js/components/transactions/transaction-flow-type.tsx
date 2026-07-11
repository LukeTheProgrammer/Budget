import { router } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import TransactionFlowTypeController from '@/actions/App/Http/Controllers/Transactions/TransactionFlowTypeController';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

export type FlowType = 'expense' | 'income' | 'transfer' | 'refund';

export type FlowTypeOption = {
    value: string;
    label: string;
};

/**
 * The colour of each kind of money movement. Expenses are the neutral baseline;
 * income is money in; transfers merely moved and count as neither; refunds are
 * money coming back.
 */
const TRIGGER_CLASSES: Record<FlowType, string> = {
    expense: '',
    income: 'text-emerald-700 dark:text-emerald-400',
    transfer: 'text-sky-700 dark:text-sky-400',
    refund: 'text-amber-700 dark:text-amber-400',
};

type FlowTypeSelectProps = {
    transactionId: number;
    flowType: FlowType;
    options: FlowTypeOption[];
    isPairedTransfer?: boolean;
};

/**
 * A transaction's flow type, shown and edited in place. The correction is taught
 * to the merchant as well, so the same statement row arriving next month
 * classifies correctly without a second correction.
 *
 * A paired transfer carries a link icon: both legs of the movement are present,
 * so the money is provably neither spent nor earned.
 */
export function FlowTypeSelect({ transactionId, flowType, options, isPairedTransfer = false }: FlowTypeSelectProps) {
    const reclassify = (value: string): void => {
        router.patch(
            TransactionFlowTypeController.update.url(transactionId),
            { flow_type: value, apply_to_merchant: true },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="flex items-center gap-1">
            <Select value={flowType} onValueChange={reclassify}>
                <SelectTrigger size="sm" className={cn('w-[124px]', TRIGGER_CLASSES[flowType])} aria-label="Flow type">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {isPairedTransfer ? (
                <Link2
                    className="size-3.5 shrink-0 text-sky-600 dark:text-sky-400"
                    aria-label="Matched to the other leg of this transfer"
                />
            ) : null}
        </div>
    );
}

/**
 * Colour an amount by its direction, so money in never reads as money out.
 */
export function flowAmountClasses(flowType: FlowType, amountCents: number): string {
    if (amountCents < 0) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    if (flowType === 'transfer') {
        return 'text-muted-foreground';
    }

    return '';
}

/**
 * Render an amount with its direction made explicit. Amounts are stored
 * positive for money out and negative for money in, which is a convention for
 * the database, not for a person reading a table: an inflow is shown with a
 * leading "+" rather than as a bare negative number.
 */
export function formatFlowAmount(amountCents: number, currency: string): string {
    if (amountCents < 0) {
        return `+${formatMoney(Math.abs(amountCents), currency)}`;
    }

    return formatMoney(amountCents, currency);
}
