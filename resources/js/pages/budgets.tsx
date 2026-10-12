import { Head, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { deriveCategory } from '@/components/budgets/budget-shared';
import { CategoryDetail } from '@/components/budgets/category-detail';
import { CategoryRail } from '@/components/budgets/category-rail';
import { EditBudgetDialog } from '@/components/budgets/edit-budget-dialog';
import { TotalsBar } from '@/components/budgets/totals-bar';
import { Button } from '@/components/ui/button';
import { index as budgetsIndex, recalculate } from '@/routes/budgets';
import type { BudgetCategoryRow, BudgetsPageProps, SortMode } from '@/types';

export default function Budgets({ currency, months, pacing, categories }: BudgetsPageProps) {
    const period = usePage().props.period;

    const derived = useMemo(() => categories.map((row) => deriveCategory(row, months)), [categories, months]);

    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [sortMode, setSortMode] = useState<SortMode>('budget');
    const [editing, setEditing] = useState<BudgetCategoryRow | null>(null);
    const [recalculating, setRecalculating] = useState(false);

    const recalculateBudgets = () => {
        router.post(
            recalculate(),
            {},
            {
                preserveScroll: true,
                onStart: () => setRecalculating(true),
                onFinish: () => setRecalculating(false),
            },
        );
    };

    const sorted = useMemo(() => {
        const list = [...derived];

        if (sortMode === 'budget') {
            list.sort((a, b) => (b.budgeted ?? -1) - (a.budgeted ?? -1));
        } else if (sortMode === 'spent') {
            list.sort((a, b) => (b.spent ?? -1) - (a.spent ?? -1));
        } else {
            list.sort((a, b) => a.row.name.localeCompare(b.row.name));
        }

        return list;
    }, [derived, sortMode]);

    const totals = useMemo(() => {
        let budgeted = 0;
        let spent = 0;

        for (const item of derived) {
            spent += item.spent;

            if (item.budgeted !== null) {
                budgeted += item.budgeted;
            }
        }

        return {
            budgeted,
            spent,
            remaining: budgeted - spent,
            percent: budgeted > 0 ? Math.round((spent / budgeted) * 100) : 0,
        };
    }, [derived]);

    const selected =
        (selectedId !== null ? derived.find((item) => item.row.id === selectedId) : undefined) ?? sorted[0] ?? null;

    return (
        <>
            <Head title="Budgets" />
            <div className="flex h-[calc(100svh-4rem)] flex-col gap-5 overflow-hidden p-4">
                <header className="grid items-center gap-x-8 gap-y-4 lg:grid-cols-[33%_1fr]">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">Budgets</h1>
                            <p className="text-sm text-muted-foreground">{period.label}</p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={recalculating || derived.length === 0}
                            onClick={recalculateBudgets}
                            title="Reset every budget to its monthly average spend"
                        >
                            <RefreshCw className={recalculating ? 'animate-spin' : undefined} />
                            Recalculate
                        </Button>
                    </div>

                    <div>{totals.budgeted > 0 && <TotalsBar totals={totals} currency={currency} />}</div>
                </header>

                {derived.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-12 text-center">
                        <p className="font-medium">No categories yet</p>
                        <p className="text-sm text-muted-foreground">
                            Assign categories to your merchants first, then set a monthly budget for each.
                        </p>
                    </div>
                ) : (
                    <div className="grid min-h-0 flex-1 gap-5 lg:grid-cols-[33%_1fr]">
                        <CategoryRail
                            categories={sorted}
                            currency={currency}
                            selectedId={selected?.row.id ?? null}
                            sortMode={sortMode}
                            onSort={setSortMode}
                            onSelect={setSelectedId}
                            onEdit={setEditing}
                        />

                        {selected && (
                            <CategoryDetail
                                category={selected}
                                currency={currency}
                                pacing={pacing}
                                onEdit={() => setEditing(selected.row)}
                            />
                        )}
                    </div>
                )}
            </div>

            <EditBudgetDialog category={editing} currency={currency} months={months} onClose={() => setEditing(null)} />
        </>
    );
}

Budgets.layout = {
    breadcrumbs: [
        {
            title: 'Budgets',
            href: budgetsIndex(),
        },
    ],
};
