# Phase 0 Research: Plaid Bank Account Integration

## R1 — Plaid client library (DECIDED)

**Decision**: Build a **custom in-house `PlaidClient` service** over Laravel's `Http`
client. **No third-party Plaid SDK.** (User decision, 2026-06-24.)

**Rationale**: The feature only needs five Plaid endpoints (`/link/token/create`,
`/item/public_token/exchange`, `/accounts/balance/get`, `/transactions/sync`,
`/item/remove`). A thin typed wrapper over `Http::baseUrl(...)` with client_id/secret
injected from config is simple, idiomatic (Principle III/V), and adds no dependency. Plaid
has no official PHP SDK.

**Alternatives considered & rejected**:
- `tomorrowideas/plaid-sdk-php` — **rejected by user**: unmaintained (~3 years without an
  update), so the dependency risk outweighs the minor request-plumbing savings for a
  five-call integration.
- Raw Guzzle — unnecessary; `Http` already wraps Guzzle and is the framework-idiomatic
  choice.

## R2 — Front-end Plaid Link integration

**Decision**: Use the official `react-plaid-link` package on the React side, opened from a
`plaid-link-button.tsx` component. The component receives a `link_token` fetched from the
backend (`POST plaid.link-token`) and, on success, posts the returned `public_token` to
`POST plaid.exchange` via an Inertia/Wayfinder action.

**Rationale**: `react-plaid-link` is Plaid's officially supported React wrapper for Plaid
Link, handles the hosted credential UI (so the app never sees raw credentials, FR-001),
and fits React 19. This frontend npm dependency is **user-approved** (2026-06-24).

**Alternatives considered**:
- Loading Plaid's `link-initialize.js` script manually — more boilerplate, no typing.
- Plaid Hosted Link (redirect) — heavier; embedded Link is simpler for a local SPA.

## R3 — Transaction sync strategy

**Decision**: Use Plaid `/transactions/sync` with a persisted per-connection `cursor`.
Each sync applies `added`, `modified`, and `removed` sets. `added`/`modified` rows are
upserted into `transactions` via `Transaction::updateOrCreate(['import_hash' => $hash], …)`
using a stable hash derived from the Plaid `transaction_id`. `removed` rows are
deleted/voided by matching `import_hash`.

**Rationale**: `/transactions/sync` is Plaid's current recommended incremental model and
maps cleanly onto the existing `import_hash` + `updateOrCreate` mechanism already used by
`CsvTransactionImporter` (FR-005/FR-006). Cursor persistence (`Sync State`) makes repeated
syncs idempotent (SC-004) and cheap.

**Alternatives considered**:
- Legacy `/transactions/get` with date windows — requires manual pagination and dedupe;
  superseded by `/transactions/sync`.

**Hash note**: Use `transaction_id` (Plaid's stable id) as the hash basis rather than
amount/date/merchant so pending→posted changes update the same row instead of duplicating.

## R4 — Account mapping & balances

**Decision**: Each Plaid account becomes an `Account` row carrying `plaid_connection_id`,
`plaid_account_id`, a normalized `type`, and a cached `balance_cents`. `last_four` maps
from Plaid's `mask`, `name` from the account name, `currency` from `iso_currency_code`.
Balances refresh on each sync.

**Rationale**: Matches the spec clarification (reuse `Account`, nullable connection
reference). Keeps all existing transaction/merchant/category/dashboard features working
unchanged.

**Alternatives considered**: Separate `PlaidAccount` table — rejected per spec
clarification (Q1 → A) to avoid a parallel model.

## R5 — Sync execution (queue) and "real time" scope

**Decision**: Run both the initial post-link import and on-demand syncs through a queued
Job (`SyncPlaidConnection`) on the existing `database` queue connection. No scheduler, no
webhook endpoint in v1.

**Rationale**: Initial history can be large; queueing keeps the session responsive
(spec edge case + SC). The spec clarification (Q2 → C) defers automatic/webhook refresh,
so building scheduler/webhook infra now would violate Constitution Principle I (no
production concerns) and add unused complexity.

**Alternatives considered**: Synchronous controller sync — risks request timeouts on
initial import. Webhook + scheduled refresh — deferred by clarification.

## R6 — Secrets & environment configuration

**Decision**: Add `config/services.php` `plaid` block reading `PLAID_CLIENT_ID`,
`PLAID_SECRET`, `PLAID_ENV` (sandbox|development|production) from `.env`. The per-Item
access token is stored on `plaid_connections.access_token` using Eloquent
`encrypted` cast (encrypted at rest, never sent to the frontend — FR-011).

**Rationale**: Config-selectable environment satisfies clarification Q4 → A (Sandbox now,
Production later without code changes). The `encrypted` cast is the idiomatic Laravel way
to keep secrets out of plaintext and out of Inertia props.

**Alternatives considered**: Storing access tokens plaintext — rejected (FR-011). A
dedicated secrets vault — out of scope for local-only (Principle I).

## Resolved unknowns

All Technical Context items are resolved. Dependency decisions are finalized: **no Plaid
Composer SDK** (custom in-house `Http` service, R1) and the **user-approved** frontend npm
package `react-plaid-link` (R2). No outstanding approvals remain.
