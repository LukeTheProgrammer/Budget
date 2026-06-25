# Implementation Plan: Merchant Display Names & Alias Grouping

**Branch**: `004-merchant-aliases-display-name` | **Date**: 2026-06-01 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-merchant-aliases-display-name/spec.md`

## Summary

Add an optional user-facing `display_name` to merchants and introduce a
per-user merchant aliasing mechanism so that multiple store variants (e.g.
"HY-VEE PR VILLAGE 1532", "HYVEE FUEL 4021") can be grouped under a single
primary merchant. Grouping **merges** absorbed merchants into the primary:
their transactions move over, their raw names become aliases, and the absorbed
records are deleted. Aliases are unique per user (normalized) and are matched
during CSV import so future imports resolve incoming raw names to the already
grouped merchant instead of recreating variants.

Technical approach: a new `merchant_aliases` table + `MerchantAlias` model, a
`display_name` column on `merchants`, a `MerchantGrouper` service encapsulating
the transactional merge, a thin Merchants module (controller + form requests +
Inertia/React pages) mirroring the Settings reference pattern, and an
alias-aware tweak to `CsvTransactionImporter::storeRow()`.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13), TypeScript / React 19

**Primary Dependencies**: Laravel 13, Inertia v3, @inertiajs/react, Wayfinder, Tailwind v4, shadcn/ui, Lucide

**Storage**: MySQL 8.4 (via Laravel Sail)

**Testing**: None — per constitution Principle II, no automated tests are written; verification is manual via the running app

**Target Platform**: Local development only (`http://localhost`, `APP_PORT=80`) via Sail/Docker

**Project Type**: Web application (Laravel backend + Inertia/React SPA, single project)

**Performance Goals**: Interactive single-user local app; no specific throughput targets. Grouping merge must complete within a single DB transaction.

**Constraints**: Per-user data isolation; merge must not orphan or lose transactions; alias uniqueness enforced per user at the DB level

**Scale/Scope**: Single local user; merchant/alias counts in the low thousands at most. One new table, one column, one service, ~3 controllers, 1–2 React pages.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | ✅ Pass | No production concerns introduced; single-account/single-user assumptions retained. |
| II. No Automated Tests | ✅ Pass | Plan authors **no** tests and adds no test tasks; manual verification only (see quickstart.md). |
| III. Framework-Idiomatic Code | ✅ Pass | Uses Artisan generators (model/migration/controller/request), Eloquent relations, Inertia rendering, Wayfinder helpers, shadcn/ui primitives. No hand-edited generated files. |
| IV. Code Quality Gates | ✅ Pass | Pint for PHP; ESLint/Prettier/tsc for frontend. Explicit return types and typed PHPDoc throughout. |
| V. Simplicity & Convention | ✅ Pass | Reuses existing per-user uniqueness + normalization patterns; no new dependencies; new files live under existing `app/Http/Controllers/<Module>` and `resources/js/pages/<module>` conventions (no new base dirs). |

**Result**: PASS — no violations, Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/004-merchant-aliases-display-name/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output (manual verification steps)
├── contracts/           # Phase 1 output (HTTP route contracts)
│   └── merchants.md
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Merchant.php                 # + display_name fillable, label accessor, aliases() relation
│   └── MerchantAlias.php            # NEW model
├── Services/
│   ├── Merchants/
│   │   └── MerchantGrouper.php      # NEW — transactional merge logic
│   └── Transactions/
│       └── CsvTransactionImporter.php  # storeRow() becomes alias-aware
├── Http/
│   ├── Controllers/Merchants/
│   │   ├── MerchantController.php       # NEW — index, update (display_name)
│   │   ├── MerchantGroupController.php  # NEW — store (group/merge)
│   │   └── MerchantAliasController.php  # NEW — store, destroy
│   └── Requests/Merchants/
│       ├── UpdateMerchantRequest.php    # NEW
│       ├── GroupMerchantsRequest.php    # NEW
│       └── StoreMerchantAliasRequest.php# NEW
database/
├── migrations/
│   ├── ****_add_display_name_to_merchants_table.php   # NEW
│   └── ****_create_merchant_aliases_table.php          # NEW
└── factories/
    └── MerchantAliasFactory.php         # NEW (factory only; no tests)

resources/js/
├── pages/merchants/
│   └── index.tsx                    # NEW — merchant list, display-name edit, grouping, alias mgmt
└── components/
    └── (reuse existing shadcn/ui primitives; add merchant-specific components only if needed)

routes/web.php                       # + merchant routes under auth+verified group
```

**Structure Decision**: Single Laravel + Inertia project (existing). New code follows
the established module convention used by `Transactions/` and `Settings/`
(controllers under `app/Http/Controllers/Merchants/`, form requests under
`app/Http/Requests/Merchants/`, pages under `resources/js/pages/merchants/`).
Merchant routes are added directly to `routes/web.php` (small set, auth+verified
group) rather than a separate route file, consistent with the existing
`transactions.import` route placement.

## Complexity Tracking

No constitution violations — section intentionally left empty.
