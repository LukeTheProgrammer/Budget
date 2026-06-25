# Phase 0 Research: Transaction Schema

All Technical Context items were resolvable from the constitution and installed stack;
no NEEDS CLARIFICATION remained. Key decisions below.

## Decision: Store money as integer minor units (cents)

- **Decision**: `amount_cents BIGINT` (signed), plus a `currency CHAR(3)` column.
- **Rationale**: Avoids floating-point rounding errors in spend aggregation; standard
  practice for financial data. Signed value lets refunds/credits be negative (FR-006).
- **Alternatives considered**: `DECIMAL(12,2)` — valid and exact, but integer cents is
  simpler to sum in app code and sidesteps locale/scale ambiguity. Rejected for v1.

## Decision: Merchant → Category is one-to-many

- **Decision**: `merchants.category_id` nullable FK; transactions derive their category
  through their merchant.
- **Rationale**: Matches the spec (a merchant has one category; categories group many
  merchants). Keeps reporting a single join chain and avoids per-transaction category
  duplication. Nullable supports "Uncategorized" (FR/edge case).
- **Alternatives considered**: Per-transaction `category_id` override — deferred (YAGNI,
  Constitution V); many-to-many merchant/category — unnecessary for stated requirements.

## Decision: Transactions reference merchant nullably

- **Decision**: `transactions.merchant_id` nullable FK.
- **Rationale**: FR-009 requires storing transactions with an unresolved merchant.
- **Alternatives considered**: Required FK with a sentinel "Unknown" merchant — adds
  bookkeeping; nullable is simpler and queries can `LEFT JOIN`.

## Decision: Duplicate-import detection via a hash column

- **Decision**: `transactions.import_hash CHAR(64)` nullable, with a unique index on
  `(account_id, import_hash)`.
- **Rationale**: Satisfies FR-008/SC-003 (re-import is idempotent) without prescribing an
  import format. The app computes a hash of stable fields (date, amount, raw descriptor)
  on import; manual entries leave it null.
- **Alternatives considered**: Provider transaction IDs — not available for all sources;
  hash is source-agnostic.

## Decision: Ownership scoping

- **Decision**: `accounts.user_id` FK to existing `users`; categories also scoped by
  `user_id`. Transactions/merchants inherit ownership through account/category.
- **Rationale**: Reuses Fortify's existing `users` table (Constitution III). Even though
  the app is local single-user, scoping keeps data model honest and queries explicit.
- **Alternatives considered**: No user scoping (single global dataset) — rejected; cheap
  to scope and avoids rework if multiple profiles are ever added.

## Decision: Soft deletes & timestamps

- **Decision**: All domain tables get `created_at/updated_at`. `transactions` and
  `accounts` use `softDeletes()`; `merchants`/`categories` do not (hard delete with FK
  handling).
- **Rationale**: Preserves historical transactions if an account is removed; categories
  are reference data that can be reassigned then deleted.
- **Alternatives considered**: Soft-delete everything — unnecessary overhead for small
  reference tables.
