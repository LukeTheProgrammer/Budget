import { Pencil } from 'lucide-react';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BudgetCategoryRow, DerivedCategory, SortMode } from '@/types';
import { ACCENT, monogram, STATUS_COLOR } from './budget-shared';

export function CategoryRail({
    categories,
    currency,
    selectedId,
    sortMode,
    onSort,
    onSelect,
    onEdit,
}: {
    categories: DerivedCategory[];
    currency: string;
    selectedId: number | null;
    sortMode: SortMode;
    onSort: (mode: SortMode) => void;
    onSelect: (id: number) => void;
    onEdit: (row: BudgetCategoryRow) => void;
}) {
    const sortOptions: { value: SortMode; label: string }[] = [
        { value: 'budget', label: 'Budget' },
        { value: 'spent', label: 'Spent' },
        { value: 'alpha', label: 'A-Z' },
    ];

    return (
        <aside className="flex min-h-0 flex-col overflow-hidden rounded-xl border bg-card">
            <div className="flex items-center justify-between px-4 pt-4 pb-3">
                <span className="font-mono text-[11px] tracking-[0.13em] text-muted-foreground uppercase">
                    Categories · {categories.length}
                </span>
                <div className="flex gap-0.5 rounded-lg bg-muted p-0.75">
                    {sortOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => onSort(option.value)}
                            className={cn(
                                'rounded-md px-2.5 py-1 text-xs text-muted-foreground transition-colors',
                                sortMode === option.value && 'bg-background font-medium text-foreground shadow-sm',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="flex-1 space-y-0.5 overflow-auto px-2 pb-2.5">
                {categories.map((item) => (
                    <CategoryRow
                        key={item.row.id}
                        item={item}
                        currency={currency}
                        selected={item.row.id === selectedId}
                        onSelect={() => onSelect(item.row.id)}
                        onEdit={() => onEdit(item.row)}
                    />
                ))}
            </div>
        </aside>
    );
}

function CategoryRow({
    item,
    currency,
    selected,
    onSelect,
    onEdit,
}: {
    item: DerivedCategory;
    currency: string;
    selected: boolean;
    onSelect: () => void;
    onEdit: () => void;
}) {
    const { row, spent, budgeted, percent, status } = item;
    const color = status ? STATUS_COLOR[status] : null;
    const barWidth = percent === null ? 0 : Math.min(percent, 100);

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'group relative grid w-full grid-cols-[34px_1fr_auto] items-center gap-x-3 gap-y-2 rounded-[10px] px-3 py-3 pl-3.5 text-left transition-colors hover:bg-muted',
                selected && 'bg-muted',
            )}
        >
            {selected && (
                <span
                    className="absolute inset-y-3.5 left-0.75 w-0.75 rounded-full"
                    style={{ backgroundColor: ACCENT }}
                />
            )}

            <span
                className={cn(
                    'row-span-2 grid size-8.5 place-items-center rounded-[9px] border bg-card font-mono text-sm text-muted-foreground',
                    selected && 'text-foreground',
                )}
                style={
                    selected
                        ? {
                              backgroundColor: STATUS_COLOR.ok.bg,
                              borderColor: ACCENT,
                              color: STATUS_COLOR.ok.text,
                          }
                        : row.color
                          ? { borderColor: row.color }
                          : undefined
                }
            >
                {monogram(row.name)}
            </span>

            <span className="col-start-2 row-start-1 truncate text-[14.5px] font-semibold tracking-[-0.01em]">
                {row.name}
            </span>

            <span className="col-start-3 row-start-1 justify-self-end text-[12.5px] text-muted-foreground tabular-nums group-hover:invisible">
                {budgeted === null ? (
                    'No budget'
                ) : (
                    <>
                        <b className="font-semibold text-foreground">{formatMoney(spent, currency)}</b> /{' '}
                        {formatMoney(budgeted, currency)}
                    </>
                )}
            </span>

            <span
                role="button"
                tabIndex={-1}
                onClick={(event) => {
                    event.stopPropagation();
                    onEdit();
                }}
                className="invisible col-start-3 row-start-1 grid size-6 cursor-pointer place-items-center justify-self-end rounded-[7px] border bg-card text-muted-foreground group-hover:visible hover:border-foreground hover:bg-foreground hover:text-background"
                aria-label={`Edit budget for ${row.name}`}
            >
                <Pencil className="size-3" />
            </span>

            <span className="col-span-2 col-start-2 row-start-2 flex items-center gap-2.5">
                <span className="h-1.25 flex-1 overflow-hidden rounded-full bg-card ring-1 ring-border ring-inset">
                    {color && (
                        <span
                            className="block h-full rounded-full"
                            style={{
                                width: `${barWidth}%`,
                                backgroundColor: color.fill,
                            }}
                        />
                    )}
                </span>
                <span className="min-w-8 text-right font-mono text-[11px] text-muted-foreground">
                    {percent === null ? '—' : `${percent}%`}
                </span>
            </span>
        </button>
    );
}
