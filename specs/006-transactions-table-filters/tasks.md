---
description: "Task list for Transactions Table with Filters"
---

# Tasks: Transactions Table with Filters

**Input**: Design documents from `/specs/006-transactions-table-filters/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/transactions-index.md

**Tests**: NONE. Per Constitution Principle II (No Automated Tests), this project does not author automated tests. Verification is manual in the browser (see quickstart.md). No test tasks are generated.

**Organization**: Tasks are grouped by user story. US1 (browse table) and US2 (filtering) are both P1; US3 (URL persistence) is P2.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task serves (US1, US2, US3)

## Path Conventions

Web app (Laravel + Inertia React). Backend under `app/`, routes in `routes/`, frontend under `resources/js/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Add shared primitives needed before any story work.

- [X] T001 [P] Add the shadcn table primitive at `resources/js/components/ui/table.tsx` (Table, TableHeader, TableBody, TableRow, TableHead, TableCell, TableFooter, TableCaption).
- [X] T002 [P] Added a "Transactions" sidebar link (ReceiptText icon) in `resources/js/components/app-sidebar.tsx`, using the Wayfinder `index` route from `@/routes/transactions`. (Completed in Phase 3 once the route helper was generated.)

**Checkpoint**: Table primitive and nav entry exist (nav link will 404 until Phase 2 route lands).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Route, controller, base query scope, and page props that BOTH P1 stories depend on. No filtering logic yet — returns all of the user's transactions, paginated.

**⚠️ MUST complete before Phase 3 and Phase 4.**

- [X] T003 Add `#[Scope] filter(Builder $query, int $userId, array $filters = [])` to `app/Models/Transaction.php`: scope to `accounts.user_id = $userId` (via `whereHas('account')`), eager-load `merchant.category`, order `posted_at desc, id desc`. Per-filter conditionals stubbed for T011.
- [X] T004 Create `app/Http/Controllers/Transactions/TransactionController.php`; add `index(Request $request): Response` that runs the `filter` scope for `$request->user()->id`, calls `->paginate(50)->withQueryString()`, and returns `Inertia::render('transactions/index', [...])`.
- [X] T005 In `TransactionController`, add a private `transactionRows(...)` mapper producing the `TransactionRow` shape (`id, posted_at (Y-m-d), merchant_label, category_name|null, description|null, amount_cents, currency`).
- [X] T006 In `TransactionController`, build the `pagination` prop (`current_page, last_page, per_page, total, links`) and a `currency` prop from the user's first account (fallback `USD`).
- [X] T007 [P] In `TransactionController`, add `merchant_options` and `category_options` props as `{ id, label }[]` scoped to the authenticated user.
- [X] T008 Register the route in `routes/web.php` inside the `auth, verified` group: `Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');` and import the controller.

**Checkpoint**: `/transactions` loads server-side and returns paginated rows + options + currency props (no UI yet).

---

## Phase 3: User Story 1 - Browse all transactions in a table (Priority: P1) 🎯 MVP

**Goal**: Render the user's transactions in a paginated table, newest first, with an empty state.

**Independent Test**: Visit `/transactions` with no query params and confirm a table of the user's transactions renders (date, merchant, category, description, amount), newest first, paginated; empty state when the user has none.

- [X] T009 [US1] Create `resources/js/pages/transactions/index.tsx`: define the `TransactionRow`/`Pagination`/`TransactionsPageProps` types (per contracts), render `<Head>`, a page heading, and the table from `transactions` using the `ui/table` primitive with columns date, merchant, category, description, amount. Format amounts with `Intl.NumberFormat` (value/100, `currency`). Show an empty state when `transactions.length === 0`. Render pagination controls from `pagination.links` (using Inertia `<Link>`). Do NOT import a layout (assigned centrally in `app.tsx`).

**Checkpoint**: US1 fully functional — browsing and pagination work end-to-end. This is the MVP.

---

## Phase 4: User Story 2 - Filter by date range, merchant, category, amount (Priority: P1)

**Goal**: Narrow the table by date range, single merchant, single category, and amount range, combined with AND.

**Independent Test**: Apply each filter individually and combined via query params and confirm only matching rows show; clearing returns the full list.

