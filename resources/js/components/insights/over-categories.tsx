import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import { swatchColor } from './swatch-color';
import type { OverCategory } from './types';

export function OverCategories({
    rows,
    currency,
}: {
    rows: OverCategory[];
    currency: string;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-4 space-y-0">
                <CardTitle>What pushed you over</CardTitle>
                <span className="text-sm text-muted-foreground">
                    Ranked by overage
                </span>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        No category ran over its cap this period.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {rows.map((row, index) => {
                            const color = swatchColor(row.color, index);
                            const capRatio =
                                row.spent_cents > 0
                                    ? Math.min(
                                          100,
                                          (row.cap_cents / row.spent_cents) *
                                              100,
                                      )
                                    : 100;

                            return (
                                <li
                                    key={row.id}
                                    className="grid grid-cols-[20px_1fr_120px_80px] items-center gap-3 rounded-lg px-2 py-3 hover:bg-muted/50"
                                >
                                    <span className="text-center font-mono text-xs text-muted-foreground">
                                        {index + 1}
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2 text-sm font-semibold">
                                            <span
                                                className="size-2.5 rounded-sm"
                                                style={{
                                                    background: color,
                                                }}
                                            />
                                            {row.name}
                                        </div>
                                        <div className="mt-1 pl-[18px] text-xs text-muted-foreground">
                                            {formatMoney(
                                                row.spent_cents,
                                                currency,
                                            )}{' '}
                                            of{' '}
                                            {formatMoney(
                                                row.cap_cents,
                                                currency,
                                            )}{' '}
                                            cap · {row.transaction_count}{' '}
                                            {row.transaction_count === 1
                                                ? 'transaction'
                                                : 'transactions'}
                                        </div>
                                    </div>
                                    <div className="relative h-2 overflow-hidden rounded-full bg-muted ring-1 ring-border ring-inset">
                                        <span
                                            className="absolute inset-y-[-2px] w-px bg-foreground/50"
                                            style={{ left: `${capRatio}%` }}
                                        />
                                        <span
                                            className="block h-full rounded-full"
                                            style={{
                                                width: '100%',
                                                background: color,
                                            }}
                                        />
                                    </div>
                                    <div className="text-right">
                                        <div className="font-mono text-base font-medium text-destructive">
                                            {formatSignedMoney(
                                                row.over_cents,
                                                currency,
                                            )}
                                        </div>
                                        <div className="text-[11px] text-muted-foreground">
                                            over cap
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
