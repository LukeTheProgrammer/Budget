import { useSyncExternalStore } from 'react';
import { Bar, BarChart, CartesianGrid, ReferenceLine, XAxis } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatMoney } from '@/lib/format';

export type TrendPoint = {
    month: string;
    label: string;
    total_cents: number;
};

const chartConfig = {
    total_cents: { label: 'Spent', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const noop = () => () => {};

/**
 * Returns false during SSR and the first client render, then true once
 * hydrated. Lets us render the average reference line on the client only,
 * avoiding a recharts hydration mismatch without a setState-in-effect.
 */
export function useIsHydrated(): boolean {
    return useSyncExternalStore(
        noop,
        () => true,
        () => false,
    );
}

export function ReferenceLineLabel({
    text,
    viewBox,
}: {
    text: string;
    viewBox?: { x?: number; y?: number; width?: number; height?: number };
}) {
    if (!viewBox) {
        return null;
    }

    const padding = 6;
    const charWidth = 6.5;
    const boxWidth = text.length * charWidth + padding * 2;
    const boxHeight = 18;
    const x = (viewBox.x ?? 0) + 4;
    const y = (viewBox.y ?? 0) + 4;

    return (
        <g>
            <rect x={x} y={y} width={boxWidth} height={boxHeight} rx={4} fill="#27272a" />
            <text
                x={x + boxWidth / 2}
                y={y + boxHeight / 2}
                fill="#ffffff"
                fontSize={12}
                textAnchor="middle"
                dominantBaseline="central"
            >
                {text}
            </text>
        </g>
    );
}

export function SpendingTrend({ trend, currency }: { trend: TrendPoint[]; currency: string }) {
    const isHydrated = useIsHydrated();

    const average = trend.length > 0 ? trend.reduce((sum, point) => sum + point.total_cents, 0) / trend.length : 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Spending trend</CardTitle>
            </CardHeader>
            <CardContent>
                <ChartContainer config={chartConfig} className="max-h-[260px] w-full">
                    <BarChart data={trend} accessibilityLayer>
                        <CartesianGrid vertical={false} />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    labelKey="month"
                                    formatter={(value) => formatMoney(Number(value), currency)}
                                />
                            }
                        />
                        <Bar dataKey="total_cents" fill="var(--color-total_cents)" radius={4} />
                        {isHydrated && (
                            <ReferenceLine
                                y={average}
                                stroke="#27272a"
                                label={<ReferenceLineLabel text={`Avg ${formatMoney(average, currency)}`} />}
                            />
                        )}
                    </BarChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
