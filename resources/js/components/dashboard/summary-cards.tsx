import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatChangePercent, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

export type SpendingSummary = {
    total_cents: number;
    transaction_count: number;
    previous_total_cents: number;
    change_percent: number | null;
};

function ChangeIndicator({
    summary,
    currency,
}: {
    summary: SpendingSummary;
    currency: string;
}) {
    const { change_percent, previous_total_cents } = summary;

    if (change_percent === null) {
        return (
            <span className="text-muted-foreground">
                No prior period to compare
            </span>
        );
    }

    const Icon =
        change_percent > 0
            ? TrendingUp
            : change_percent < 0
              ? TrendingDown
              : Minus;
    const tone =
        change_percent > 0
            ? 'text-red-600 dark:text-red-400'
            : change_percent < 0
              ? 'text-emerald-600 dark:text-emerald-400'
              : 'text-muted-foreground';

    return (
        <span className={cn('inline-flex items-center gap-1', tone)}>
            <Icon className="size-4" />
            {formatChangePercent(change_percent)}
            <span className="text-muted-foreground">
                vs. {formatMoney(previous_total_cents, currency)}
            </span>
        </span>
    );
}

export function SummaryCards({
    summary,
    currency,
}: {
    summary: SpendingSummary;
    currency: string;
}) {
    return (
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>Total spent</CardDescription>
                    <CardTitle className="text-2xl">
                        {formatMoney(summary.total_cents, currency)}
                    </CardTitle>
                </CardHeader>
                <CardContent className="text-sm">
                    <ChangeIndicator summary={summary} currency={currency} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription>Transactions</CardDescription>
                    <CardTitle className="text-2xl">
                        {summary.transaction_count.toLocaleString()}
                    </CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground">
                    in this period
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription>Previous period</CardDescription>
                    <CardTitle className="text-2xl">
                        {formatMoney(summary.previous_total_cents, currency)}
                    </CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground">
                    total spent
                </CardContent>
            </Card>
        </div>
    );
}
