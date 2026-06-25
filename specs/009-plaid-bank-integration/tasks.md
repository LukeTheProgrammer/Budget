---
description: "Task list for Plaid Bank Account Integration"
---

# Tasks: Plaid Bank Account Integration

**Input**: Design documents from `/specs/009-plaid-bank-integration/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/plaid-routes.md, quickstart.md

**Tests**: NONE. Per Constitution Principle II (No Automated Tests), no test tasks are
generated. Verification is manual via `quickstart.md` using Plaid Sandbox.

**Organization**: Tasks are grouped by user story to enable independent implementation and
verification of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)
- All paths are relative to the repository root `/home/luke/Dev/budget/`

## Path Conventions

Web application (Inertia single repo): backend under `app/`, migrations under
`database/migrations/`, routes in `routes/web.php`, frontend under `resources/js/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Dependencies and configuration that everything else builds on.

- [X] T001 Install the approved frontend package: `./vendor/bin/sail npm install react-plaid-link`. (No Composer dependency — Plaid backend is a custom in-house `Http` service per research.md R1.)
- [X] T002 Add `plaid` service block to `config/services.php` (`env`, `client_id`, per-env `secrets`, per-env `base_urls`) reading `PLAID_ENV`, `PLAID_CLIENT_ID`, `PLAID_SANDBOX_SECRET`, `PLAID_PRODUCTION_SECRET` — aligned to the project's existing env naming
- [X] T003 [P] Add `PLAID_ENV=sandbox` to `.env.example` and `.env` (the `PLAID_CLIENT_ID`/`PLAID_SANDBOX_SECRET`/`PLAID_PRODUCTION_SECRET` keys already existed)

**Checkpoint**: Plaid credentials are configurable and the chosen client library is available.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models, and the Plaid client wrapper that ALL user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 Create migration `database/migrations/2026_06_24_125416_create_plaid_connections_table.php` per data-model.md (user_id FK, plaid_item_id unique, access_token text, institution_id, institution_name, status, transactions_cursor, last_synced_at, timestamps). Added `app/Enums/PlaidConnectionStatus.php` for the status enum.
- [X] T005 Create migration `database/migrations/2026_06_24_125417_add_plaid_columns_to_accounts_table.php` adding `plaid_connection_id` (nullable FK, null-on-delete), `plaid_account_id` (nullable), `type` (nullable), `balance_cents` (nullable bigint); unique index `(plaid_connection_id, plaid_account_id)`. (Create migration renamed to `…125416` so the FK target table exists before the alter runs.)
- [X] T006 Run `./vendor/bin/sail artisan migrate` — both migrations DONE.
- [X] T007 [P] Create `app/Models/PlaidConnection.php` with `#[Fillable]`, `access_token` `encrypted` cast + `$hidden`, `status` enum cast, `belongsTo(User)`, `hasMany(Account)`
- [X] T008 [P] Update `app/Models/Account.php`: added Plaid columns to fillable + docblocks, `balance_cents` integer cast, `belongsTo(PlaidConnection)` relation, and `isLinked()` helper
- [X] T009 [P] Update `app/Models/User.php` to add `hasMany(PlaidConnection)` relation (`plaidConnections()`)
- [X] T010 Create `app/Services/Plaid/PlaidConfig.php` resolving environment + base URL + active-env secret + client id from `config('services.plaid')`
- [X] T011 Create `app/Services/Plaid/PlaidClient.php` — custom `Http`-client service (no SDK) wrapping the five endpoints, injecting client_id/secret per request
- [X] T012 Foundational `plaid.*` route group anchor added in `routes/web.php` inside the `auth`+`verified` group; controller stubs generated under `app/Http/Controllers/Plaid/`. Per-route wiring + imports happen in the per-story tasks (T018/T028/T033) to avoid unused imports Pint would strip.

**Checkpoint**: Schema migrated, models and Plaid client wrapper ready — user stories can begin.

---

## Phase 3: User Story 1 - Connect a bank account (Priority: P1) 🎯 MVP

**Goal**: A user securely links a financial institution via Plaid Link and sees the linked
accounts (institution name, account name, last four, type, balance).

**Independent Test**: At `/settings/connections`, click "Link a bank", complete Plaid
Sandbox flow (`user_good`/`pass_good`), and confirm the connection and its accounts appear.

### Implementation for User Story 1

