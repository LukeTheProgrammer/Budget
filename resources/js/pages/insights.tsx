import { Head, usePage } from '@inertiajs/react';
import { InsightsHero } from '@/components/insights/insights-hero';
import { OverCategories } from '@/components/insights/over-categories';
import { OverageComposition } from '@/components/insights/overage-composition';
import { SpendingChanges } from '@/components/insights/spending-changes';
import type { CompositionSlice, InsightsSummary, OverCategory, SpendingChangesData } from '@/components/insights/types';
import { insights } from '@/routes';

type InsightsProps = {
    currency: string;
    previous_label: string;
    has_budgets: boolean;
    summary: InsightsSummary;
    over_categories: OverCategory[];
    composition: CompositionSlice[];
    changes: SpendingChangesData;
};

export default function Insights({
    currency,
    previous_label,
    has_budgets,
    summary,
    over_categories,
    composition,
    changes,
}: InsightsProps) {
    const period = usePage().props.period;
    const isOver = summary.total_overage_cents > 0;

    return (
        <>
            <Head title="Insights" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <p className="font-mono text-xs tracking-widest text-muted-foreground uppercase">Monthly review</p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        {isOver ? `Why ${period.label} went over budget` : `${period.label} stayed within budget`}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        A breakdown of what pushed spending past your caps, and what changed since {previous_label}.
                    </p>
                </div>

                {!has_budgets ? (
                    <EmptyState />
                ) : (
                    <>
                        <InsightsHero summary={summary} currency={currency} isOver={isOver} />
                        <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                            <OverCategories rows={over_categories} currency={currency} />
                            <OverageComposition
                                slices={composition}
                                totalCents={summary.total_overage_cents}
                                currency={currency}
                            />
                        </div>
                        <SpendingChanges
                            changes={changes}
                            previousLabel={previous_label}
                            currentLabel={period.label}
                            currency={currency}
                        />
                    </>
                )}
            </div>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-12 text-center">
            <p className="font-medium">No budgets set</p>
            <p className="text-sm text-muted-foreground">
                Set monthly caps on your categories to see where spending ran over and what changed.
            </p>
        </div>
    );
}

Insights.layout = {
    breadcrumbs: [
        {
            title: 'Insights',
            href: insights(),
        },
    ],
};
