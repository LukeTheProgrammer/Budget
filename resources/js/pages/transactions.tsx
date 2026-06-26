import { Head, Link, router } from '@inertiajs/react';
import { create as uploadCreate } from '@/actions/App/Http/Controllers/Transactions/UploadController';
import { TransactionFilters as TransactionFiltersBar } from '@/components/transactions/transaction-filters';
import { TransactionTags } from '@/components/transactions/transaction-tags';
import type { Tag } from '@/components/transactions/transaction-tags';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as transactionsIndex } from '@/routes/transactions';

export type TransactionRow = {
    id: number;
    posted_at: string;
    merchant_label: string;
    category_name: string | null;
    description: string | null;
    amount_cents: number;
    currency: string;
    tags: Tag[];
};

export type FilterOption = {
    id: number;
    label: string;
};

export type TransactionFilters = {
    start: string | null;
    end: string | null;
    merchant_id: number | null;
    category_id: number | null;
    min_amount: number | null;
    max_amount: number | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};

export type TransactionsPageProps = {
    transactions: TransactionRow[];
    pagination: Pagination;
    filters: TransactionFilters;
    merchant_options: FilterOption[];
    category_options: FilterOption[];
    available_tags: Tag[];
    currency: string;
};

function formatDate(isoDate: string): string {
    return new Date(isoDate).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function buildQuery(filters: TransactionFilters): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.start !== null) {
        query.start = filters.start;
    }

    if (filters.end !== null) {
        query.end = filters.end;
    }

    if (filters.merchant_id !== null) {
        query.merchant_id = String(filters.merchant_id);
    }

    if (filters.category_id !== null) {
        query.category_id = String(filters.category_id);
    }

    if (filters.min_amount !== null) {
        query.min_amount = String(filters.min_amount);
    }

    if (filters.max_amount !== null) {
        query.max_amount = String(filters.max_amount);
    }

    return query;
}

export default function TransactionsIndex({
    transactions,
    pagination,
    filters,
    merchant_options,
    category_options,
    available_tags,
}: TransactionsPageProps) {
    const applyFilters = (next: TransactionFilters): void => {
        // Omit `page` so changing a filter resets to the first page (FR-010a),
        // and rewrite the URL in place without a full navigation (FR-012).
        router.get(transactionsIndex.url(), buildQuery(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Transactions</h1>
                        <p className="text-sm text-muted-foreground">
                            {pagination.total.toLocaleString()}{' '}
                            {pagination.total === 1
                                ? 'transaction'
                                : 'transactions'}
                        </p>
                    </div>

                    <Button asChild size="sm">
                        <Link href={uploadCreate().url}>Upload file</Link>
                    </Button>
                </div>

                <TransactionFiltersBar
                    key={JSON.stringify(filters)}
                    filters={filters}
                    merchantOptions={merchant_options}
                    categoryOptions={category_options}
                    onChange={applyFilters}
                />

                {transactions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-12 text-center">
                        <p className="font-medium">No transactions found</p>
                        <p className="text-sm text-muted-foreground">
                            Import some transactions to get started.
                        </p>
                    </div>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Merchant</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Tags</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(row.posted_at)}
                                        </TableCell>
                                        <TableCell>
                                            {row.merchant_label}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {row.category_name ??
                                                'Uncategorized'}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-muted-foreground">
                                            {row.description ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <TransactionTags
                                                transactionId={row.id}
                                                tags={row.tags}
                                                availableTags={available_tags}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(
                                                row.amount_cents,
                                                row.currency,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {pagination.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {pagination.links.map((link, index) => {
                            const className = cn(
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground',
                                !link.url &&
                                    'pointer-events-none text-muted-foreground opacity-50',
                            );

                            if (!link.url) {
                                return (
                                    <span
                                        key={index}
                                        className={className}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                );
                            }

                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={className}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        })}
                    </nav>
                )}
            </div>
        </>
    );
}
