import { formatMoney, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { InsightsSummary } from './types';

export function InsightsHero({
    summary,
    currency,
    isOver,
}: {
    summary: InsightsSummary;
    currency: string;
    isOver: boolean;
}) {
    return (
        <div className="grid overflow-hidden rounded-xl border shadow-sm md:grid-cols-[1.15fr_1fr]">
            <div
                className={cn(
                    'flex flex-col justify-center p-6',
                    isOver
                        ? 'bg-destructive/10 text-destructive'
                        : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                )}
            >
                <p className="font-mono text-xs tracking-widest uppercase opacity-80">Total overage</p>
                <p className="mt-2 text-5xl font-bold tabular-nums">
                    {formatSignedMoney(summary.total_overage_cents, currency)}
                </p>
                <p className="mt-2 max-w-xs text-sm opacity-90">
                    {isOver ? (
                        <>
                            You spent <b>{formatMoney(summary.total_overage_cents, currency)}</b> more than your
                            combined caps allowed across {summary.categories_over} of {summary.categories_total}{' '}
                            categories.
                        </>
                    ) : (
                        <>Every category with a cap stayed within budget.</>
                    )}
                </p>
            </div>
            <div className="grid grid-cols-2 divide-x divide-y border-l bg-card">
                <Stat
                    label="Categories over cap"
                    value={
                        <span className={cn(isOver && 'text-destructive')}>
                            {summary.categories_over}{' '}
                            <span className="text-sm font-medium text-muted-foreground">
                                / {summary.categories_total}
                            </span>
                        </span>
                    }
                />
                <Stat
                    label={`vs. ${summary.vs_previous_percent !== null ? 'previous period' : 'prior'}`}
                    value={
                        <span className={cn(summary.vs_previous_cents > 0 && 'text-destructive')}>
                            {formatSignedMoney(summary.vs_previous_cents, currency)}
                        </span>
                    }
                    note={
                        summary.vs_previous_percent !== null
                            ? `${summary.vs_previous_percent > 0 ? '+' : ''}${summary.vs_previous_percent}% month over month`
                            : undefined
                    }
                />
                <Stat
                    label="Biggest contributor"
                    value={<span className="text-2xl">{summary.biggest_contributor?.name ?? '—'}</span>}
                    note={
                        summary.biggest_contributor
                            ? `${formatSignedMoney(summary.biggest_contributor.over_cents, currency)} over · ${formatSignedMoney(summary.biggest_contributor.change_cents, currency)} vs prior`
                            : undefined
                    }
                />
                <Stat
                    label="Largest single charge"
                    value={summary.largest_charge ? formatMoney(summary.largest_charge.amount_cents, currency) : '—'}
                    note={
                        summary.largest_charge
                            ? `${summary.largest_charge.merchant}${summary.largest_charge.category ? ` · ${summary.largest_charge.category}` : ''}`
                            : undefined
                    }
                />
            </div>
        </div>
    );
}

function Stat({ label, value, note }: { label: string; value: React.ReactNode; note?: string }) {
    return (
        <div className="flex flex-col justify-center p-5">
            <p className="text-xs text-muted-foreground">{label}</p>
            <div className="mt-1 text-2xl font-semibold tabular-nums">{value}</div>
            {note && <p className="mt-1 text-xs text-muted-foreground">{note}</p>}
        </div>
    );
}
