# Tasks: Upload Transaction Files

**Feature**: `011-upload-transaction-files` | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

**Note**: No automated tests (Constitution Principle II — verification is manual via [quickstart.md](./quickstart.md)). Run all commands inside Sail. Run Pint + ESLint/Prettier/TypeScript before finalizing (Principle IV).

**Paths** are repo-relative from `/home/luke/Dev/budget`.

## Phase 1: Setup

- [X] T001 Create the migration for the new table via `./vendor/bin/sail artisan make:migration create_saved_import_mappings_table --no-interaction`, then define columns per [data-model.md](./data-model.md) in `database/migrations/XXXX_create_saved_import_mappings_table.php`: `id`, `user_id` (FK→users, cascade), `account_id` (FK→accounts, cascade), `mapping` (json), timestamps, and a unique index on (`user_id`, `account_id`). Run `./vendor/bin/sail artisan migrate`.
- [X] T002 [P] Create the model via `./vendor/bin/sail artisan make:model SavedImportMapping --no-interaction` and configure `app/Models/SavedImportMapping.php`: `#[Fillable(['user_id', 'account_id', 'mapping'])]`, cast `mapping` to `array`, and `belongsTo(User)` / `belongsTo(Account)` relations with explicit return types.

## Phase 2: Foundational (blocking prerequisites)

**Goal**: Extract the reusable storage engine and wire routes so all stories share one dedup/merchant path. Blocks Phase 3+.

- [X] T003 Create the value object `app/Services/Transactions/NormalizedTransactionRow.php` as a `final readonly` class with `lineNumber:int`, `postedAt:CarbonImmutable`, `description:string`, `merchantName:string`, `amountCents:int`, `currency:string` (mirror the shape of `ChaseCsvRow.php`).
- [X] T004 Extract row storage into `app/Services/Transactions/TransactionRowStore.php`: move `storeRow`, `resolveMerchant`, `backfillCategory`, `resolveCategory`, `createMerchantWithAlias`, `applyDefaultTags`, and the `import_hash` computation out of `CsvTransactionImporter.php`. Expose `store(Account $account, NormalizedTransactionRow $row, ImportResult $result): void` and inject `NameResolver` via the constructor. Keep `import_hash` = `sha256(account_id | postedAt(Y-m-d) | amountCents | merchant_id)` identical so cross-path dedup holds (FR-012).
- [X] T005 Refactor `app/Services/Transactions/CsvTransactionImporter.php` to depend on `TransactionRowStore` (delegate `storeRow`), constructing a `NormalizedTransactionRow` from each `ChaseCsvRow`. Behavior must be unchanged for the legacy Chase/back-end path; call `$this->nameResolver->forUser()` before storing.
- [X] T006 Add the upload routes to `routes/web.php` inside the authenticated `transactions` group: `GET transactions/upload` → `UploadController@create` (name `transactions.upload.create`) and `POST transactions/upload` → `UploadController@store` (name `transactions.upload.store`). Import the new `UploadController`.

**Checkpoint**: `./vendor/bin/sail artisan route:list --path=transactions` shows the two new routes; legacy import still functions.

## Phase 3: User Story 1 - Upload, map, and import (Priority: P1) 🎯 MVP

**Goal**: A user selects an account + CSV file, maps headers to fields, confirms, and rows import to the account with correct dates/signs and a result summary.

**Independent test**: Per [quickstart.md](./quickstart.md) "Happy path" — upload a CSV, map columns, import, verify transactions appear with correct values and a summary.

- [X] T007 [US1] Create `app/Services/Transactions/MappedCsvImporter.php`: `importUpload(UploadedFile $file, Account $account, array $mapping): ImportResult`. Parse the header row + data rows with `SplFileObject` (READ_CSV flags, as in `CsvTransactionImporter`), resolve each field's column index from `mapping['fields']`, build `NormalizedTransactionRow`s, and delegate to `TransactionRowStore::store`. Call `NameResolver::forUser($account->user_id)` once up front. Record per-row failures via `ImportResult::recordFailure` without aborting (FR-014).
- [X] T008 [US1] In `MappedCsvImporter`, implement value parsing helpers: tolerant date parsing (try `Y-m-d`, `m/d/Y`, `d/m/Y`, `m/d/y`, then `CarbonImmutable::parse`, honoring optional `mapping['date_format']`) per research D6; amount-to-signed-cents with `mapping['amount_sign']` (`as_is` | `invert`) per research D5; currency from the mapped column else `$account->currency`. Throw per-row on unparseable date/amount (FR-010, FR-014).
- [X] T009 [US1] Create `app/Http/Requests/Transactions/UploadTransactionsRequest.php`: authorize `user() !== null`; rules for `file` (required, csv/txt mimes, `max:` size — FR-013), `account_id` (required, integer, exists), and `mapping.fields.{posted_at,amount,description}` required strings with `mapping.fields.currency` nullable, `mapping.amount_sign` in `as_is,invert`, `mapping.date_format` nullable. In `after()`, verify `account_id` is owned by the user (FR-015) and that required mapped headers exist in the uploaded file's header row (FR-006).
- [X] T010 [US1] Create `app/Http/Controllers/Transactions/UploadController.php` `create()`: render Inertia `transactions/upload` with props `accounts` (id, name, currency for the user's accounts) and `savedMappings` (this user's `SavedImportMapping` payloads keyed by account_id) per [contracts/import-upload.md](./contracts/import-upload.md).
- [X] T011 [US1] In `UploadController::store(UploadTransactionsRequest $request, MappedCsvImporter $importer)`: run the import synchronously (FR-016), then `SavedImportMapping::updateOrCreate(['user_id','account_id'], ['mapping'])` (FR-017/018), and redirect back with flash `status` summary + `importResult` `{imported, skipped, failed, needsReview, failures}` (FR-011).
- [X] T012 [P] [US1] Create the mapping UI component `resources/js/components/transactions/column-mapper.tsx`: accept parsed `headers` and a `mapping` value, render a shadcn `Select` per target field (Date, Amount, Description/Merchant, Currency-optional) populated from `headers`, plus an amount-sign toggle (As-is / Invert). Emit mapping changes upward.
- [X] T013 [US1] Create the page `resources/js/pages/transactions/upload.tsx`: account `Select`, file input, client-side header parse (minimal delimited-line parser per [contracts/read-headers.md](./contracts/read-headers.md)), render `<ColumnMapper>`, and submit via Wayfinder action for `transactions.upload.store` (`@/actions/...` / `@/routes/...`) as multipart. Display the returned `importResult` summary and per-row failures.
- [X] T014 [US1] Add an entry point to reach the upload page (e.g. an "Upload file" link/button on `resources/js/pages/transactions.tsx`) using the Wayfinder route for `transactions.upload.create`.

