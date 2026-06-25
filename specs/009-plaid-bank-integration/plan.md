# Implementation Plan: Plaid Bank Account Integration

**Branch**: `009-plaid-bank-integration` | **Date**: 2026-06-24 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/009-plaid-bank-integration/spec.md`

## Summary

Add the ability for a user to securely link real banking/credit-card accounts via Plaid
Link and import their transactions and balances into the existing budget app. The app
exposes a hosted Plaid Link flow (no raw credentials touch the server), exchanges the
public token for an access token, and stores a per-user **Connection** (Plaid Item) with
its linked **Accounts** mapped onto the existing `Account` model. A user-triggered sync
pulls transactions via Plaid's `/transactions/sync` cursor endpoint and upserts them into
the existing `Transaction` table using the established `import_hash` + `updateOrCreate`
mechanism, reusing merchant/category resolution. Automatic background refresh, webhooks,
and cross-source reconciliation are explicitly deferred (v1 = on-demand sync, Sandbox
environment, config-selectable for Production later).

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13) backend; React 19 + TypeScript (Inertia v3) frontend

**Primary Dependencies**: Laravel framework, Inertia v3, Fortify (existing auth), Plaid
API. Plaid access is a **custom in-house service over Laravel's `Http` client** — no
Composer dependency (the community `tomorrowideas/plaid-sdk-php` was rejected as
unmaintained, ~3 years stale). Frontend uses the approved `react-plaid-link` npm package.

**Storage**: MySQL 8.4 (Sail). New tables `plaid_connections`, plus columns added to the
existing `accounts` table; reuse existing `transactions` table.

**Testing**: None — per Constitution Principle II (No Automated Tests). Verification is
manual via Plaid Sandbox credentials.

**Target Platform**: Local development only (Laravel Sail, `http://localhost`).

**Project Type**: Web application (Inertia SPA: Laravel backend + React frontend, single repo).

**Performance Goals**: A user-triggered sync of a typical account completes within ~30s
(SC-003); initial historical import runs on the queue so it never blocks the session.

**Constraints**: Plaid access secrets encrypted at rest, never exposed to the frontend
(FR-011). Sandbox by default; environment config-selectable. Amounts stored in integer
minor units consistent with existing model.

**Scale/Scope**: Single local user, a handful of linked institutions; depository + credit
card accounts only.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Local-Development-Only Scope** — PASS. Sandbox-first, on-demand sync, no production
  hardening (no webhooks/scheduler/secret-rotation infra). Production is a config switch
  treated as an operational prerequisite, not built-out infrastructure.
- **II. No Automated Tests** — PASS. No test artifacts or test-authoring tasks will be
  produced; manual verification via Plaid Sandbox documented in quickstart.md.
- **III. Framework-Idiomatic Code** — PASS. Uses Artisan generators, Eloquent models +
  migrations, Inertia page rendering, Wayfinder route/action helpers, queued Jobs for the
  import, and shadcn/ui primitives. Mirrors the existing `Transactions`/`Merchants`
  controller + service layering.
- **IV. Code Quality Gates** — PASS. PHP via Pint; frontend via ESLint/Prettier/tsc.
  Explicit types and return types throughout.
- **V. Simplicity & Convention Over Configuration** — PASS. Reuses `Account`/`Transaction`
  and the `import_hash` upsert rather than a parallel data model; one new table + a few
  columns. Plaid access is a thin `Http`-client service with no third-party SDK.

**Dependency decision (resolved)**: The Plaid backend is a custom in-house `Http`-client
service — **no new Composer dependency**. The frontend `react-plaid-link` npm package is
user-approved. No outstanding approval gate remains.

## Project Structure

### Documentation (this feature)

```text
specs/009-plaid-bank-integration/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (HTTP route contracts)
│   └── plaid-routes.md
└── checklists/
    └── requirements.md  # From /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Account.php                 # + plaid_connection_id, plaid_account_id, type, balance_cents
│   └── PlaidConnection.php         # NEW — Plaid Item per user
├── Services/
│   └── Plaid/
│       ├── PlaidClient.php         # NEW — in-house Http wrapper: linkToken, exchange, accounts, transactionsSync, removeItem
│       ├── PlaidConfig.php         # NEW — env selection (sandbox/production), credentials
│       ├── PlaidAccountSync.php    # NEW — upserts accounts/balances from Plaid into Account
│       └── PlaidTransactionSync.php# NEW — cursor sync → import_hash upsert, reuses merchant/category resolution
├── Jobs/
│   └── SyncPlaidConnection.php     # NEW — queued initial + on-demand sync
└── Http/
    ├── Controllers/Plaid/
    │   ├── PlaidConnectionController.php  # NEW — index (list), destroy (disconnect)
    │   ├── PlaidLinkTokenController.php   # NEW — POST create link_token
    │   ├── PlaidLinkController.php        # NEW — POST exchange public_token → Connection
    │   └── PlaidSyncController.php        # NEW — POST trigger on-demand sync
    └── Requests/Plaid/
        └── ExchangePublicTokenRequest.php # NEW

database/migrations/
├── ****_create_plaid_connections_table.php           # NEW
└── ****_add_plaid_columns_to_accounts_table.php       # NEW

routes/web.php                       # + plaid.* routes inside auth/verified group

resources/js/
├── pages/settings/
│   └── connections.tsx             # NEW — manage linked banks (list, link, sync, disconnect)
└── components/
    └── plaid-link-button.tsx       # NEW — wraps react-plaid-link, opens Plaid Link
```

**Structure Decision**: Web application (single Inertia repo). The feature follows the
existing reference layering: routes in `routes/web.php`, namespaced controllers under
`app/Http/Controllers/Plaid/`, business logic in `app/Services/Plaid/`, queued work in
`app/Jobs/`, and an Inertia page under `resources/js/pages/settings/connections.tsx`
(linked from the settings module, the project's reference pattern). It reuses the existing
`Account`, `Transaction`, `Merchant`, and `Category` models rather than introducing a
parallel data model.

## Complexity Tracking

> No constitution violations requiring justification. Dependency decisions are resolved:
> custom in-house `Http` service for Plaid (no Composer dependency), user-approved
> `react-plaid-link` on the frontend.
