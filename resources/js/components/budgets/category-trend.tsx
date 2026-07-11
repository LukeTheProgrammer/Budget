import { Bar, BarChart, CartesianGrid, ReferenceLine, XAxis } from 'recharts';
import { ReferenceLineLabel, useIsHydrated } from '@/components/dashboard/spending-trend';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatMoney } from '@/lib/format';
import type { CategoryTrendPoint } from '@/types/budget';

const chartConfig = {
    total_cents: { label: 'Spent', color: 'var(--chart-1)' },
} satisfies ChartConfig;

/**
 * Twelve-month spending trend for a single category, mirroring the dashboard's
 * spending-trend chart but with a reference line at the category's recurring
 * monthly budget rather than the running average.
 */
export function CategoryTrend({
    trend,
    budgetCents,
    currency,
}: {
    trend: CategoryTrendPoint[];
    budgetCents: number;
    currency: string;
}) {
    const isHydrated = useIsHydrated();

    return (
        <div className="mt-7">
            <div className="font-mono text-[11px] tracking-[0.13em] text-muted-foreground uppercase">
                Last 12 months
            </div>
            {/*
             * Recharts derives its SVG geometry from the measured container
             * width, which the layout-driven detail panel only resolves in the
             * browser. Rendering the chart only after hydration avoids a
             * server/client mismatch; a fixed-height placeholder reserves space.
             */}
            {!isHydrated ? (
                <div className="mt-3 h-[260px] w-full" />
            ) : (
                <ChartContainer config={chartConfig} className="mt-3 max-h-[260px] w-full">
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
                        <ReferenceLine
                            y={budgetCents}
                            stroke="#27272a"
                            label={<ReferenceLineLabel text={`Budget ${formatMoney(budgetCents, currency, true)}`} />}
                        />
                    </BarChart>
                </ChartContainer>
            )}
        </div>
    );
}