**Checkpoint**: MVP complete — a CSV can be uploaded, mapped, and imported end-to-end with a summary.

## Phase 4: User Story 2 - Guided, validated mapping (Priority: P2)

**Goal**: Prevent invalid mappings and pre-suggest mappings from header names; pre-fill the saved mapping.

**Independent test**: Per [quickstart.md](./quickstart.md) "Guided mapping" — saved mapping pre-fills; removing a required field or duplicating a header blocks import with a clear message.

- [X] T015 [US2] In `column-mapper.tsx`, disable/block submission until all required fields (date, amount, description) are mapped, showing inline messages on unmapped required fields (FR-006).
- [X] T016 [US2] In `column-mapper.tsx`, detect and flag when one header is assigned to two different required fields and block submission with a conflict message (FR-007).
- [X] T017 [P] [US2] Add header-name-based suggestion logic (e.g. fuzzy match "date"/"posted", "amount", "description"/"merchant", "currency") that pre-selects mappings when the mapping is empty, while letting the user override (FR-008).
- [X] T018 [US2] In `upload.tsx`, when an account is selected, initialize the mapping from the matching `savedMappings[account_id]` if present (FR-018), otherwise fall back to the suggestion logic from T017.
- [X] T019 [US2] Mirror the conflict/required-field checks server-side in `UploadTransactionsRequest::after()` so a crafted request cannot bypass the client validation (FR-006, FR-007).

**Checkpoint**: Invalid mappings are impossible to submit; repeat uploads pre-fill.

## Phase 5: User Story 3 - Preview rows before importing (Priority: P3)

**Goal**: Show the first several file rows interpreted under the current mapping, updating live.

**Independent test**: Per [quickstart.md](./quickstart.md) "Preview" — preview shows first rows aligned to fields and updates when a mapping changes.

- [X] T020 [US3] Extend the client parse in `upload.tsx` to also capture up to 5 preview data rows (`previewRows`) alongside headers per [contracts/read-headers.md](./contracts/read-headers.md).
- [X] T021 [US3] In `column-mapper.tsx` (or a small `mapping-preview.tsx` sibling), render a preview table showing the first rows with file values placed under the transaction field each header is mapped to, re-rendering when the mapping changes (Story 3 ACs).

## Phase 6: Polish & Cross-Cutting

- [X] T022 [P] Handle empty/header-only files and oversized/non-CSV files with clear user-facing messages on both client (`upload.tsx`) and server (`UploadTransactionsRequest`) (FR-013, edge cases).
- [X] T023 Run quality gates: `./vendor/bin/sail composer run lint` (Pint), `./vendor/bin/sail npm run lint`, `./vendor/bin/sail npm run types:check`, `./vendor/bin/sail npm run format`; fix any issues.
- [ ] T024 Manually verify all scenarios in [quickstart.md](./quickstart.md), including re-upload de-duplication (FR-012) and partial-failure reporting (SC-006).

## Dependencies & Execution Order

- **Setup (T001–T002)** → **Foundational (T003–T006)** → **User Stories**.
- **US1 (T007–T014)** depends on Foundational; is the MVP and must precede US2/US3 (they extend its component/page).
- **US2 (T015–T019)** and **US3 (T020–T021)** both build on the US1 component/page; US2 and US3 are independent of each other and may be done in either order.
- **Polish (T022–T024)** last.

## Parallel Opportunities

- T002 [P] runs alongside T001's column definitions once the migration file exists.
- Within US1: T012 [P] (component) can be built in parallel with backend T007–T011, then integrated in T013.
- T017 [P] (suggestions) is independent of T015/T016 within US2.
- T022 [P] is independent polish.

## Implementation Strategy

**MVP = Phase 1 + 2 + Phase 3 (US1)**: delivers a working upload→map→import flow. Ship/verify it before layering US2 (guarded mapping + pre-fill) and US3 (preview), each an independent increment.
