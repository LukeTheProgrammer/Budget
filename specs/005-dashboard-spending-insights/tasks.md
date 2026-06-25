---
description: "Task list for Dashboard Spending Insights"
---

# Tasks: Dashboard Spending Insights

**Input**: Design documents from `/specs/005-dashboard-spending-insights/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/dashboard.md

**Tests**: NONE. Per Constitution Principle II (No Automated Tests), no test tasks are generated. Verification is manual in-browser per `quickstart.md`.

**Organization**: Tasks are grouped by user story (priority order from spec.md) so each story is an independently demonstrable increment on the dashboard page.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete tasks)
- **[Story]**: US1/US2/US3/US4 (Setup, Foundational, Polish have no story label)
- All paths are repository-relative; run tooling through `./vendor/bin/sail`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Bring in the approved charting dependency and its primitive.

- [X] T001 Install the approved chart dependency: `./vendor/bin/sail npm install recharts`
- [X] T002 [P] Add the shadcn/ui chart primitive at `resources/js/components/ui/chart.tsx` (ChartContainer/ChartTooltip wrappers around recharts), matching new-york style conventions of sibling `ui/` components

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Controller, route, period resolution, and page scaffold that every user story renders into. MUST complete before US1–US4.

- [X] T003 Generate the controller: `./vendor/bin/sail artisan make:controller DashboardController --no-interaction`, creating `app/Http/Controllers/DashboardController.php` with an `index(Request $request): Response` returning `Inertia::render('dashboard', [...])`
- [X] T004 In `routes/web.php`, replace `Route::inertia('dashboard', 'dashboard')->name('dashboard')` with `Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')` (keep it inside the `auth`,`verified` group; add the `use` import)
- [X] T005 In `app/Http/Controllers/DashboardController.php`, add period resolution: read `?period` (`this_month|last_month|last_3_months`, default `this_month`, invalid → default) and compute the current + previous equal-length date windows and `period_label`/`currency`; pass `period`, `period_label`, `currency` props per `contracts/dashboard.md`
- [X] T006 [P] Add a money/percent formatting helper for the frontend in `resources/js/lib/format.ts` (format `amount_cents` + currency, and signed percent), reusing existing util conventions
- [X] T007 Replace the placeholder body of `resources/js/pages/dashboard.tsx` with a typed `DashboardProps` interface (per `contracts/dashboard.md`) and the responsive grid layout shell that will host the widgets (no widgets wired yet)
- [X] T008 Create `resources/js/components/dashboard/period-selector.tsx` (shadcn `select` or `toggle-group`) that navigates via the Wayfinder `dashboard()` helper with the chosen `period`, and mount it in `dashboard.tsx`

**Checkpoint**: Dashboard page loads under the controller, period selector switches the `?period` param and the page re-renders.

---

## Phase 3: User Story 1 — Period summary at a glance (Priority: P1) 🎯 MVP

**Goal**: Show total spent, transaction count, and change vs. previous period for the selected window.

**Independent Test**: Load `/dashboard` with transactions; confirm total/count/change render and update when the period changes; empty period shows an empty state.

- [X] T009 [US1] Add a `#[Scope]` `spendingSummary(Builder, int $userId, $start, $end)` to `app/Models/Transaction.php` returning summed `amount_cents` and count, joining `accounts`, filtering `accounts.user_id`, `amount_cents > 0`, and the date window (mirror `spendingByCategory`)
- [X] T010 [US1] In `DashboardController@index`, build the `summary` prop using the scope for the current and previous windows; compute `change_percent` as `(current-previous)/previous*100`, or `null` when `previous_total_cents == 0` (FR-012)
- [X] T011 [P] [US1] Create `resources/js/components/dashboard/summary-cards.tsx` rendering total spent, transaction count, and the change indicator (neutral when `change_percent` is null) using shadcn `card`/`badge` and the format helper
- [X] T012 [US1] Wire `summary-cards` into `dashboard.tsx` and add its empty state (zero total / no transactions) per FR-009

**Checkpoint**: US1 is independently demonstrable — the MVP dashboard answers "how much did I spend this period?".

---

## Phase 4: User Story 2 — Spending by category (Priority: P2)

**Goal**: Chart + ranked table of spending per category with amounts and percentage shares.

**Independent Test**: Load `/dashboard` for a user with multi-category transactions; confirm chart + ranked table show totals and percentages, "Uncategorized" appears, and chart consolidates beyond top 8 into "Other".

