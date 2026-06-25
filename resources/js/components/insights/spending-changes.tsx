import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SpendingChangesData } from './types';

export function SpendingChanges({
    changes,
    previousLabel,
    currentLabel,
    currency,
}: {
    changes: SpendingChangesData;
    previousLabel: string;
    currentLabel: string;
    currency: string;
}) {
    const maxChange = Math.max(
        1,
        ...changes.rows.map((row) => Math.abs(row.change_cents)),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>What changed since {previousLabel}</CardTitle>
            </CardHeader>
            <CardContent>
                {changes.rows.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Spending held steady across categories.
                    </p>
                ) : (
                    <>
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground">
                                <tr className="border-b">
                                    <th className="py-2 text-left font-medium">
                                        Category
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        {previousLabel}
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        {currentLabel}
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Change
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {changes.rows.map((row) => {
                                    const up = row.change_cents > 0;
                                    const barWidth =
                                        (Math.abs(row.change_cents) /
                                            maxChange) *
                                        50;

                                    return (
                                        <tr
                                            key={row.name}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 font-semibold">
                                                {row.name}
                                            </td>
                                            <td className="py-3 text-right font-mono text-muted-foreground tabular-nums">
                                                {formatMoney(
                                                    row.previous_cents,
                                                    currency,
                                                )}
                                            </td>
                                            <td className="py-3 text-right font-mono tabular-nums">
                                                {formatMoney(
                                                    row.current_cents,
                                                    currency,
                                                )}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex items-center justify-end gap-3">
                                                    <span className="relative hidden h-2 w-20 sm:block">
                                                        <span className="absolute inset-y-0 left-1/2 w-px bg-border" />
                                                        <span
                                                            className={cn(
                                                                'absolute top-0 h-2 rounded-sm',
                                                                up
                                                                    ? 'left-1/2 bg-destructive'
                                                                    : 'right-1/2 bg-emerald-500',
                                                            )}
                                                            style={{
                                                                width: `${barWidth}%`,
                                                            }}
                                                        />
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'w-30 text-right font-mono font-medium tabular-nums',
                                                            up
                                                                ? 'text-destructive'
                                                                : 'text-emerald-600 dark:text-emerald-400',
                                                        )}
                                                    >
                                                        {up ? '▲' : '▼'}{' '}
                                                        {formatSignedMoney(
                                                            row.change_cents,
                                                            currency,
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="mt-4 flex justify-end gap-6 text-sm text-muted-foreground">
                            <span>
                                Increases{' '}
                                <b className="font-mono text-destructive">
                                    {formatSignedMoney(
                                        changes.increases_cents,
                                        currency,
                                    )}
                                </b>
                            </span>
                            <span>
                                Decreases{' '}
                                <b className="font-mono text-emerald-600 dark:text-emerald-400">
                                    {formatSignedMoney(
                                        changes.decreases_cents,
                                        currency,
                                    )}
                                </b>
                            </span>
                            <span>
                                Net change{' '}
                                <b
                                    className={cn(
                                        'font-mono',
                                        changes.net_cents > 0
                                            ? 'text-destructive'
                                            : 'text-emerald-600 dark:text-emerald-400',
                                    )}
                                >
                                    {formatSignedMoney(
                                        changes.net_cents,
                                        currency,
                                    )}
                                </b>
                            </span>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
