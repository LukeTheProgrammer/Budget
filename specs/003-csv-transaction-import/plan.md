# Implementation Plan: CSV Transaction Import

**Branch**: `003-csv-transaction-import` | **Date**: 2026-06-01 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-csv-transaction-import/spec.md`

## Summary

Provide a single reusable back-end service that reads a Chase-format CSV file from
`storage/app/private/`, turns each row into a `Transaction` (resolving/creating its
`Merchant`), skips already-imported rows via the existing `import_hash` dedupe key, and
moves the file to a processed archive on success. The same service is invoked from an
Artisan command, a queued Job, and an Inertia controller endpoint so front-end and
back-end triggers share identical behavior. Builds directly on the schema delivered by
spec `002-transaction-data-and` (already migrated).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13)

**Primary Dependencies**: Laravel framework only — `Illuminate\Support\Facades\Storage`
for the `local` (private) disk, native `SplFileObject`/`fgetcsv` for parsing. **No new
Composer dependency** (e.g. `league/csv`) is introduced, per constitution.

**Storage**: MySQL 8.4 (existing `transactions`, `merchants`, `accounts`, `categories`
tables). Source files on the `local` filesystem disk (`storage/app/private`).

**Testing**: None — automated tests are prohibited by Constitution Principle II.
Verification is manual via the Artisan command and the app UI.

**Target Platform**: Local development via Laravel Sail (Docker), `http://localhost`.

**Project Type**: Web application (Laravel + Inertia/React), back-end-centric feature.

**Performance Goals**: Import a typical monthly statement (hundreds of rows) in well
under a developer-acceptable wait; batch import of the ~20 sample files completes in one
command run. No production throughput target (Principle I).

**Constraints**: Row-level resilience (one bad row never aborts the file); idempotent
re-import; create files via Artisan generators; pass Pint/ESLint/Prettier/tsc gates.

**Scale/Scope**: ~20 statement files, single user, single configured account for v1.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | PASS | No production hardening; single-user, single-account v1; no queue infra assumptions beyond the existing `jobs` table. |
| II. No Automated Tests | PASS | Plan authors **no** tests; verification is manual. Tasks must omit test-authoring steps. |
| III. Framework-Idiomatic Code | PASS | Uses Eloquent, `Storage` facade, Artisan-generated Command/Job/Controller, `firstOrCreate` for merchants. Reuses 002 models and `import_hash`. |
| IV. Code Quality Gates | PASS | PHP via Pint; the (small) front-end trigger via ESLint/Prettier/tsc. |
| V. Simplicity & Convention | PASS | One service + three thin entry points; native CSV parsing; no new base directories beyond the conventional `app/Services`, `app/Jobs`, `app/Console/Commands`. |

No violations — Complexity Tracking left empty.

## Project Structure

### Documentation (this feature)

```text
specs/003-csv-transaction-import/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (no schema changes — usage notes)
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── service.md       # Internal service/CLI/job/HTTP contract
└── tasks.md             # Created later by /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Services/
│   └── Transactions/
│       ├── CsvTransactionImporter.php   # the reusable service (core logic)
│       ├── ChaseCsvRow.php              # value object: one parsed/validated row
│       └── ImportResult.php             # summary DTO: imported/skipped/failed (+ failures)
├── Console/
│   └── Commands/
│       └── ImportTransactionsCommand.php # `transactions:import {file?} {--all}`
├── Jobs/
│   └── ImportTransactionsFile.php        # queued wrapper around the service
└── Http/
    └── Controllers/
        └── Transactions/
            └── ImportController.php       # Inertia/JSON endpoint (front-end trigger)

config/transactions.php                    # default account id + paths (or reuse .env)
routes/web.php                             # POST route → ImportController (auth)
```

**Structure Decision**: Web-application layout. All import logic lives in
`App\Services\Transactions\CsvTransactionImporter`; the Command, Job, and Controller are
thin adapters that call it and surface its `ImportResult` (FR-001). This follows the
controller/service layering convention referenced by the constitution.

## Complexity Tracking

> No constitution violations — section intentionally empty.
