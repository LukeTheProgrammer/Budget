# Contract: Transactions Index Page

The single UI contract this feature exposes: the `GET /transactions` Inertia endpoint, its query-parameter input, and its page-prop output.

## Route

- **Method/Path**: `GET /transactions`
- **Name**: `transactions.index`
- **Middleware**: `auth`, `verified`
- **Controller**: `App\Http\Controllers\Transactions\TransactionController@index`
- **Wayfinder**: imported on the frontend from `@/routes/transactions` (`index`) / `@/actions/.../TransactionController`.

## Request — Query Parameters

All optional. Invalid values are ignored (filter not applied), never a hard error.

| Param | Type | Example | Meaning |
|-------|------|---------|---------|
| `start` | date (`Y-m-d`) | `2026-01-01` | Inclusive lower bound on `posted_at` |
| `end` | date (`Y-m-d`) | `2026-03-31` | Inclusive upper bound on `posted_at` |
| `merchant_id` | int | `42` | Exact merchant (must belong to user) |
| `category_id` | int | `7` | Category via merchant (must belong to user) |
| `min_amount` | number (major units) | `10.00` | Inclusive lower bound |
| `max_amount` | number (major units) | `250` | Inclusive upper bound |
| `page` | int ≥ 1 | `2` | Pagination page; reset to 1 when any filter changes |

## Response — Inertia Page `transactions/index`

```ts
type TransactionRow = {
    id: number;
    posted_at: string;          // 'YYYY-MM-DD'
    merchant_label: string;     // 'Unknown' when no merchant
    category_name: string | null;
    description: string | null;
    amount_cents: number;
    currency: string;           // ISO code
};

type FilterOption = { id: number; label: string };

type TransactionFilters = {
    start: string | null;
    end: string | null;
    merchant_id: number | null;
    category_id: number | null;
    min_amount: number | null;  // major units
    max_amount: number | null;  // major units
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;           // 50
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type TransactionsPageProps = {
    transactions: TransactionRow[];
    pagination: Pagination;
    filters: TransactionFilters;          // echoes the applied (validated) filters
    merchant_options: FilterOption[];     // user's merchants, for the merchant select
    category_options: FilterOption[];     // user's categories, for the category select
    currency: string;                     // default display currency
};
```

## Behavior Contract

1. With no params: returns page 1 of all the user's transactions, `posted_at desc, id desc` (FR-003).
2. Each provided + valid filter narrows results; all active filters combine with AND (FR-009).
3. `filters` in the response always equals the validated input echoed back, so the controls re-hydrate exactly (FR-011).
4. Changing any filter on the client issues a fresh `GET transactions.index` visit (`preserveState`, `preserveScroll`, `replace`) with the new params and `page` reset to 1 (FR-010a, FR-012).
5. `merchant_options` / `category_options` contain only the authenticated user's records (FR-002, SC-005).
6. Empty result set → `transactions: []`; the page shows an empty state with filters still editable (FR-014).
