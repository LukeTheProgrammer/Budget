---
description: "Task list for Deposits and Transfers (012-transaction-flow-types)"
---

# Tasks: Deposits and Transfers

**Input**: Design documents from `/specs/012-transaction-flow-types/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/http.md, quickstart.md

**Tests**: No test tasks. Constitution Principle II forbids automated tests in this project; verification is manual per `quickstart.md`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story the task belongs to (US1, US2, US3)
- Exact file paths are given in each task

## Path Conventions

Laravel + Inertia monorepo at the repository root: PHP in `app/`, migrations in `database/migrations/`, routes in `routes/web.php`, React in `resources/js/`. Run all Artisan/npm commands through `./vendor/bin/sail`.

---

## Phase 1: Setup

**Purpose**: The enums and shared vocabulary every later phase depends on.

- [X] T001 [P] Create `App\Enums\FlowType` in `app/Enums/FlowType.php` — backed string enum with cases `Expense = 'expense'`, `Income = 'income'`, `Transfer = 'transfer'`, `Refund = 'refund'`; a `label()` method and static `options()` returning value/label pairs (mirror `app/Enums/AccountType.php`); and a static `spendingCases(): array` returning `[self::Expense, self::Refund]` so the spending predicate is defined in exactly one place.
- [X] T002 [P] Create `App\Enums\FlowTypeSource` in `app/Enums/FlowTypeSource.php` — backed string enum with cases `Auto = 'auto'` and `User = 'user'`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models, and the classifier. Every user story depends on all of this. No story can start until Phase 2 is done.

- [X] T003 Create the transactions migration via `./vendor/bin/sail artisan make:migration add_flow_type_to_transactions_table` — add `flow_type` enum column (`expense`/`income`/`transfer`/`refund`, NOT NULL, default `expense`), `flow_type_source` enum column (`auto`/`user`, NOT NULL, default `auto`), and nullable self-referencing `transfer_pair_id` foreign key on `transactions.id` with `nullOnDelete`. Add indexes `['account_id', 'flow_type', 'posted_at']` and `['flow_type', 'posted_at']`.
- [X] T004 [P] Create the merchants migration via `./vendor/bin/sail artisan make:migration add_default_flow_type_to_merchants_table` — add nullable `default_flow_type` enum column with the same four values.
- [X] T005 Update `app/Models/Transaction.php` — cast `flow_type` to `FlowType` and `flow_type_source` to `FlowTypeSource`; add `flow_type`, `flow_type_source`, `transfer_pair_id` to the `#[Fillable]` attribute; add a `transferPair(): BelongsTo<Transaction, $this>` relation.
- [X] T006 [P] Update `app/Models/Merchant.php` — cast `default_flow_type` to `FlowType`, add it to `#[Fillable]`, and add a `withExpenseActivity()` scope limiting to merchants having at least one transaction whose `flow_type` is in `FlowType::spendingCases()`.
- [X] T007 Create `app/Services/Transactions/FlowTypeClassifier.php` implementing the algorithm in `research.md`: `forUser(int $userId)` primes an in-memory set of merchant ids that already have expense activity (mirroring `NameResolver::forUser()`, so classification stays O(rows) with no per-row queries); `classify(Account $account, Merchant $merchant, int $amountCents, string $description): FlowType` applies, in order — the merchant's `default_flow_type` if set; then for outflows, a transfer/payment descriptor match (private constant list: transfer, xfer, online transfer, payment thank you, autopay, epay, bill pay, zelle, withdrawal to) or a counterpart that is one of the user's own accounts → `Transfer`, else `Expense`; then for inflows, a descriptor match → `Transfer`, an account of type `credit` → `Refund`, a merchant with prior expense activity → `Refund`, else `Income`.
- [X] T008 Create `app/Services/Transactions/TransferPairer.php` — `pairForUser(int $userId): int` selects the user's unpaired `transfer` transactions and links each to a candidate with equal absolute amount, opposite sign, same currency, a different account of the same user, and `posted_at` within 3 days; the closest-dated candidate wins, ties broken by id; each transaction takes at most one partner; `transfer_pair_id` is written on both rows. Must be idempotent — re-running never double-links. Also expose `unpair(Transaction $transaction): void` to clear both sides of a pair.
- [X] T009 Wire classification into `app/Services/Transactions/TransactionRowStore.php` — inject `FlowTypeClassifier`, prime it in `forUser()` alongside the name resolver, and in `store()` classify the row and include `flow_type` in the persisted attributes. Critically, when `updateOrCreate` hits an existing row whose `flow_type_source` is `user`, do NOT overwrite `flow_type`/`flow_type_source` (FR-011) — classify only for newly created rows and for existing rows whose source is `auto`.
- [X] T010 [P] Wire classification into `app/Services/Plaid/PlaidTransactionSync.php` — same rule as T009 in its `upsert()` method: classify before persisting, and never overwrite a user-set flow type on re-sync.
- [X] T011 Run `TransferPairer::pairForUser()` once at the end of each import batch — after the row loop in the import/upload controllers that drive `TransactionRowStore`, and at the end of `PlaidTransactionSync`'s sync loop. (Per-row pairing cannot work: the counterpart leg may not be imported yet.)