- [X] T010 [US2] Create `app/Http/Requests/Transactions/TransactionFilterRequest.php` via `./vendor/bin/sail artisan make:request Transactions/TransactionFilterRequest`: `authorize()` returns true; nullable rules for `start`/`end` (date), `merchant_id` (integer + `exists:merchants,id` scoped to the user via a `Rule::exists(...)->where('user_id', ...)`), `category_id` (same for categories), `min_amount`/`max_amount` (numeric, min:0), `page` (integer min:1). Add a `filters()` helper returning the normalized array with amounts converted to cents (`round($v * 100)`).
- [X] T011 [US2] Fill in the conditional filter logic in `Transaction::filter` (T003): apply `posted_at >= start`, `posted_at <= end`, `merchant_id = ?`, `merchant.category_id = ?` (constrain via the merchant join/`whereHas`), `amount_cents >= min`, `amount_cents <= max` — each only when the corresponding key is present.
- [X] T012 [US2] Update `TransactionController::index` to type-hint `TransactionFilterRequest`, pass `$request->filters()` into the scope, and echo the validated `filters` (in major units, as received) back as the `filters` prop. Ensure invalid params are ignored (validation makes them null) rather than erroring.
- [X] T013 [US2] Create `resources/js/components/transactions/transaction-filters.tsx`: a filter bar with two date inputs (start/end), a merchant `Select` (from `merchant_options`), a category `Select` (from `category_options`), min/max amount `Input`s, and a "Clear filters" button. Controlled by the `filters` prop; exposes an `onChange(filters)` callback. (URL wiring added in US3.)
- [X] T014 [US2] Render `<TransactionFilters>` in `resources/js/pages/transactions/index.tsx` above the table, wired to local state seeded from the `filters` prop. (Submitting/persisting handled in US3 — for now it can call a placeholder that triggers a plain visit.)

**Checkpoint**: US2 functional — filters narrow results with AND semantics and clearing restores the full list.

---

## Phase 5: User Story 3 - Shareable & persistent filters via the URL (Priority: P2)

**Goal**: Hydrate filters from URL query params on load and rewrite the query string on every change (reset to page 1), without a full navigation.

**Independent Test**: Open `/transactions?...filters...` and confirm controls + table reflect them; change a filter and confirm the URL updates and page resets to 1; reload/share reproduces the same results; a malformed param loads without error.

- [X] T015 [US3] In `transaction-filters.tsx` / `index.tsx`, wire `onChange` to issue an Inertia visit to the Wayfinder `index()` route from `@/routes/transactions` via `router.get(index.url({ query }), {}, { preserveState: true, preserveScroll: true, replace: true })`, omitting `page` so it resets to 1. Drop any empty/null filter keys from the query so cleared filters disappear from the URL (FR-010a, FR-012).
- [X] T016 [US3] Confirm initial hydration: since the controls are seeded from the server-echoed `filters` prop (T012/T014) and the controller reads params via `TransactionFilterRequest`, verify the page renders pre-filtered from a query-string URL and that an invalid param (e.g. `start=notadate`, `merchant_id=999999`) is ignored without error (FR-011, FR-013). Adjust seeding if any param fails to round-trip.

**Checkpoint**: All user stories complete — filters are shareable, reloadable, and bookmarkable.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final quality gates and manual verification.

- [X] T017 [P] Run PHP formatting: `./vendor/bin/sail composer exec -- pint --dirty --format agent` and resolve any issues in the new/modified PHP files.
- [X] T018 [P] Run frontend gates: `./vendor/bin/sail npm run lint && ./vendor/bin/sail npm run format && ./vendor/bin/sail npm run types:check` and resolve any issues.
- [ ] T019 Manual verification per `quickstart.md`: browse, each filter, combined filters, AND semantics, URL round-trip, page reset on filter change, second-user isolation, and empty state. **PENDING DEVELOPER**: requires running `./vendor/bin/sail npm run dev` and an authenticated browser session; cannot be completed headlessly (Constitution Principle II — manual verification by the developer). Backend filter sanitization was already verified via tinker in Phase 5.

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: T001, T002 — independent, parallelizable.
- **Foundational (Phase 2)**: T003 → T004 → (T005, T006, T007) → T008. Blocks all stories.
- **US1 (Phase 3)**: T009 — depends on Phase 2. Delivers the MVP on its own.
- **US2 (Phase 4)**: T010 → T011 → T012; T013 → T014. Depends on Phase 2; independent of US1 but shares the same page file (coordinate edits to `index.tsx`).
- **US3 (Phase 5)**: T015 → T016. Depends on US2 (filter bar) being present.
- **Polish (Phase 6)**: after all implementation.

### Story completion order

P1 MVP = Phase 2 + US1 (T003–T009). Then US2 (filtering) completes the second P1 story. US3 (P2) layers URL persistence on top.

## Parallel Execution Examples

- Phase 1: T001 and T002 together (different files).
- Phase 2: T007 in parallel with T005/T006 (distinct prop builders) once T004 exists.
- Phase 6: T017 and T018 together (PHP vs frontend tooling).

## Implementation Strategy

1. **MVP**: Phases 1–3 (T001–T009) — a working, paginated transactions table.
2. **Increment 2**: Phase 4 (T010–T014) — filtering.
3. **Increment 3**: Phase 5 (T015–T016) — URL persistence/shareability.
4. **Finalize**: Phase 6 quality gates + manual verification.
