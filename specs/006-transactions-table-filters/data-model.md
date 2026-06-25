# Phase 1 Data Model: Transactions Table with Filters

No schema changes. This feature reads existing tables. This document records the entities involved and the filter/validation rules applied at query time.

## Entities (existing, read-only)

### Transaction
Source of the table rows.

| Field | Type | Notes |
|-------|------|-------|
| id | int | Row key |
| account_id | int | Ownership via `accounts.user_id` |
| merchant_id | int \| null | Nullable; null shows as "Unknown" / unmatched by merchant filter |
| amount_cents | int | Positive = outflow; displayed/filtered in major units |
| currency | string | ISO currency code for display |
| description | string \| null | Shown in table |
| posted_at | date | Default sort key (desc); target of date-range filter |

Relationships: `belongsTo Account`, `belongsTo Merchant`, category via `merchant.category`.

### Merchant
Filter option + display label.

| Field | Type | Notes |
|-------|------|-------|
| id | int | Filter value (`merchant_id`) |
| user_id | int | Scopes filter options & `exists` validation |
| name / label | string | Display in option list and table |
| category_id | int \| null | Links a transaction to its category |

### Category
Filter option + display label.

| Field | Type | Notes |
|-------|------|-------|
| id | int | Filter value (`category_id`) |
| user_id | int | Scopes filter options & `exists` validation |
| name | string | Display in option list and table |
| color | string \| null | Optional display accent |

### Account
Ownership boundary only; `accounts.user_id` constrains every query. Provides display currency.

## Filter Parameters & Validation Rules

Validated by `TransactionFilterRequest`. All are `nullable`; an invalid value is treated as "not applied" (FR-013), not a hard error.

| Param | Rule | Applied as |
|-------|------|-----------|
| `start` | `nullable date` | `posted_at >= start` (inclusive) |
| `end` | `nullable date` | `posted_at <= end` (inclusive) |
| `merchant_id` | `nullable integer exists:merchants,id` (scoped to user) | `merchant_id = ?` |
| `category_id` | `nullable integer exists:categories,id` (scoped to user) | `merchant.category_id = ?` |
| `min_amount` | `nullable numeric min:0` | `amount_cents >= round(min_amount*100)` |
| `max_amount` | `nullable numeric min:0` | `amount_cents <= round(max_amount*100)` |
| `page` | `nullable integer min:1` | Paginator page; reset to 1 on filter change |

### Cross-field behavior (edge cases)
- `start > end` → empty range, zero results (no error).
- `min_amount > max_amount` → empty range, zero results (no error).
- `merchant_id` / `category_id` not owned by user → fails `exists`, ignored → no rows leaked.

## Query Scope

A new `#[Scope] filter(Builder $query, int $userId, array $filters)` on `Transaction`:
1. Join `accounts`, constrain `accounts.user_id = $userId` (ownership; SC-005).
2. Eager-load `merchant.category` for display.
3. Conditionally apply each provided filter above.
4. Order `posted_at desc, id desc`.
Caller paginates the scoped query (`->paginate(50)->withQueryString()`).

## Display Row Shape (props)
Each paginated row is mapped (as in `DashboardController::transactionRows`) to:
`{ id, posted_at (Y-m-d), merchant_label, category_name|null, description|null, amount_cents, currency }`.
