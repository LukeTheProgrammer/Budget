# Implementation Plan: Upload Transaction Files

**Branch**: `011-upload-transaction-files` | **Date**: 2026-06-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/011-upload-transaction-files/spec.md`

## Summary

Add a front-end flow that lets a signed-in user upload a delimited (CSV) transaction file, pick which of their accounts the rows belong to, map the file's column headers to transaction fields (date, amount, description/merchant, optional currency), preview the result, and import synchronously. The import reuses the existing transaction-storage engine (duplicate detection via `import_hash`, merchant resolution via `NameResolver`, default tags) by extracting that logic out of the Chase-specific `CsvTransactionImporter` into a mapping-driven importer. Confirmed mappings are persisted in a new `SavedImportMapping` model keyed by user + account and pre-filled on the next upload to that account.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), TypeScript / React 19

**Primary Dependencies**: Laravel 13, Inertia v3, React 19, Tailwind v4, shadcn/ui, Wayfinder. No new dependencies — server CSV parsing uses `SplFileObject` (as the existing importer does); client-side header/preview parsing uses a small hand-rolled delimited-line parser in TS.

**Storage**: MySQL 8.4 via Eloquent. New `saved_import_mappings` table. Uploaded files are processed in-request from the temp upload path and are not persisted to disk.

**Testing**: None — per Constitution Principle II (no automated tests). Manual verification in the browser.

**Target Platform**: Local development via Laravel Sail (`http://localhost`).

**Project Type**: Web application (Laravel backend + Inertia/React frontend, single repo).

**Performance Goals**: Synchronous import of a typical statement (hundreds to low-thousands of rows) completes within a normal HTTP request without a perceptible hang. No production-scale targets (Constitution Principle I).

**Constraints**: Synchronous import (FR-016); CSV/delimited-with-header only (FR-013); user may only import to accounts they own (FR-015). PHP `upload_max_filesize`/`post_max_size` and a validated app-level max size bound the upload.

**Scale/Scope**: Single local developer/user. One upload screen, one new model, one new importer path layered on existing services.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Local-Development-Only Scope** — PASS. Synchronous in-request import chosen (no queue/scaling concerns); no production hardening introduced.
- **II. No Automated Tests** — PASS. No tests will be written; tasks omit test-authoring. Verification is manual.
- **III. Framework-Idiomatic Code** — PASS. Eloquent model + migration via Artisan generators, FormRequest validation, Inertia page + Wayfinder actions, shadcn/ui primitives, reuse of existing `NameResolver`/merchant services. Generated Wayfinder files not hand-edited.
- **IV. Code Quality Gates** — PASS (enforced at finalize): Pint on PHP; ESLint/Prettier/TypeScript on frontend; explicit types and return types.
- **V. Simplicity & Convention Over Configuration** — PASS. No new base directories, no new dependencies, no temp-file storage layer (headers/preview parsed client-side; authoritative parse server-side in one request). Reuses the established Transactions/Settings layering.

**Result**: PASS — no violations; Complexity Tracking not required.

**Post-Design Re-check (after Phase 1)**: PASS — data model adds one table, contracts expose two existing-pattern endpoints, no new dependencies or abstractions beyond the extracted row-store service. No deviations.

## Project Structure

### Documentation (this feature)

```text
specs/011-upload-transaction-files/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   ├── read-headers.md
│   └── import-upload.md
└── checklists/
    └── requirements.md  # From /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── SavedImportMapping.php                  # NEW: user+account → field/header map
├── Services/Transactions/
│   ├── CsvTransactionImporter.php              # REFACTOR: delegate row storage to TransactionRowStore
│   ├── TransactionRowStore.php                 # NEW (extracted): persist a normalized row (hash, merchant, tags)
│   ├── MappedCsvImporter.php                   # NEW: parse uploaded CSV + apply column mapping → normalized rows
│   ├── NormalizedTransactionRow.php            # NEW: value object (postedAt, description, merchantName, amountCents, currency)
│   └── ImportResult.php                        # REUSE: per-import summary
├── Http/
│   ├── Controllers/Transactions/
│   │   └── UploadController.php                 # NEW: store() runs the mapped import synchronously
│   └── Requests/Transactions/
│       └── UploadTransactionsRequest.php       # NEW: validate file, account ownership, mapping
└── Models/Account.php                          # reuse: ownership + savedImportMappings() relation

database/migrations/
└── XXXX_create_saved_import_mappings_table.php # NEW

resources/js/pages/transactions/
└── upload.tsx                                   # NEW: upload + mapping + preview UI

resources/js/components/transactions/
└── column-mapper.tsx                            # NEW: header→field mapping + preview component

routes/web.php                                   # add transactions/upload route(s)
```

**Structure Decision**: Single web-app repo following the existing **Transactions** module layering (controller + FormRequest + services under `app/.../Transactions`, Inertia page under `resources/js/pages/transactions/`). This mirrors the reference Settings/Transactions patterns rather than introducing new top-level folders.

## Complexity Tracking

No constitution violations — section intentionally empty.