**Checkpoint**: Schema, models, classifier, and pairer exist and every import path assigns a flow type. Nothing user-visible has changed yet.

---

## Phase 3: User Story 1 — Deposits and transfers stop polluting spending (Priority: P1) 🎯 MVP

**Goal**: Spending figures count expenses and refunds only. Deposits and transfers appear in none of them, and the existing data is corrected without a re-import.

**Independent test**: Import a checking statement with one deposit, one transfer out, one credit-card payment, and three purchases. The dashboard spending total equals only the three purchases; the deposit and both transfers appear in no spending total, category breakdown, budget, or trend.

- [X] T012 [US1] Rewrite the spending predicate in `app/Models/Transaction.php` — in the `spendingByCategory`, `spendingSummary`, and `monthlySpendingTrend` scopes, replace `where('transactions.amount_cents', '>', 0)` with `whereIn('transactions.flow_type', FlowType::spendingCases())`. Because refunds are stored negative, `SUM(amount_cents)` now nets them out of every category and period total automatically (FR-006). Update each scope's docblock, which currently states that only positive amounts count.
- [X] T013 [US1] In the same file, change the `recentSpending` and `largestSpending` scopes to `where('transactions.flow_type', FlowType::Expense)` — a refund is not a "largest expense", so these two intentionally differ from the summing scopes above. Update their docblocks.
- [X] T014 [P] [US1] Update `app/Http/Controllers/BudgetController.php` — replace the `amount_cents > 0` predicate at both call sites (lines ~89 and ~141) with the `FlowType::spendingCases()` predicate, so budget consumption nets refunds and ignores income and transfers (FR-014).
- [X] T015 [P] [US1] Update `app/Http/Controllers/InsightsController.php` — apply the same flow-type predicate anywhere it computes spending.
- [X] T016 [P] [US1] Update `app/Http/Controllers/Merchants/MerchantController.php` — the merchant total subqueries (lines ~47 and ~112) currently sum every transaction with no sign filter, so transfers land straight in merchant totals. Constrain them to `FlowType::spendingCases()`.
- [X] T017 [US1] Create the backfill command via `./vendor/bin/sail artisan make:command ClassifyTransactionFlowTypes` — signature `transactions:classify-flow-types {--user=}`. It reclassifies every transaction whose `flow_type_source` is `auto` (never touching user-set rows), chunking through each user's transactions with the classifier primed per user, then runs `TransferPairer::pairForUser()` for each affected user. Idempotent; reports a count per flow type on completion (FR-016).
- [X] T018 [US1] Create `resources/js/components/flow-type-badge.tsx` — a small shared component rendering a transaction's flow type as a badge (reuse the existing shadcn `badge` primitive in `resources/js/components/ui/`) with a distinct colour per type, plus a helper for direction-aware amount formatting so money-in and money-out are visually obvious rather than relying on a bare sign (FR-018).
- [X] T019 [US1] Surface the flow type in `app/Http/Controllers/Transactions/TransactionController.php` — add `flow_type`, `flow_type_source`, and `is_paired_transfer` (i.e. `transfer_pair_id !== null`) to each row of the transactions payload, and pass `flow_type_options` from `FlowType::options()` to the page.
- [X] T020 [US1] Render the flow type in `resources/js/pages/transactions.tsx` — add a flow-type column using `FlowTypeBadge`, and use the direction-aware amount formatting so deposits read as money in.
- [X] T021 [US1] Add the "show non-expense merchants" behaviour: apply the `withExpenseActivity()` scope by default in `MerchantController@index`, lift it when `?include_non_expense=1` is present, echo `include_non_expense` back as a prop, and add the toggle to `resources/js/pages/merchants.tsx` so payroll and internal-transfer pseudo-merchants stop cluttering the spending-management page (FR-015).
- [ ] T022 [US1] Run the migrations and backfill: `./vendor/bin/sail artisan migrate` then `./vendor/bin/sail artisan transactions:classify-flow-types`. Verify against the real checking-account data per `quickstart.md` steps 1, 2, and 7 — hand-tally the period's purchases and confirm the dashboard spending total matches exactly (SC-001).

