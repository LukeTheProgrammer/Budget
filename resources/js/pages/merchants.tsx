import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { EditMerchantDialog } from '@/components/merchants/edit-merchant-dialog';
import { GroupMerchantsDialog } from '@/components/merchants/group-merchants-dialog';
import { MerchantTabs } from '@/components/merchants/merchant-tabs';
import { MerchantsTable } from '@/components/merchants/merchants-table';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { index } from '@/routes/merchants';
import type {
    BreadcrumbItem,
    Merchant,
    MerchantCategory,
    MerchantFilters,
    MerchantTab,
    MerchantTag,
    Pagination,
} from '@/types';

type MerchantsPageProps = {
    merchants: Merchant[];
    pagination: Pagination;
    filters: MerchantFilters;
    review_count: number;
    available_tags: MerchantTag[];
    available_categories: MerchantCategory[];
    include_non_expense: boolean;
};

/**
 * The message shown in place of the table, which depends on why the page came
 * back empty: an unmatched search, a cleared review queue, or no data at all.
 */
function emptyMessage(filters: MerchantFilters): string {
    if (filters.search !== '') {
        return `No merchants match “${filters.search}”.`;
    }

    if (filters.tab === 'review') {
        return 'Nothing to review — every merchant is confirmed.';
    }

    return 'No merchants yet. Import some transactions to get started.';
}

export default function MerchantsIndex({
    merchants,
    pagination,
    filters,
    review_count: reviewCount,
    available_tags,
    available_categories,
    include_non_expense: includeNonExpense,
}: MerchantsPageProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);

    const editingMerchant = merchants.find((merchant) => merchant.id === editingId) ?? null;

    const debouncedSearch = useDebouncedValue(search);

    /**
     * Reload the list with new filters. Any filter change drops the page number,
     * since an old page rarely means anything against a new result set.
     */
    const reload = (changes: Partial<MerchantFilters> & { include_non_expense?: boolean }) => {
        router.get(
            index().url,
            {
                ...filters,
                ...(includeNonExpense ? { include_non_expense: true } : {}),
                ...changes,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['merchants', 'pagination', 'filters', 'include_non_expense'],
            },
        );
    };

    // Fetch once the debounced term settles on something new. The ref stops the
    // effect from re-firing on the reload it just triggered, which would loop.
    const requestedSearch = useRef(filters.search);

    useEffect(() => {
        if (debouncedSearch === requestedSearch.current) {
            return;
        }

        requestedSearch.current = debouncedSearch;
        reload({ search: debouncedSearch });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch]);

    const selectTab = (tab: MerchantTab) => {
        setSelectedIds([]);
        reload({ tab });
    };

    const toggle = (id: number, selected: boolean) => {
        setSelectedIds((current) => (selected ? [...current, id] : current.filter((value) => value !== id)));
    };

    return (
        <>
            <Head title="Merchants" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Merchants</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage how merchants appear and group store variants together.
                        </p>
                    </div>
                    <div className="flex items-end justify-end gap-2">
                        {selectedIds.length >= 2 && (
                            <Button onClick={() => setDialogOpen(true)}>Group {selectedIds.length} merchants</Button>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="include-non-expense"
                        checked={includeNonExpense}
                        onCheckedChange={(checked) => reload({ include_non_expense: checked === true })}
                    />
                    <Label htmlFor="include-non-expense" className="text-sm font-normal text-muted-foreground">
                        Show merchants with no spending (payroll, transfers)
                    </Label>
                </div>

                <MerchantTabs tab={filters.tab} reviewCount={reviewCount} onSelect={selectTab} onSearch={setSearch} />

                {merchants.length === 0 ? (
                    <div className="rounded-xl border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        {emptyMessage(filters)}
                    </div>
                ) : (
                    <MerchantsTable
                        merchants={merchants}
                        selectedIds={selectedIds}
                        onSelectedChange={toggle}
                        onEdit={setEditingId}
                    />
                )}

                {pagination.total > 0 && (
                    <p className="text-center text-sm text-muted-foreground">
                        {pagination.total.toLocaleString()} {pagination.total === 1 ? 'merchant' : 'merchants'}
                    </p>
                )}

                <PaginationNav pagination={pagination} />
            </div>

            {dialogOpen && (
                <GroupMerchantsDialog
                    merchants={merchants}
                    selectedIds={selectedIds}
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    onGrouped={() => setSelectedIds([])}
                />
            )}

            {editingMerchant && (
                <EditMerchantDialog
                    merchant={editingMerchant}
                    availableTags={available_tags}
                    availableCategories={available_categories}
                    open={editingId !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingId(null);
                        }
                    }}
                />
            )}
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Merchants',
        href: index(),
    },
];

MerchantsIndex.layout = {
    breadcrumbs,
};
