import { useState } from 'react';
import type { FlowType, FlowTypeOption } from '@/components/transactions/transaction-flow-type';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { FilterOption, TransactionFilters } from '@/pages/transactions';

const ALL = 'all';

type DraftFilters = {
    start: string;
    end: string;
    account_id: string;
    merchant_id: string;
    category_id: string;
    min_amount: string;
    max_amount: string;
    flow_type: FlowType[];
};

function toDraft(filters: TransactionFilters): DraftFilters {
    return {
        start: filters.start ?? '',
        end: filters.end ?? '',
        account_id: filters.account_id !== null ? String(filters.account_id) : ALL,
        merchant_id: filters.merchant_id !== null ? String(filters.merchant_id) : ALL,
        category_id: filters.category_id !== null ? String(filters.category_id) : ALL,
        min_amount: filters.min_amount !== null ? String(filters.min_amount) : '',
        max_amount: filters.max_amount !== null ? String(filters.max_amount) : '',
        flow_type: filters.flow_type,
    };
}

function toFilters(draft: DraftFilters): TransactionFilters {
    const toNumber = (value: string): number | null => {
        const trimmed = value.trim();

        if (trimmed === '') {
            return null;
        }

        const parsed = Number(trimmed);

        return Number.isNaN(parsed) ? null : parsed;
    };

    return {
        start: draft.start.trim() === '' ? null : draft.start,
        end: draft.end.trim() === '' ? null : draft.end,
        account_id: draft.account_id === ALL ? null : Number(draft.account_id),
        merchant_id: draft.merchant_id === ALL ? null : Number(draft.merchant_id),
        category_id: draft.category_id === ALL ? null : Number(draft.category_id),
        min_amount: toNumber(draft.min_amount),
        max_amount: toNumber(draft.max_amount),
        flow_type: draft.flow_type,
    };
}

const EMPTY_FILTERS: TransactionFilters = {
    start: null,
    end: null,
    account_id: null,
    merchant_id: null,
    category_id: null,
    min_amount: null,
    max_amount: null,
    flow_type: [],
};

export function TransactionFilters({
    filters,
    accountOptions,
    merchantOptions,
    categoryOptions,
    flowTypeOptions,
    onChange,
}: {
    filters: TransactionFilters;
    accountOptions: FilterOption[];
    merchantOptions: FilterOption[];
    categoryOptions: FilterOption[];
    flowTypeOptions: FlowTypeOption[];
    onChange: (filters: TransactionFilters) => void;
}) {
    const [draft, setDraft] = useState<DraftFilters>(() => toDraft(filters));

    const update = (patch: Partial<DraftFilters>): void => {
        setDraft((current) => ({ ...current, ...patch }));
    };

    const hasFilters =
        filters.start !== null ||
        filters.end !== null ||
        filters.account_id !== null ||
        filters.merchant_id !== null ||
        filters.category_id !== null ||
        filters.min_amount !== null ||
        filters.max_amount !== null ||
        filters.flow_type.length > 0;

    const apply = (): void => {
        onChange(toFilters(draft));
    };

    const clear = (): void => {
        setDraft(toDraft(EMPTY_FILTERS));
        onChange(EMPTY_FILTERS);
    };

    return (
        <form
            className="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4"
            onSubmit={(event) => {
                event.preventDefault();
                apply();
            }}
        >
            <div className="grid gap-1.5">
                <div>
                    <Label htmlFor="filter-start">From</Label>
                    <Input
                        id="filter-start"
                        type="date"
                        value={draft.start}
                        onChange={(event) => update({ start: event.target.value })}
                    />
                </div>
                <div>
                    <Label htmlFor="filter-end">To</Label>
                    <Input
                        id="filter-end"
                        type="date"
                        value={draft.end}
                        onChange={(event) => update({ end: event.target.value })}
                    />
                </div>
            </div>

            <div className="grid gap-1.5">
                <div>
                    <Label htmlFor="filter-account">Account</Label>
                    <Combobox
                        id="filter-account"
                        value={draft.account_id}
                        onValueChange={(value) => update({ account_id: value })}
                        placeholder="All accounts"
                        searchPlaceholder="Search accounts..."
                        emptyText="No accounts found."
                        options={[
                            { value: ALL, label: 'All accounts' },
                            ...accountOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        ]}
                    />
                </div>
                <div>
                    <Label htmlFor="filter-merchant">Merchant</Label>
                    <Combobox
                        id="filter-merchant"
                        value={draft.merchant_id}
                        onValueChange={(value) => update({ merchant_id: value })}
                        placeholder="All merchants"
                        searchPlaceholder="Search merchants..."
                        emptyText="No merchants found."
                        options={[
                            { value: ALL, label: 'All merchants' },
                            ...merchantOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        ]}
                    />
                </div>
                <div>
                    <Label htmlFor="filter-category">Category</Label>
                    <Select value={draft.category_id} onValueChange={(value) => update({ category_id: value })}>
                        <SelectTrigger id="filter-category" className="w-full">
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All categories</SelectItem>
                            {categoryOptions.map((option) => (
                                <SelectItem key={option.id} value={String(option.id)}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="grid gap-1.5">
                <div>
                    <Label htmlFor="filter-min">Min amount</Label>
                    <Input
                        id="filter-min"
                        type="number"
                        step="0.01"
                        min="0"
                        inputMode="decimal"
                        value={draft.min_amount}
                        onChange={(event) => update({ min_amount: event.target.value })}
                    />
                </div>
                <div>
                    <Label htmlFor="filter-max">Max amount</Label>
                    <Input
                        id="filter-max"
                        type="number"
                        step="0.01"
                        min="0"
                        inputMode="decimal"
                        value={draft.max_amount}
                        onChange={(event) => update({ max_amount: event.target.value })}
                    />
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="filter-flow-type">Type</Label>
                <ToggleGroup
                    id="filter-flow-type"
                    type="multiple"
                    variant="outline"
                    size="sm"
                    className="justify-start"
                    value={draft.flow_type}
                    onValueChange={(value: string[]) => update({ flow_type: value as FlowType[] })}
                >
                    {flowTypeOptions.map((option) => (
                        <ToggleGroupItem key={option.value} value={option.value}>
                            {option.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <p className="text-xs text-muted-foreground">Selecting none lists every type.</p>
            </div>

            <div className="col-span-full flex items-end justify-end">
                <div className="gap-2 sm:col-span-2 lg:col-span-3">
                    <Button type="submit">Apply filters</Button>
                    {hasFilters && (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear filters
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}
