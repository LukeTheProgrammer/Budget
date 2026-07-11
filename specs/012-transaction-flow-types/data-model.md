# Phase 1 Data Model: Deposits and Transfers

## Enum: `App\Enums\FlowType`

Backed string enum, mirroring the shape of `App\Enums\AccountType` (a `label()` method and a static `options()` for select inputs).

| Case | Value | Sign of `amount_cents` | Counts toward spending | Counts toward income |
|------|-------|------------------------|------------------------|----------------------|
| `Expense` | `expense` | positive (outflow) | yes | no |
| `Refund` | `refund` | negative (inflow) | yes, as a negative — reduces the category/merchant total | no |
| `Income` | `income` | negative (inflow) | no | yes |
| `Transfer` | `transfer` | either | no | no |

Helper used by every aggregation so the rule exists in exactly one place:

- `FlowType::spendingCases(): array` → `[Expense, Refund]`

## Enum: `App\Enums\FlowTypeSource`

| Case | Value | Meaning |
|------|-------|---------|
| `Auto` | `auto` | Assigned by `FlowTypeClassifier`. May be reclassified on re-import or backfill. |
| `User` | `user` | Set explicitly by the user. Never overwritten by automatic classification (FR-011). |

## Table changes

### `transactions` (migration: add columns)

| Column | Type | Notes |
|--------|------|-------|
| `flow_type` | `enum('expense','income','transfer','refund')`, NOT NULL, default `'expense'` | The default makes the migration valid on existing rows; the backfill command then corrects them. |
| `flow_type_source` | `enum('auto','user')`, NOT NULL, default `'auto'` | Guards user corrections against re-import (FR-011). |
| `transfer_pair_id` | `foreignId` nullable, self-referencing `transactions.id`, `nullOnDelete` | The other leg of an internal transfer (FR-008). Null for unpaired transfers and every non-transfer row. |

Indexes:
- `index(['account_id', 'flow_type', 'posted_at'])` — serves the per-account, per-period spending and income queries.
- `index(['flow_type', 'posted_at'])` — serves the cross-account dashboard aggregates.

Model changes (`app/Models/Transaction.php`):
- Casts: `flow_type => FlowType::class`, `flow_type_source => FlowTypeSource::class`.
- `#[Fillable]` gains `flow_type`, `flow_type_source`, `transfer_pair_id`.
- Relation: `transferPair(): BelongsTo<Transaction>`.
- Scopes `spendingByCategory`, `spendingSummary`, `monthlySpendingTrend` swap `where('amount_cents', '>', 0)` for `whereIn('flow_type', FlowType::spendingCases())`.
- Scopes `recentSpending`, `largestSpending` swap it for `where('flow_type', FlowType::Expense)` (a refund is not a largest expense — see research.md).
- New scope `incomeSummary(int $userId, $start, $end)` → `-SUM(amount_cents)` over `flow_type = 'income'`.
- Scope `filter()` gains a `flow_types` key: `whereIn('flow_type', $filters['flow_types'])`.

### `merchants` (migration: add column)

| Column | Type | Notes |
|--------|------|-------|
| `default_flow_type` | `enum('expense','income','transfer','refund')` nullable | The reusable classification rule (FR-010). Null means "no rule — use the heuristics". Written whenever the user corrects a transaction of this merchant. |

Model changes (`app/Models/Merchant.php`):
- Cast `default_flow_type => FlowType::class`; add to `#[Fillable]`.
- New scope `withExpenseActivity()` — merchants having at least one transaction with `flow_type IN (expense, refund)`. Drives the default Merchants-page listing (FR-015).

## Validation rules

- `flow_type` must be one of the four enum values (`Rule::enum(FlowType::class)` in `UpdateFlowTypeRequest`).
- A transaction may only be updated by the user who owns its account (authorize via the account relation, matching the existing `TransactionTagController` pattern).
- `transfer_pair_id`, when set, must reference a transaction belonging to a *different* account of the *same* user, with the opposite sign, the same absolute amount and currency, and a `posted_at` within 3 days. Enforced by `TransferPairer`, which is the only writer of this column.
- Pairing is symmetric and exclusive: if A pairs with B, then B pairs with A, and neither pairs with anything else.

## State transitions

```text
                    import / sync / backfill
                              │
                              ▼
                   FlowTypeClassifier::classify()
                              │
        ┌─────────────────────┴──────────────────────┐
        │ merchant.default_flow_type set?            │
        │   yes → use it                             │
        │   no  → sign + description + account type  │
        └─────────────────────┬──────────────────────┘
                              ▼
              flow_type = X, flow_type_source = auto
                              │
                user edits the flow type (PATCH)
                              │
                              ▼
              flow_type = Y, flow_type_source = user
              merchant.default_flow_type = Y  (unless "this transaction only")
                              │
                    later re-import of the same row
                              │
                              ▼
       flow_type_source = user → classification SKIPPED, Y preserved
```

`TransactionRowStore::store()` currently calls `updateOrCreate` with the full attribute set. It must exclude `flow_type` / `flow_type_source` from the update path when the existing row's source is `user`; the simplest form is to classify only for newly created rows and for existing rows whose source is `auto`.

## Entity relationships (unchanged except as noted)

```text
User ─┬─< Account ─< Transaction ─── transfer_pair_id ──> Transaction  (NEW, self, nullable)
      │                    │
      │                    └──> Merchant ──> Category
      └─< Merchant (default_flow_type NEW)
```