**Checkpoint**: US1 is a complete, shippable increment. Spending numbers are correct for a checking account; deposits and transfers are visible but excluded from the math.

---

## Phase 4: User Story 2 — Correcting a misclassified transaction (Priority: P2)

**Goal**: The user can change any transaction's flow type; the correction sticks across re-imports and teaches future imports via the merchant default.

**Independent test**: Change a transaction the app called a transfer into an expense and confirm the dashboard spending total rises by that amount; re-import the same file and confirm the correction survives.

- [X] T023 [P] [US2] Create `app/Http/Requests/Transactions/UpdateFlowTypeRequest.php` — validate `flow_type` as required with `Rule::enum(FlowType::class)` and `apply_to_merchant` as a boolean defaulting to `true`. Authorize that the transaction's account belongs to the authenticated user, following the pattern in the existing `Transactions` form requests.
- [X] T024 [US2] Create `app/Http/Controllers/Transactions/TransactionFlowTypeController.php` with an `update()` action — set `flow_type` and `flow_type_source = FlowTypeSource::User`; when `apply_to_merchant` is true, also write `merchants.default_flow_type` so future imports of the same merchant classify the same way (FR-010); when the transaction leaves the `transfer` type, unpair both legs via `TransferPairer::unpair()`; when it enters `transfer`, re-run `pairForUser()`. Return `back()`, matching `TransactionTagController`.
- [X] T025 [US2] Register the route in `routes/web.php` inside the existing `auth`/`verified` group: `Route::patch('transactions/{transaction}/flow-type', [TransactionFlowTypeController::class, 'update'])->name('transactions.flow-type.update');`. Wayfinder regenerates the typed helper on the next dev/build — do not hand-edit `resources/js/actions/` or `resources/js/routes/`.
- [X] T026 [US2] Add the inline flow-type editor to `resources/js/pages/transactions.tsx` — a select (existing shadcn `select` primitive) on each row, populated from `flow_type_options`, submitting to the Wayfinder-generated action from `@/actions`. Include the "apply to this merchant in future" affordance, defaulted on.
- [ ] T027 [US2] Verify per `quickstart.md` step 5: correct a row, confirm totals update with no re-import; re-import the same statement and confirm the correction survives; import a later statement containing the same recurring merchant and confirm it arrives already classified (SC-007).

**Checkpoint**: Classification is trustworthy — anything the classifier gets wrong, the user fixes once and only once.

---

## Phase 5: User Story 3 — Seeing income and net cash flow (Priority: P3)

**Goal**: The user can answer "how much came in, how much went out, what's left" from one dashboard view, and can filter the transactions list by flow type.

**Independent test**: With a month containing $4,000 of deposits and $3,100 of purchases, the dashboard reports income $4,000, spending $3,100, net +$900 — and transfers in that month move none of the three.

