# Implementation Plan: Tags for Transaction Categorization

**Branch**: `008-tags-transaction-categorization` | **Date**: 2026-06-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/008-tags-transaction-categorization/spec.md`

## Summary

Introduce a `Tag` model — short, slug-keyed string labels — that can be applied many-to-many to transactions and assigned as defaults to merchants. During CSV import, each newly created transaction inherits its merchant's default tags. Users can apply/remove tags on transactions, manage a merchant's default tags, and delete a tag globally. Implemented with idiomatic Laravel (slug-primary-key Eloquent model, two pivot tables, `firstOrCreate`-by-slug) and Inertia/React UI hung off the existing transactions and merchants pages. No automated tests (per constitution); verification is manual.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), TypeScript / React 19

**Primary Dependencies**: Inertia v3, Eloquent, Wayfinder, Tailwind v4, shadcn/ui, Lucide — all already installed; no new dependencies.

**Storage**: MySQL 8.4 via Laravel Sail. New `tags` table (string primary key = slug) plus `tag_transaction` and `merchant_default_tag` pivot tables.

**Testing**: None — Constitution Principle II forbids automated tests. Manual verification via the running app.

**Target Platform**: Local development only (`http://localhost`, Sail/Docker).

**Project Type**: Web application (Laravel + Inertia SPA, single codebase).

**Performance Goals**: Interactive UI; no specific throughput targets (local single-user tool).

**Constraints**: Slug is the tag primary key (no numeric id). Tag equivalence is by slug (case/spacing-insensitive). Tag values trimmed, ≤50 chars, letters/numbers/spaces/hyphens. Default tags applied on import only.

**Scale/Scope**: Single-user local dataset; a handful of new backend files and two augmented React pages.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Local-Development-Only Scope** — ✅ No production concerns introduced.
- **II. No Automated Tests** — ✅ Plan adds no test files or test tasks; manual verification only.
- **III. Framework-Idiomatic Code** — ✅ Uses Artisan generators (model/migration/controller/request), Eloquent relations, Inertia rendering, Wayfinder route helpers, shadcn/ui primitives.
- **IV. Code Quality Gates** — ✅ Pint for PHP; ESLint/Prettier/tsc for frontend before finalize.
- **V. Simplicity & Convention** — ✅ Reuses the existing Merchant module layering (controllers + form requests + pivot relations); no new base directories or dependencies. Tags are global (matches single-tenant scaffolding) — no per-user scoping abstraction added.

**Result: PASS** (no violations; Complexity Tracking not required).

## Project Structure

### Documentation (this feature)

```text
specs/008-tags-transaction-categorization/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (HTTP route contracts)
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── Tag.php                                   # NEW — slug PK model + relations
├── Http/
│   ├── Controllers/
│   │   ├── Tags/
│   │   │   └── TagController.php                 # NEW — global tag delete
│   │   ├── Transactions/
│   │   │   └── TransactionTagController.php      # NEW — attach/detach tags on a transaction
│   │   └── Merchants/
│   │       └── MerchantDefaultTagController.php  # NEW — manage merchant default tags
│   └── Requests/
│       ├── Transactions/
│       │   └── SyncTransactionTagsRequest.php    # NEW — validate tag values
│       └── Merchants/
│           └── SyncMerchantDefaultTagsRequest.php# NEW — validate tag values
└── Services/
    └── Transactions/
        └── CsvTransactionImporter.php            # EDIT — apply merchant default tags on create

database/
└── migrations/
    └── 2026_06_06_xxxxxx_create_tags_tables.php  # NEW — tags + two pivots

resources/js/
└── pages/
    ├── transactions/index.tsx                    # EDIT — show + edit tags per row
    └── merchants/index.tsx                        # EDIT — manage default tags per merchant

routes/
└── web.php                                        # EDIT — tag routes
```

**Structure Decision**: Single Laravel + Inertia codebase. The feature follows the existing Merchant module pattern (thin controllers + form requests + Eloquent relations) and hangs UI off the two existing pages rather than introducing new top-level pages or directories.

## Complexity Tracking

No constitutional violations — section intentionally empty.
