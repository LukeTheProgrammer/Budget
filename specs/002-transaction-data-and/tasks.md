---
description: "Task list for Transaction Data & Merchant-Category Spending Analysis schema"
---

# Tasks: Transaction Data & Merchant-Category Spending Analysis

**Input**: Design documents from `/specs/002-transaction-data-and/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/schema.md

**Tests**: OMITTED. Per Constitution Principle II (No Automated Tests), no test tasks are
generated. Verification is manual via `artisan migrate` + tinker / `database-schema`.

**Scope**: Database schema only — migrations, Eloquent models, factories, optional
seeder. Run Artisan via `./vendor/bin/sail`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3 (maps to spec.md user stories)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Generate model/migration/factory scaffolding for all entities.

- [X] T001 [P] Generate Account scaffolding: `./vendor/bin/sail artisan make:model Account -mf`
- [X] T002 [P] Generate Merchant scaffolding: `./vendor/bin/sail artisan make:model Merchant -mf`
- [X] T003 [P] Generate Category scaffolding: `./vendor/bin/sail artisan make:model Category -mf`
- [X] T004 [P] Generate Transaction scaffolding: `./vendor/bin/sail artisan make:model Transaction -mf`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Confirm the baseline DB is reachable before writing schema.

**⚠️ CRITICAL**: Complete before any user story phase.

- [X] T005 Verify Sail MySQL container is up and baseline migrations apply: `./vendor/bin/sail up -d && ./vendor/bin/sail artisan migrate`

**Checkpoint**: Database reachable — user story implementation can begin.

---

## Phase 3: User Story 1 - Record credit card transactions (Priority: P1) 🎯 MVP

**Goal**: Persist transactions tied to an account and a (nullable) merchant, with signed
cents amounts and idempotent-import dedupe.

**Independent Test**: Create an account + merchant, add transactions (incl. a negative
refund and a duplicate `import_hash`), confirm persistence, the duplicate is rejected,
and `account->transactions` / `transaction->merchant` resolve.

### Implementation for User Story 1

- [X] T006 [P] [US1] Write `accounts` migration in `database/migrations/*_create_accounts_table.php` per data-model.md (user_id FK cascade, name, last_four, currency default 'USD', timestamps, softDeletes)
- [X] T007 [P] [US1] Write `merchants` migration in `database/migrations/*_create_merchants_table.php` with user_id FK cascade, name, normalized_name, timestamps, UNIQUE(user_id, normalized_name) — **no category_id yet** (added in US2)
- [X] T008 [US1] Write `transactions` migration in `database/migrations/*_create_transactions_table.php` (account_id FK cascade, merchant_id FK nullable nullOnDelete, amount_cents bigInteger signed, currency, description, posted_at date, import_hash, timestamps, softDeletes, UNIQUE(account_id, import_hash), INDEX(account_id, posted_at), INDEX(merchant_id)) — depends on T006, T007
- [X] T009 [P] [US1] Implement `app/Models/Account.php`: `user()` belongsTo, `transactions()` hasMany, SoftDeletes, `$fillable`
- [X] T010 [P] [US1] Implement `app/Models/Merchant.php`: `user()` belongsTo, `transactions()` hasMany, `normalized_name` mutator (`strtolower(trim(name))`), `$fillable`
- [X] T011 [US1] Implement `app/Models/Transaction.php`: `account()` and `merchant()` belongsTo, SoftDeletes, casts (`amount_cents`→integer, `posted_at`→date), `$fillable`
- [X] T012 [US1] Add relations to `app/Models/User.php`: `accounts()` hasMany, `merchants()` hasMany
- [X] T013 [P] [US1] Flesh out `database/factories/AccountFactory.php` (faker name, last_four, currency 'USD')
- [X] T014 [P] [US1] Flesh out `database/factories/MerchantFactory.php` (company name → name + normalized_name)
- [X] T015 [P] [US1] Flesh out `database/factories/TransactionFactory.php` (signed amount_cents, recent posted_at, currency)
- [X] T016 [US1] Run `./vendor/bin/sail artisan migrate` and manually verify per quickstart.md step 5

**Checkpoint**: Transactions can be recorded, deduped, and read back. MVP complete.

---

## Phase 4: User Story 2 - Categorize merchants (Priority: P2)

**Goal**: Add categories and map each merchant to one category; transactions derive their
category through their merchant; null = Uncategorized.

**Independent Test**: Create a category, assign a merchant, confirm
`transaction->category` resolves and an unassigned merchant's transactions report as
Uncategorized.

### Implementation for User Story 2

- [X] T017 [P] [US2] Write `categories` migration in `database/migrations/*_create_categories_table.php` (user_id FK cascade, name, color nullable, timestamps, UNIQUE(user_id, name))
- [X] T018 [US2] Write migration `database/migrations/*_add_category_id_to_merchants_table.php` adding `category_id` nullable FK → categories, nullOnDelete, plus INDEX — depends on T017
- [X] T019 [P] [US2] Implement `app/Models/Category.php`: `user()` belongsTo, `merchants()` hasMany, `$fillable`
- [X] T020 [US2] Extend `app/Models/Merchant.php`: add `category()` belongsTo and include `category_id` in `$fillable`
- [X] T021 [US2] Add `category()` hasOneThrough(Category, Merchant) to `app/Models/Transaction.php` for derived category access
- [X] T022 [US2] Add `categories()` hasMany relation to `app/Models/User.php`
- [X] T023 [P] [US2] Flesh out `database/factories/CategoryFactory.php` (name, hex color)
- [X] T024 [US2] Run `./vendor/bin/sail artisan migrate` and verify merchant→category and Uncategorized fallback in tinker

**Checkpoint**: Merchants categorized; transactions roll up by category.

---

## Phase 5: User Story 3 - Track category spending over time (Priority: P3)

**Goal**: Aggregate per-category spend across date ranges using the join chain from
data-model.md.

**Independent Test**: Seed categorized transactions across multiple months, then run the
spending query and confirm per-period totals equal the underlying sums (incl. NULL =
Uncategorized).

### Implementation for User Story 3

- [X] T025 [US3] Add a `scopeSpendingByCategory($query, $userId, $start, $end)` (or query method) to `app/Models/Transaction.php` implementing the grouped join from data-model.md (transactions → accounts, LEFT JOIN merchants → categories, SUM(amount_cents), group by category)
- [X] T026 [P] [US3] Create `database/seeders/BudgetSeeder.php` generating a user's accounts, categories, merchants, and multi-month transactions via factories
- [X] T027 [US3] Register `BudgetSeeder` in `database/seeders/DatabaseSeeder.php`
- [X] T028 [US3] Run `./vendor/bin/sail artisan db:seed --class=BudgetSeeder` and manually verify per-category monthly totals match the raw transaction sums

**Checkpoint**: All three stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T029 [P] Add PHPDoc type hints / array-shape blocks to all four models per PHP rules
- [X] T030 Run `./vendor/bin/sail bin pint --dirty` and confirm no style violations (Constitution IV)
- [X] T031 Re-run full quickstart.md from a clean DB (`migrate:fresh --seed`) to confirm the schema applies end-to-end

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — all four `make:model` tasks parallel.
- **Foundational (Phase 2)**: After Setup — blocks all stories.
- **US1 (Phase 3)**: After Foundational. MVP.
- **US2 (Phase 4)**: After Foundational. Depends on US1 only for the existing `merchants`
  table (extends it via add-column migration) — otherwise independent.
- **US3 (Phase 5)**: After US2 (needs `categories`/`merchants.category_id` for the join).
- **Polish (Phase 6)**: After desired stories complete.

### Within Each Story

- Migrations before models; models before factories that reference them; migrate + verify
  last.

### Parallel Opportunities

- Phase 1: T001–T004 all parallel.
- US1: T006 & T007 parallel (different migrations); T009 & T010 parallel; factories
  T013–T015 parallel after their models exist.
- US2: T017 & T019 & T023 parallel.

---

## Parallel Example: User Story 1

```bash
# Migrations for independent tables (different files):
Task: "Write accounts migration (T006)"
Task: "Write merchants migration (T007)"

# Models with no interdependency (different files):
Task: "Implement Account model (T009)"
Task: "Implement Merchant model (T010)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 → 4. Verify transactions
   record + dedupe in tinker → MVP ready.

### Incremental Delivery

1. Setup + Foundational → schema scaffolding ready.
2. US1 → record transactions (MVP).
3. US2 → categorize merchants.
4. US3 → spending-over-time reporting.

---

## Notes

- No automated tests (Constitution II) — every checkpoint is manually verified.
- Money is signed integer cents; refunds are negative.
- `merchants.category_id` is intentionally added in US2 via a separate migration to keep
  US1 independently deliverable.
- Commit after each phase; run Pint on any changed PHP before finalizing (Constitution IV).
