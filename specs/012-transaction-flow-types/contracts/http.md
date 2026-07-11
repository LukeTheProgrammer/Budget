# Phase 1 Contracts: Routes and Inertia Props

All routes sit inside the existing `auth` + `verified` middleware group in `routes/web.php`. Frontend calls them through Wayfinder-generated helpers (`@/actions/...`), never hand-written URLs.

## New route

### `PATCH transactions/{transaction}/flow-type` → `Transactions\TransactionFlowTypeController@update`

Name: `transactions.flow-type.update`

Request (`UpdateFlowTypeRequest`):

```json
{
  "flow_type": "expense | income | transfer | refund",
  "apply_to_merchant": true
}
```

- `flow_type` — required, `Rule::enum(FlowType::class)`.
- `apply_to_merchant` — boolean, defaults to `true`. When true, also writes `merchants.default_flow_type` so future imports of the same merchant classify the same way (FR-010). When false, only this transaction changes.

Behavior: sets `flow_type` and `flow_type_source = user`; clears `transfer_pair_id` on both legs if the transaction is leaving the `transfer` type; re-runs `TransferPairer` for the user if it is entering it. Authorizes that the transaction's account belongs to the authenticated user (403 otherwise).

Response: Inertia redirect back (`back()`), matching `TransactionTagController`.

## Modified props

### `dashboard` (`DashboardController@index`)

Adds, for the selected period:

```ts
cash_flow: {
  income_cents: number;    // positive magnitude of inflows classified as income
  spending_cents: number;  // expenses net of refunds
  net_cents: number;       // income_cents - spending_cents; may be negative
}
```

Existing spending props keep their shape; only their underlying predicate changes.

### `transactions` (`TransactionController@index`)

Each row gains:

```ts
flow_type: 'expense' | 'income' | 'transfer' | 'refund';
flow_type_source: 'auto' | 'user';
is_paired_transfer: boolean;   // transfer_pair_id !== null
```

The page also receives `flow_type_options: Array<{ value: string; label: string }>` (from `FlowType::options()`) for the filter and the per-row editor.

### `TransactionFilterRequest`

Adds an optional `flow_type` query parameter: an array of enum values. Absent means no flow-type filtering (all types listed — FR-017). Maps into the `filter()` scope as `flow_types`.

### `merchants` (`MerchantController@index`)

- Merchant totals are computed over `flow_type IN (expense, refund)` instead of over every transaction.
- The listing is scoped to merchants with expense activity by default. A `?include_non_expense=1` query parameter (surfaced as a toggle) lifts the scope (FR-015).
- Prop `include_non_expense: boolean` echoes the current state back to the page.

## New Artisan command

```bash
./vendor/bin/sail artisan transactions:classify-flow-types [--user=ID]
```

Reclassifies every transaction whose `flow_type_source` is `auto` (user-set rows are never touched), then runs `TransferPairer` for each affected user. Idempotent. Reports counts per flow type.
