# Contract: Dashboard page (Inertia)

## Route
`GET /dashboard?period={this_month|last_month|last_3_months}` → `DashboardController@index` → `Inertia::render('dashboard', props)`

- Named route `dashboard` (existing). Middleware: `auth`, `verified`.
- `period` query param optional; default `this_month`; unknown values fall back to default.
- Wayfinder helper `dashboard()` used by the frontend period selector for navigation.

## Page props (TypeScript shape)

```ts
type Money = { amount_cents: number; currency: string };

type DashboardProps = {
  period: 'this_month' | 'last_month' | 'last_3_months';
  period_label: string;                 // e.g. "June 2026"
  currency: string;                     // assumed single currency this iteration

  summary: {
    total_cents: number;
    transaction_count: number;
    previous_total_cents: number;
    change_percent: number | null;      // null = no comparable prior period (FR-012)
  };

  categories: Array<{                   // ranked desc by total_cents; ALL categories (table)
    category_id: number | null;
    category_name: string;              // "Uncategorized" when null
    color: string | null;
    total_cents: number;
    percent: number;                    // share of period total
  }>;

  trend: Array<{                        // exactly 12, chronological, zero-filled (FR-006)
    month: string;                      // "2026-06"
    label: string;                      // "Jun"
    total_cents: number;
  }>;

  recent_transactions: Array<TransactionRow>;  // latest 10 by posted_at
  largest_transactions: Array<TransactionRow>; // top 10 by amount_cents within period
};

type TransactionRow = {
  id: number;
  merchant_label: string;
  category_name: string | null;
  posted_at: string;                    // ISO date
  amount_cents: number;
  currency: string;
};
```

## Behavior guarantees
- All figures derive only from the authenticated user's accounts and `amount_cents > 0` (FR-010, FR-001a).
- `summary.total_cents` equals the sum of the selected period's spending transactions (SC-003).
- Empty arrays / zero totals are valid; the page renders per-widget empty states (FR-009).
- The chart consolidates categories beyond the top 8 into an "Other" entry client-side; the table renders the full `categories` array (FR-013).
- No mutations: the page is read-only (no POST/PATCH/DELETE).
