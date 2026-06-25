---
description: "Task list for CSV Transaction Import"
---

# Tasks: CSV Transaction Import

**Input**: Design documents from `/specs/003-csv-transaction-import/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/service.md

**Tests**: OMITTED. Constitution Principle II prohibits automated tests in this project;
verification is manual per `quickstart.md`.

**Organization**: Tasks are grouped by user story. The schema (spec 002) is already
migrated, so there is no schema work here.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- File paths are repository-relative.

## Path Conventions

Web application (Laravel back-end). Source under `app/`, config under `config/`, routes
under `routes/`. Create PHP files via Artisan generators where one exists.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration the import depends on across all entry points.

- [X] T001 Create `config/transactions.php` returning `['default_account_id' => env('TRANSACTIONS_DEFAULT_ACCOUNT_ID')]` and document the var in `.env.example` (per research.md R6).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The reusable service and its supporting structures that every entry point
(US1) and every other story depend on. **No user story can be completed until this phase
is done.**

- [X] T002 [P] Create value object `app/Services/Transactions/ChaseCsvRow.php` with promoted readonly properties (`lineNumber`, `postedAt`, `description`, `merchantName`, `amountCents`, `type`) per data-model.md.
- [X] T003 [P] Create summary DTO `app/Services/Transactions/ImportResult.php` (`file`, `imported`, `skipped`, `failed`, `failures[]`, `archived`) with a method to record a failure (`{line, reason}`) and an `incrementImported/Skipped` helper, per data-model.md.
- [X] T004 [P] Create `app/Services/Transactions/ImportException.php` extending `RuntimeException` (per contracts/service.md error type).
- [X] T005 Create `app/Services/Transactions/CsvTransactionImporter.php` skeleton with `importFile(string $relativePath): ImportResult` and `importAll(): array` signatures, constructor resolving the default `Account` from `config('transactions.default_account_id')` and throwing `ImportException` when missing (C8). Depends on T001–T004.

**Checkpoint**: Service surface exists; US1 implementation can begin.

---

## Phase 3: User Story 1 - Import transactions from a CSV file (Priority: P1) 🎯 MVP

**Goal**: Read a Chase-format CSV from `storage/app/private/` and persist each valid row
as a `Transaction` with a resolved/created `Merchant`, reporting imported/skipped/failed.

**Independent Test**: Run `artisan transactions:import <file>` on a known file and confirm
every valid row is stored with correct amount, date, and merchant, and a summary is shown.

- [X] T006 [US1] In `CsvTransactionImporter::importFile`, open the file from the `local` disk via `SplFileObject`/`fgetcsv`; throw `ImportException` if missing/unreadable, and validate the header equals `Transaction Date,Post Date,Description,Category,Type,Amount,Memo`, else throw (C1, FR-003a). File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T007 [US1] Add a `parseRow(array $columns, int $lineNumber): ChaseCsvRow` step: parse `Post Date` as `MM/DD/YYYY` → `postedAt`, build `description` (append `Memo` when non-empty), set `merchantName` from `Description`, parse `Amount` (strip currency/thousands separators, allow leading `-`) and compute `amountCents = round(value * -100)` (sign inverted, R3); throw a row-level validation error on bad date, empty description, or zero/non-numeric amount. File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T008 [US1] Add merchant resolution `Merchant::firstOrCreate(['user_id' => $account->user_id, 'normalized_name' => mb_strtolower(trim($row->merchantName))], ['name' => $row->merchantName])` (R5, FR-004); reuse the existing model's `name`→`normalized_name` mutator. File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T009 [US1] Compute `import_hash = hash('sha256', "{account_id}|{postedAt:Y-m-d}|{amountCents}|{normalized_name}")` and create the `Transaction` (account_id, merchant_id, amount_cents, currency from account, description, posted_at, import_hash) via the fillable model (R4, FR-003/005/006). File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T010 [US1] Wrap the per-row loop so each row independently increments `imported` on success and records a `failed` entry (line + reason) on any exception without aborting the file (FR-008, FR-012); return the populated `ImportResult`. File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T011 [US1] Generate the Artisan command via `artisan make:command` → `app/Console/Commands/ImportTransactionsCommand.php`, signature `transactions:import {file? : relative path} {--all}`, call `importFile`/`importAll`, render a per-file summary table and list failed lines, return non-zero only on hard failure (contracts/service.md entry point 1).

**Checkpoint**: A single file imports end-to-end from the CLI — MVP is functional.

---

## Phase 4: User Story 2 - Skip duplicate transactions on re-import (Priority: P2)

**Goal**: Re-importing overlapping data creates no duplicate transactions; overlapping
rows are reported as skipped.

**Independent Test**: Import the same file twice; the second run reports `imported=0`,
`skipped=N`, and `Transaction::count()` is unchanged.

- [X] T012 [US2] Before insert in `CsvTransactionImporter`, short-circuit when a transaction with the same `(account_id, import_hash)` already exists → increment `skipped` instead of inserting (FR-007, C4). File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T013 [US2] Guard the insert against the `UNIQUE(account_id, import_hash)` race by catching the unique-constraint violation (`QueryException`) and counting it as `skipped` rather than `failed` (R4, IM1). File: `app/Services/Transactions/CsvTransactionImporter.php`.

**Checkpoint**: Re-import is idempotent; depends on US1 insert path (T009).

---

## Phase 5: User Story 3 - Initiate import from multiple entry points (Priority: P2)

**Goal**: The same import behavior is reachable from a queued Job and an HTTP endpoint, in
addition to the CLI, with identical results.

**Independent Test**: Trigger the same file via the Job and via `POST /transactions/import`
and confirm the resulting `ImportResult` matches the CLI run.

- [X] T014 [P] [US3] Implement `importAll()` in `CsvTransactionImporter`: discover `*.csv`/`*.CSV` in the private disk root (excluding `processed/`) via `Storage::disk('local')->files()`, run `importFile` per file, capturing a throwing file as a failed `ImportResult` without stopping the batch (R7, FR-010). File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T015 [P] [US3] Generate the job via `artisan make:job` → `app/Jobs/ImportTransactionsFile.php`: constructor takes a relative path, `handle(CsvTransactionImporter $importer)` calls `importFile` and logs the summary (contracts/service.md entry point 2).
- [X] T016 [US3] Generate the controller via `artisan make:controller` → `app/Http/Controllers/Transactions/ImportController.php`; add a Form Request validating `file` (string) / `all` (bool); dispatch `ImportTransactionsFile` (or run `importAll`) and return an Inertia redirect with a flash summary plus JSON support (contracts/service.md entry point 3, SC-004).
- [X] T017 [US3] Register `POST /transactions/import` → `ImportController` behind auth in `routes/web.php` (regenerates Wayfinder helpers on build).

**Checkpoint**: All three triggers share the one service and produce identical outcomes.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T018 In `CsvTransactionImporter`, after a file is fully read without an aborting error, move it to `storage/app/private/processed/` (create dir if absent) via `Storage::disk('local')->move()` and set `ImportResult.archived = true`; leave rejected/errored files in place (FR-013, R8). File: `app/Services/Transactions/CsvTransactionImporter.php`.
- [X] T019 [P] Run `vendor/bin/pint --dirty` on all new PHP and `npm run lint`/`types:check` if any front-end trigger UI was added (Constitution Principle IV).
- [ ] T020 Manually verify against `quickstart.md` steps 1–6 (single import, data/sign check, idempotent re-import, batch, bad-row resilience, job + HTTP trigger).

---

## Dependencies & Execution Order

- **Setup (T001)** → **Foundational (T002–T005)** must complete before any story.
- **US1 (T006–T011)** depends on Foundational; delivers the MVP.
- **US2 (T012–T013)** depends on US1's insert path (T009).
- **US3 (T014–T017)** depends on US1's `importFile` (T006–T010); T014/T015 are parallel.
- **Polish (T018–T020)**: T018 builds on US1; T019–T020 run last.

Story completion order: **US1 → US2 → US3** (US2 and US3 are independent of each other
and could be built in either order after US1).

## Parallel Opportunities

- Foundational: **T002, T003, T004** in parallel (separate files), then T005.
- US3: **T014** and **T015** in parallel (service method vs. job file).
- Polish: **T019** parallel with documentation/verification prep.

## Implementation Strategy

- **MVP = Phase 1 + Phase 2 + Phase 3 (US1)**: a working `transactions:import <file>`.
- Add **US2** for safe re-imports, then **US3** for job/HTTP triggers.
- Finish with **Polish** (file archiving, quality gates, manual verification).