- [X] T013 [US1] Implement `PlaidLinkTokenController@store` returning `{ link_token }` from `PlaidClient::createLinkToken()` (route `POST /plaid/link-token` → `plaid.link-token`)
- [X] T014 [P] [US1] Create `app/Http/Requests/Plaid/ExchangePublicTokenRequest.php` validating `public_token` (required string) and `institution` (nullable array with `institution_id`/`name`)
- [X] T015 [US1] Create `app/Services/Plaid/PlaidAccountSync.php` that fetches accounts via `PlaidClient::getAccounts()` and upserts `Account` rows (map `mask`→`last_four`, `iso_currency_code`→`currency`, `type`, `balance_cents`) keyed on `(plaid_connection_id, plaid_account_id)`
- [X] T016 [US1] Implement `PlaidLinkController@store`: exchange `public_token`, create `PlaidConnection` (encrypted access token, status `active`), call `PlaidAccountSync`, redirect to `connections.index` with flash. (Initial transaction-import job dispatch deferred to T025/Phase 4.) `PlaidConfig` bound as a singleton in `AppServiceProvider`.
- [X] T017 [US1] Implement `PlaidConnectionController@index` returning Inertia `settings/connections` with connections + nested accounts (added `currency` to account props for formatting). Route lives in `routes/settings.php` (`GET /settings/connections` → `connections.index`).
- [X] T018 [US1] Wired routes (`plaid.link-token`, `plaid.exchange` in `routes/web.php`; `connections.index` in `routes/settings.php`) and regenerated Wayfinder with `wayfinder:generate --with-form` to match the Vite plugin's `formVariants: true`
- [X] T019 [P] [US1] Create `resources/js/components/plaid-link-button.tsx` wrapping `react-plaid-link`: fetch `link_token` from `plaid.link-token` (with XSRF header), open Link, post `public_token` to `plaid.exchange` via the Inertia router
- [X] T020 [US1] Create `resources/js/pages/settings/connections.tsx` listing connections + accounts (institution, name, last four, balance) with the link button + empty state; settings layout applied centrally
- [X] T021 [P] [US1] Added a "Connections" link to the settings navigation (`resources/js/layouts/settings/layout.tsx`)

**Checkpoint**: A user can link a real (Sandbox) bank and see their accounts listed. MVP complete.

---

## Phase 4: User Story 2 - Import transactions from a linked account (Priority: P1)

**Goal**: After linking, the account's transactions are imported into the existing
`transactions` table (de-duplicated, merchants/categories resolved) via background sync.

**Independent Test**: After linking, view `/transactions` and confirm Sandbox transactions
appear against the linked account with no duplicates.

### Implementation for User Story 2

- [X] T022 [US2] Create `app/Services/Plaid/PlaidTransactionSync.php` calling `PlaidClient::syncTransactions(accessToken, cursor)`, looping while `has_more`, applying `added`/`modified` via `Transaction::updateOrCreate(['import_hash' => $hash], …)` and `removed` via `import_hash` match; persists `transactions_cursor` after each page (research.md R3, FR-005/FR-006)
- [X] T023 [US2] Map Plaid fields → Transaction (`amount`×100 — Plaid's positive=outflow already matches the app's positive=spend convention; `iso_currency_code`; `name`/`merchant_name`; `authorized_date`/`date`→`posted_at`) and resolve merchant/category by reusing `NameResolver` (aliases+rules) + alias fallback + auto-create-with-alias + category backfill, mirroring the CSV importer (FR-013)
- [X] T024 [US2] Create `app/Jobs/SyncPlaidConnection.php` (queued) running `PlaidAccountSync` then `PlaidTransactionSync`, setting `status`=Active + `last_synced_at` on success and `status`=Error (logged, rethrown) on failure
- [X] T025 [US2] Dispatch `SyncPlaidConnection` from `PlaidLinkController@store` after the connection is created (accounts synced synchronously for instant display; transaction import backgrounded)
- [ ] T026 [US2] **Manual verification (needs live Sandbox + queue worker).** Code wiring verified: services resolve from the container, job dispatch wired, routes load. Still to do by hand: run `./vendor/bin/sail artisan queue:work`, link a Sandbox account, and confirm imported transactions surface on `/transactions` against the linked account with no duplicates.

**Checkpoint**: Linked accounts import transactions automatically after linking; no duplicates on re-import.

---

## Phase 5: User Story 3 - Refresh spending data on demand (Priority: P2)

**Goal**: The user can trigger a sync for a connection to pull new transactions and updated
balances since the last sync.

**Independent Test**: Click "Sync" on a connection; confirm new Sandbox activity appears
and balances refresh, with no duplicate transactions (SC-004).

### Implementation for User Story 3

- [X] T027 [US3] Implement `PlaidSyncController@store`: authorize ownership (403 otherwise), dispatch `SyncPlaidConnection`, redirect back with "Sync started" flash (route `POST /plaid/connections/{connection}/sync` → `plaid.sync`)
- [X] T028 [US3] Added the `plaid.sync` route in `routes/web.php` and regenerated Wayfinder with `--with-form`
- [X] T029 [US3] Added a per-connection "Sync" button (outline) to `connections.tsx` posting to `plaid.sync` via the Inertia router with a per-connection "Syncing…" pending state, plus a "Last synced …" line

**Checkpoint**: On-demand re-sync works idempotently and refreshes balances.

---

## Phase 6: User Story 4 - Manage and disconnect connections (Priority: P3)

**Goal**: The user can see connection health, re-authenticate an invalidated connection,
and disconnect a connection (revoking Plaid access while retaining past transactions).

**Independent Test**: Disconnect a connection → it stops syncing and disappears, but its
transactions remain; force `ITEM_LOGIN_REQUIRED` → connection shows "Needs attention".

