# Phase 0 Research: Deposits and Transfers

No `NEEDS CLARIFICATION` markers remained after `/speckit-clarify`; this document records the design decisions taken against the existing codebase.

## Current-state audit

The predicate `transactions.amount_cents > 0` is the app's entire definition of "spending". It appears in:

| Location | Purpose |
|----------|---------|
| `app/Models/Transaction.php:71` | `spendingByCategory` scope |
| `app/Models/Transaction.php:101` | `spendingSummary` scope |
| `app/Models/Transaction.php:127` | `monthlySpendingTrend` scope |
| `app/Models/Transaction.php:149` | `recentSpending` scope |
| `app/Models/Transaction.php:171` | `largestSpending` scope |
| `app/Http/Controllers/BudgetController.php:89`, `:141` | budget consumption per category |
| `app/Http/Controllers/Merchants/MerchantController.php:47`, `:112` | merchant totals (no predicate at all — sums everything) |

Every one of these must move to a flow-type predicate. `MerchantController` is the subtle one: it sums *all* amounts with no sign filter, so today a checking account's transfers would land in merchant totals directly.

Three import entry points create transactions, and all three must classify:

- `app/Services/Transactions/TransactionRowStore.php` — shared by the fixed-layout CSV import and the mapped front-end upload.
- `app/Services/Plaid/PlaidTransactionSync.php` — linked-bank sync. Confirms the sign convention in a comment: "Plaid uses positive amounts for outflows, matching the app's 'positive = spend' convention".
- The new backfill command, for pre-existing rows.

## Decision: store the flow type; don't derive it

**Decision**: A `flow_type` column on `transactions`, plus `flow_type_source` recording whether it was set automatically or by the user.

**Rationale**: FR-011 requires user corrections to survive re-import, and `TransactionRowStore` re-imports with `updateOrCreate` on `import_hash` — so a derived value would be recomputed and the correction lost. Filtering (FR-012) and the aggregation predicate also want an indexable column.

**Alternatives considered**: An accessor computed from sign + description. Rejected: not persistable, not correctable, not indexable, and it would recompute the account-type-aware refund rule on every read.

## Decision: the classification rule lives on `merchants.default_flow_type`

**Decision**: A nullable `default_flow_type` column on `merchants`. When the user corrects a transaction, we write the merchant's default too; imports consult it before running the heuristics.

**Rationale**: The clarification session settled that corrections generalize per merchant. Merchants are already per-user and already carry per-merchant defaults (`category_id`, default tags), so this is the established pattern and costs one nullable column. It also means transfer/income rows must still resolve to a merchant — which they already do — matching FR-015.

**Alternatives considered**:
- Extending `merchant_rules`. Rejected: that table maps *descriptors to merchants* (prefix/regex name resolution). Overloading it with flow-type semantics conflates two different jobs and would force a `match_type`/pattern where none is wanted.
- A dedicated `flow_type_rules` table. Rejected under Principle V: it would be a table whose only key is `merchant_id` — i.e. a column on `merchants` with extra steps.

## Decision: classification algorithm

`FlowTypeClassifier::classify(Account $account, Merchant $merchant, int $amountCents, string $description): FlowType`, evaluated in precedence order:

1. **Merchant default** — if `merchant.default_flow_type` is set, use it. (This is the user's learned correction; FR-010/FR-011.)
2. **Outflow (`amount_cents > 0`)**:
   - The description matches a transfer/payment descriptor (e.g. `transfer`, `xfer`, `online transfer`, `payment thank you`, `autopay`, `epay`, `zelle`, `bill pay`, `withdrawal to`), **or** the counterpart is one of the user's own accounts → `Transfer`.
   - Otherwise → `Expense`.
3. **Inflow (`amount_cents < 0`)**, account-type aware per FR-004:
   - Description matches a transfer/payment descriptor → `Transfer`.
   - Account type is `credit` → `Refund` (an inflow to a card that isn't the card payment is a merchant credit).
   - Otherwise, the user has at least one prior `Expense` with this merchant → `Refund`.
   - Otherwise → `Income`.

**Rationale**: Uses only signals the app already holds (sign, description, `accounts.type`, merchant history). The descriptor list is a private constant on the classifier, not configuration — Principle V, and the user's corrections are the escape hatch that makes an imperfect list acceptable (SC-002 targets 90%, not 100%).

**Performance**: The "prior expense with this merchant" test would be a query per row. The classifier is primed per user at the start of an import (mirroring `NameResolver::forUser()`), loading the set of merchant ids that already have expense activity into memory, and updating that set as rows are classified. O(rows), zero per-row queries.

## Decision: transfer pairing runs per batch, after insert

**Decision**: `TransferPairer::pairForUser(int $userId)` runs once at the end of an import/sync/backfill. It selects the user's unpaired transfers and joins each to a candidate with equal `ABS(amount_cents)`, opposite sign, same currency, a different account of the same user, and `posted_at` within 3 days. Each transaction takes at most one partner; the closest-dated candidate wins, ties broken by id. Pairing is stored as a self-referencing `transfer_pair_id` on both rows.

**Rationale**: A per-row hook can't pair legs that haven't been imported yet (the checking file may be imported before the savings file). Running per batch and re-running idempotently handles both orders. Storing the link on both rows makes "is this leg paired?" a column read rather than a join.

**Alternatives considered**: A separate `transfer_links` table with two FKs. Rejected: a self-referencing nullable FK expresses a symmetric pair of exactly two rows with less machinery, and the pairing carries no attributes of its own.

**Note**: pairing is a *display and integrity* nicety, not a correctness requirement — an unpaired transfer is already excluded from spending and income by its flow type (FR-008's exclusion holds either way).

## Decision: aggregation predicate

**Decision**: Spending queries filter `flow_type IN ('expense', 'refund')` and keep `SUM(amount_cents)`. Income queries filter `flow_type = 'income'` and report `-SUM(amount_cents)` (inflows are negative). Net cash flow is `income − spending`.

**Rationale**: Refunds are stored negative, so summing them alongside positive expenses nets them out of the category, merchant, and budget totals for free (FR-006), including the negative-total edge case the spec calls for. No `CASE` expressions, no absolute values, no second query.

**Consequence**: `recentSpending` and `largestSpending` should filter to `expense` only (a refund is not a "largest expense"), while the summing scopes use the two-type set. This is called out explicitly so the difference is intentional rather than an oversight.

## Decision: backfill via an Artisan command

**Decision**: `php artisan transactions:classify-flow-types` classifies every transaction whose `flow_type_source` is not `user`, then runs the pairer. Idempotent; safe to re-run.

**Rationale**: FR-016 requires existing data to become correct without re-import. Keeping the logic in a command (rather than inside the migration) means it can be re-run after the classifier improves, and the migration stays a pure schema change. The new column's default is `expense`, so the schema is valid the moment it lands and the command corrects it.
