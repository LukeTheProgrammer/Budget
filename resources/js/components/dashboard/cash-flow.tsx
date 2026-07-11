import { ArrowDownLeft, ArrowUpRight, Wallet } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

export type CashFlow = {
    income_cents: number;
    spending_cents: number;
    net_cents: number;
};

/**
 * What came in, what went out, and what is left for the period.
 *
 * Transfers between the user's own accounts move none of these figures: moving
 * money from checking to savings is not income, and paying a credit card is not
 * a second expense on top of the purchases already counted.
 */
export function CashFlowCards({ cashFlow, currency }: { cashFlow: CashFlow; currency: string }) {
    const { income_cents, spending_cents, net_cents } = cashFlow;
    const isNegative = net_cents < 0;

    return (
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>Money in</CardDescription>
                    <CardTitle className="text-2xl text-emerald-700 dark:text-emerald-400">
                        {formatMoney(income_cents, currency)}
                    </CardTitle>
                </CardHeader>
                <CardContent className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                    <ArrowDownLeft className="size-4" />
                    income this period
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription>Money out</CardDescription>
                    <CardTitle className="text-2xl">{formatMoney(spending_cents, currency)}</CardTitle>
                </CardHeader>
                <CardContent className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                    <ArrowUpRight className="size-4" />
                    spending this period
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription>Net cash flow</CardDescription>
                    <CardTitle
                        className={cn(
                            'text-2xl',
                            isNegative ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400',
                        )}
                    >
                        {isNegative ? '−' : '+'}
                        {formatMoney(Math.abs(net_cents), currency)}
                    </CardTitle>
                </CardHeader>
                <CardContent className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                    <Wallet className="size-4" />
                    {isNegative ? 'spent more than came in' : 'left over'}
                </CardContent>
            </Card>
        </div>
    );
}
