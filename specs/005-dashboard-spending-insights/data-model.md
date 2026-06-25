# Phase 1 Data Model: Dashboard Spending Insights

No schema changes. This feature reads existing tables. Below are the existing entities used and the derived/aggregate shapes the dashboard computes.

## Existing entities (read-only)

### Account
- `id`, `user_id`, `name`, `last_four`, `currency`
- Scopes all dashboard data: `accounts.user_id = auth id` (FR-010).

### Transaction
- `id`, `account_id`, `merchant_id?`, `amount_cents` (int), `currency`, `description?`, `posted_at` (date)
- **Spending** = `amount_cents > 0`. Refunds/credits = `amount_cents < 0`, excluded (FR-001a).
- Belongs to `Account`; optional `Merchant`; category via `HasOneThrough` (`merchant.category_id`).

### Merchant
- `id`, `user_id`, `category_id?`, `name`, plus `display_name`/`label` accessors.
- Surfaced as the merchant label in transaction tables.

### Category
- `id`, `user_id`, `name`, `color?`
- Used to group/rank spend. NULL category → "Uncategorized" (FR-005). `color` may seed chart colors.

## Derived aggregate shapes (computed per request)

### PeriodSummary
- `total_cents: int` — sum of spending in the selected period
- `transaction_count: int`
- `previous_total_cents: int` — spending in the immediately preceding equal-length window
- `change_percent: float | null` — null when `previous_total_cents == 0` (FR-012)
- `currency: string`

### CategoryBreakdownRow (ranked desc by `total_cents`)
- `category_id: int | null`
- `category_name: string` — "Uncategorized" when null
- `color: string | null`
- `total_cents: int`
- `percent: float` — share of period total
- Chart consumes top 8 + an "Other" aggregate (FR-013); table consumes all rows.

### TrendPoint (12 entries, chronological, zero-filled)
- `month: string` — e.g. `2026-06`
- `label: string` — e.g. `Jun`
- `total_cents: int`

### TransactionRow (recent = latest 10 by `posted_at`; largest = top 10 by `amount_cents` in period)
- `id: int`
- `merchant_label: string` — merchant display label or fallback (e.g. "Unknown")
- `category_name: string | null`
- `posted_at: string` — ISO date
- `amount_cents: int`
- `currency: string`

## Validation / rules
- All aggregates filtered to the authenticated user's accounts and `amount_cents > 0`.
- Period windows: `this_month`, `last_month`, `last_3_months`; default `this_month`; invalid values fall back to default.
- Empty result sets are valid and drive per-widget empty states (FR-009).