- [X] T013 [US2] In `DashboardController@index`, build the `categories` prop using the existing `Transaction::spendingByCategory` scope for the current window: map NULL category → "Uncategorized", include `color`, sort desc by `total_cents`, and compute each row's `percent` of the period total (FR-004, FR-005)
- [X] T014 [P] [US2] Create `resources/js/components/dashboard/category-breakdown.tsx`: a recharts pie/donut (or bar) chart consuming top 8 categories + an "Other" aggregate (FR-013), beside a ranked table listing ALL categories with amount and percent
- [X] T015 [US2] Wire `category-breakdown` into `dashboard.tsx` and add its empty state (no spending in period) per FR-009

**Checkpoint**: US2 works on top of US1 — user sees where money goes.

---

## Phase 5: User Story 3 — Spending trend over time (Priority: P2)

**Goal**: 12-month chronological spending trend, zero-filled.

**Independent Test**: Load `/dashboard` for a user with multi-month transactions; confirm the chart plots exactly 12 chronological monthly totals, with empty months shown as zero.

- [X] T016 [US3] Add a `#[Scope]` `monthlySpendingTrend(Builder, int $userId)` to `app/Models/Transaction.php` grouping spending (`amount_cents > 0`) by year-month over the trailing 12 months for the user's accounts
- [X] T017 [US3] In `DashboardController@index`, build the `trend` prop: zero-fill all 12 trailing months in chronological order with `month`/`label`/`total_cents` (FR-006), independent of the selected period
- [X] T018 [P] [US3] Create `resources/js/components/dashboard/spending-trend.tsx` (recharts bar/area chart over the 12 trend points) and wire it into `dashboard.tsx` with an empty/all-zero state

**Checkpoint**: US3 adds trend context; independent of US1/US2.

---

## Phase 6: User Story 4 — Recent & largest transactions (Priority: P3)

**Goal**: Tables of the 10 most recent and 10 largest transactions for the period.

**Independent Test**: Load `/dashboard` with transactions; confirm recent table lists latest 10 by date and largest table lists top 10 by amount, each showing merchant, category, date, amount.

- [X] T019 [US4] Add `#[Scope]` methods `recentSpending(Builder, int $userId, ...)` (latest 10 by `posted_at`) and `largestSpending(Builder, int $userId, $start, $end)` (top 10 by `amount_cents` in period) to `app/Models/Transaction.php`, eager-loading merchant label + category, `amount_cents > 0`
- [X] T020 [US4] In `DashboardController@index`, build `recent_transactions` and `largest_transactions` props as `TransactionRow[]` per `contracts/dashboard.md` (merchant label fallback when unresolved)
- [X] T021 [P] [US4] Create `resources/js/components/dashboard/transactions-table.tsx` (reusable: merchant, category, date, amount) and render two instances (recent + largest) in `dashboard.tsx`, each with an empty state

**Checkpoint**: All four widgets present; dashboard feature-complete.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T022 [P] Verify per-widget loading/empty states and responsive layout across breakpoints in `resources/js/pages/dashboard.tsx` and `resources/js/components/dashboard/*`
- [X] T023 Run quality gates: `./vendor/bin/sail composer run lint` (Pint) and `./vendor/bin/sail npm run lint && ./vendor/bin/sail npm run format && ./vendor/bin/sail npm run types:check` (also confirmed `npm run build` succeeds)
- [ ] T024 Manual verification per `quickstart.md` (developer-run in browser, Constitution II): period switching updates all widgets, figures reconcile with transactions (SC-003), and a no-data user sees empty states with no errors (SC-004)

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → blocks everything.
- **Foundational (T003–T008)** → blocks all user stories (shared controller, route, props scaffold, period selector).
- **User stories** depend only on Foundational, not each other:
  - US1 (T009–T012) — MVP
  - US2 (T013–T015)
  - US3 (T016–T018)
  - US4 (T019–T021)
- Within each story: model scope → controller prop → frontend component → wire/empty state (sequential where same file is touched).
- **Polish (T022–T024)** → after all targeted stories complete.

### Cross-story file contention
`DashboardController.php` and `dashboard.tsx` are touched by every story → those tasks are **not** `[P]` across stories; complete one story's controller/page edits before the next. New per-widget component files are `[P]`.

## Parallel Opportunities

- T002 ∥ (after T001).
- Within a story, the new component file is `[P]` with its model scope (different files): e.g. T011 ∥ T009/T010; T014 ∥ T013; T018 after T016/T017; T021 ∥ T019/T020.
- If multiple developers: once Foundational is done, US1/US2/US3/US4 backends (model scopes T009, T016, T019 and category mapping) can proceed in parallel since scopes are independent additions to `Transaction.php` (coordinate the single file) while component files are fully parallel.

## Implementation Strategy

- **MVP** = Phase 1 + Phase 2 + **US1** (T001–T012): a working dashboard showing the period summary with a functioning period selector.
- Then layer US2 → US3 → US4 in priority order, each an independently demonstrable increment.
- Finish with Polish (quality gates + manual verification).