- [X] T028 [US3] Add an `incomeSummary(int $userId, $start, $end)` scope to `app/Models/Transaction.php` — a `#[Scope]` method matching the style of the neighbouring scopes, summing `flow_type = 'income'` over the date range and returning a positive magnitude (inflows are stored negative, so negate the sum).
- [X] T029 [US3] Add the `cash_flow` prop to `app/Http/Controllers/DashboardController.php` — `{ income_cents, spending_cents, net_cents }` for the selected period, where spending reuses the existing `spendingSummary` scope and `net_cents = income_cents - spending_cents` (may be negative).
- [X] T030 [US3] Render income, spending, and net cash flow on `resources/js/pages/dashboard.tsx` — reuse the existing summary-card layout and the direction-aware formatting from `FlowTypeBadge`'s helper; make a negative net visually distinct from a positive one.
- [X] T031 [P] [US3] Add flow-type filtering to `app/Http/Requests/Transactions/TransactionFilterRequest.php` — an optional `flow_type` array parameter validated against the enum, mapped into the filter payload as `flow_types`.
- [X] T032 [US3] Consume the filter in the `filter()` scope in `app/Models/Transaction.php` — `whereIn('transactions.flow_type', $filters['flow_types'])` when the key is present. Absent means no flow-type filtering, so all types remain listed (FR-017). Update the scope's docblock, which enumerates the supported filter keys.
- [X] T033 [US3] Add the flow-type filter control to `resources/js/pages/transactions.tsx`, alongside the existing filters.
- [ ] T034 [US3] Verify per `quickstart.md` steps 3 and 4.

---

## Phase 6: Polish & Cross-Cutting

- [ ] T035 Verify transfer pairing end to end per `quickstart.md` step 6 — import statements for two accounts sharing the same internal transfer, confirm both legs are marked transfer, both are excluded from spending and income, and both carry `transfer_pair_id`; re-run the backfill and confirm no double-linking.
- [X] T036 [P] Show the paired-transfer relationship in `resources/js/pages/transactions.tsx` — indicate on a paired row that it is one leg of a matched internal transfer (using `is_paired_transfer`), so the user can see the money was accounted for on both sides.
- [X] T037 Run the quality gates required by Constitution Principle IV: `./vendor/bin/sail composer run lint` (Pint), `./vendor/bin/sail npm run lint`, `./vendor/bin/sail npm run types:check`, `./vendor/bin/sail npm run format`.
- [ ] T038 Walk `quickstart.md` end to end against the real checking-account data to confirm all seven success criteria, particularly SC-002 (≥90% of statement rows classified correctly without intervention) — and tune the descriptor list in `FlowTypeClassifier` if the real statement falls short.

---

## Dependencies

**Phase order**: Setup (T001–T002) → Foundational (T003–T011) → US1 (T012–T022) → US2 (T023–T027) → US3 (T028–T034) → Polish (T035–T038).

**Blocking**: Phase 2 is a hard gate — every user story reads the `flow_type` column, so nothing in Phase 3+ can start before T011.

**Story independence**: US1 is self-contained and shippable alone (the MVP). US2 and US3 each depend only on the foundation plus US1's payload/predicate changes; they do not depend on each other and could be built in either order, or in parallel by two people.

**Within-phase notes**:
- T003 must precede T005 (the model casts need the columns).
- T007 must precede T009, T010, and T017 (all three call the classifier).
- T019 must precede T020, T026, T033, and T036 (they all consume the row payload).
- T024 depends on T008 (`unpair`/`pairForUser`) and T023 (the form request).

## Parallel Opportunities

- **Setup**: T001 and T002 together (two new files).
- **Foundational**: T004 alongside T003; T006 alongside T005; T010 alongside T009 (different services).
- **US1**: T014, T015, and T016 together — three different controllers, each doing the same mechanical predicate swap. T018 (the badge component) is independent of all the backend work.
- **US3**: T031 alongside T028.

## Implementation Strategy

**MVP = Phase 1 + Phase 2 + Phase 3 (US1)**, ending at T022. That alone fixes the user's actual complaint: the checking account stops corrupting every spending number in the app. Ship and use it before building US2 and US3.

Then layer US2 (make corrections stick — this is what makes the classifier's inevitable 10% error rate tolerable), then US3 (surface income and net cash flow, the payoff for capturing deposits properly).