### Implementation for User Story 4

- [X] T030 [US4] Implement `PlaidConnectionController@destroy`: authorize ownership (403), call `PlaidClient::removeItem()` (best-effort — tolerates an already-removed Item), null `accounts.plaid_connection_id`, delete the `PlaidConnection`; transactions retained (route `DELETE /plaid/connections/{connection}` → `plaid.connections.destroy`, FR-010/FR-015)
- [X] T031 [US4] Added `PlaidApiException` carrying Plaid's `error_code`; `PlaidClient::post` now throws it on failed responses, and `SyncPlaidConnection` sets `status`=ReauthRequired on `ITEM_LOGIN_REQUIRED`, else `Error` (FR-009, FR-014)
- [X] T032 [US4] Re-auth support: `PlaidLinkTokenController` accepts an optional owned `connection` to mint an update-mode `link_token`; `PlaidLinkButton` gained a `connectionId`/`label` mode that re-syncs (clearing the error) on success instead of exchanging; `connections.tsx` shows a "Re-authenticate" action when `status = reauth_required`
- [X] T033 [US4] Added the `plaid.connections.destroy` route, regenerated Wayfinder, and added a "Disconnect" button (confirm dialog) + Sync + status text (Connected / Needs attention / Error) to `connections.tsx`

**Checkpoint**: Full connection lifecycle (health, re-auth, disconnect) works.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Hardening and final verification across all stories.

- [X] T034 [P] `access_token` never serialized — `PlaidConnection` uses `$hidden`, and `connections.index` shapes explicit props. Verified: `PlaidConnection::toArray()` omits `access_token` (FR-011)
- [X] T035 [P] Graceful error handling: `PlaidClient` retries transient connection failures (`->retry(2, 200)`); `SyncPlaidConnection` retries up to 3× with backoff, only marks `Error` on the final attempt, and does not retry `ITEM_LOGIN_REQUIRED` (flags `ReauthRequired` instead). Persistent errors surface via the connection's status badge on the connections page (FR-014/FR-009)
- [X] T036 Quality gates all green project-wide: Pint ✅, ESLint ✅, Prettier ✅, `tsc --noEmit` ✅. Also fixed pre-existing lint/format debt surfaced by the gates (budgets import-order ×4 auto-fixed, removed dead `StatTile` in `category-detail.tsx`, Pint blank-line nit in `DefaultMerchantDefinitions.php`).
- [ ] T037 **Manual (needs live Sandbox + `queue:work`).** Run `quickstart.md` end-to-end (link → import → sync → disconnect → re-auth) and confirm all acceptance scenarios. Cannot be driven from the implementation environment.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately (T001 needs user approval for deps).
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–6)**: All depend on Foundational completion.
- **Polish (Phase 7)**: Depends on the desired user stories being complete.

### User Story Dependencies

- **US1 (P1)**: After Foundational. No dependency on other stories. 🎯 MVP.
- **US2 (P1)**: After Foundational. Reuses US1's `PlaidLinkController` (T025 dispatches the job) but the sync services are independent; testable on its own once a connection exists.
- **US3 (P2)**: After Foundational + reuses `SyncPlaidConnection` (US2's T024). Independently testable.
- **US4 (P3)**: After Foundational. Independent; status detection (T031) touches the sync job from US2.

### Within Each User Story

- Models/services before controllers; controllers before routes; routes before frontend wiring (Wayfinder generation).
- Backend endpoint before the React page that calls it.

### Parallel Opportunities

- Setup: T003 [P] alongside T002.
- Foundational: T007, T008, T009 [P] (different model files) after migrations (T006).
- US1: T014 and T019 and T021 [P] (request, component, nav — different files).
- Polish: T034, T035 [P].

---

## Parallel Example: Foundational models

```bash
# After T006 (migrate), create models together:
Task: "Create app/Models/PlaidConnection.php"            # T007
Task: "Update app/Models/Account.php with Plaid columns"  # T008
Task: "Update app/Models/User.php with connections relation" # T009
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

Because US1 and US2 are both P1, the true MVP is "link a bank AND see its transactions":

1. Phase 1 Setup → Phase 2 Foundational.
2. Phase 3 (US1) → validate linking + account list.
3. Phase 4 (US2) → validate transaction import.
4. **STOP and VALIDATE** via quickstart.md steps 4–5.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. US1 → link + accounts (demo).
3. US2 → transactions import (core value delivered).
4. US3 → on-demand refresh.
5. US4 → lifecycle management.
6. Polish → quality gates + full quickstart run.

---

## Notes

- No automated tests (Constitution Principle II); verify manually via `quickstart.md`.
- [P] tasks = different files, no incomplete dependencies.
- Wayfinder route/action files are generated — never hand-edit; regenerate after route changes.
- Commit after each task or logical group.
- Plaid backend is a custom in-house `Http`-client service — no Plaid Composer SDK. The only added dependency is the approved `react-plaid-link` npm package (T001).
