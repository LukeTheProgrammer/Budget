import { useMemo } from 'react';
import { Cell, Pie, PieChart } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatMoney } from '@/lib/format';

export type CategoryBreakdownRow = {
    category_id: number | null;
    category_name: string;
    color: string | null;
    total_cents: number;
    percent: number;
};

/** Maximum category slices shown in the chart before consolidating into "Other" (FR-013). */
const MAX_SLICES = 8;

/** Fallback palette for categories without an assigned color. */
const PALETTE = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

function sliceColor(row: CategoryBreakdownRow, index: number): string {
    return row.color ?? PALETTE[index % PALETTE.length];
}

export function CategoryBreakdown({ categories, currency }: { categories: CategoryBreakdownRow[]; currency: string }) {
    const { chartData, chartConfig } = useMemo(() => {
        const top = categories.slice(0, MAX_SLICES);
        const rest = categories.slice(MAX_SLICES);

        const data = top.map((row, index) => ({
            name: row.category_name,
            value: row.total_cents,
            fill: sliceColor(row, index),
        }));

        if (rest.length > 0) {
            data.push({
                name: 'Other',
                value: rest.reduce((sum, row) => sum + row.total_cents, 0),
                fill: 'var(--muted-foreground)',
            });
        }

        const config: ChartConfig = Object.fromEntries(data.map((d) => [d.name, { label: d.name, color: d.fill }]));

        return { chartData: data, chartConfig: config };
    }, [categories]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Spending by category</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6 md:grid-cols-2">
                <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[260px]">
                    <PieChart>
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    hideLabel
                                    formatter={(value) => formatMoney(Number(value), currency)}
                                />
                            }
                        />
                        <Pie data={chartData} dataKey="value" nameKey="name" innerRadius={60}>
                            {chartData.map((entry) => (
                                <Cell key={entry.name} fill={entry.fill} />
                            ))}
                        </Pie>
                    </PieChart>
                </ChartContainer>

                <div className="overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground">
                            <tr className="border-b">
                                <th className="py-2 text-left font-medium">Category</th>
                                <th className="py-2 text-right font-medium">Amount</th>
                                <th className="py-2 text-right font-medium">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((row) => (
                                <tr key={row.category_id ?? 'uncategorized'} className="border-b last:border-0">
                                    <td className="py-2">{row.category_name}</td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(row.total_cents, currency)}
                                    </td>
                                    <td className="py-2 text-right text-muted-foreground tabular-nums">
                                        {row.percent.toFixed(1)}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
