import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import { swatchColor } from './swatch-color';
import type { CompositionSlice } from './types';

export function OverageComposition({
    slices,
    totalCents,
    currency,
}: {
    slices: CompositionSlice[];
    totalCents: number;
    currency: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>By source</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-baseline gap-2">
                    <span className="font-mono text-3xl font-medium text-destructive tabular-nums">
                        {formatSignedMoney(totalCents, currency)}
                    </span>
                    <span className="text-xs text-muted-foreground">total overage</span>
                </div>

                <div className="mt-4 flex h-7 overflow-hidden rounded-lg ring-1 ring-border ring-inset">
                    {slices.map((slice, index) => (
                        <span
                            key={slice.name}
                            className="h-full"
                            style={{
                                width: `${slice.percent}%`,
                                background: swatchColor(slice.color, index),
                            }}
                        />
                    ))}
                </div>

                <ul className="mt-4">
                    {slices.map((slice, index) => (
                        <li
                            key={slice.name}
                            className="flex items-center gap-2.5 border-t py-2 text-sm first:border-t-0"
                        >
                            <span
                                className="size-3 rounded-sm"
                                style={{
                                    background: swatchColor(slice.color, index),
                                }}
                            />
                            <span>{slice.name}</span>
                            <span className="ml-auto font-mono text-sm text-muted-foreground">
                                {formatMoney(slice.over_cents, currency)}
                            </span>
                            <span className="w-10 text-right font-mono text-xs text-muted-foreground">
                                {slice.percent}%
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
