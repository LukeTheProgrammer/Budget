# Implementation Plan: Transactions Table with Filters

**Branch**: `006-transactions-table-filters` | **Date**: 2026-06-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-transactions-table-filters/spec.md`

## Summary

Add an authenticated `transactions` page that renders the user's transactions in a paginated table (date, merchant, category, description, amount), ordered most recent first. The table is filterable by date range, a single merchant, a single category, and an amount range. Filters are driven entirely by URL query parameters: they hydrate the controls and query on initial load, and every filter change rewrites the query string (resetting to page 1) via an Inertia partial visit that preserves state and scroll. All filtering and pagination happen server-side, scoped to the authenticated user's accounts.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13) backend; TypeScript / React 19 frontend

**Primary Dependencies**: Inertia v3, Wayfinder (typed routes/actions), Tailwind v4, shadcn/ui, Lucide

**Storage**: MySQL 8.4 (existing `transactions`, `merchants`, `categories`, `accounts` tables) via Eloquent; no schema changes

**Testing**: None — per Constitution Principle II, verification is manual in the browser. Quality gate is Pint + ESLint/Prettier/TypeScript.

**Target Platform**: Local-only web app via Laravel Sail (`http://localhost`)

**Project Type**: Web application (Laravel + Inertia React SPA)

**Performance Goals**: Table renders/updates within 1s for up to 10,000 transactions (SC-004); achieved through server-side pagination + indexed `posted_at` filtering.

**Constraints**: Per-user data isolation enforced at the query level (SC-005); no new dependencies; reuse existing UI primitives.

**Scale/Scope**: Single new page, one controller, one form request, one filter query scope, one Inertia page component plus a filter bar; default page size 50 rows.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | ✅ Pass | No production concerns introduced; simple server-paginated page. |
| II. No Automated Tests | ✅ Pass | No tests planned or required; manual browser verification only. |
| III. Framework-Idiomatic Code | ✅ Pass | Inertia page render, Eloquent query scope, FormRequest validation, Wayfinder route/action helpers, shadcn primitives. Mirrors `DashboardController` / `MerchantController`. |
| IV. Code Quality Gates | ✅ Pass | Pint on PHP; ESLint/Prettier/tsc on frontend before finalizing. |
| V. Simplicity & Convention | ✅ Pass | One controller + one form request + one page, following the existing `Transactions/` and `merchants/` layering. No new base directories or dependencies. Adding the shadcn `table` primitive (first-party generator, not a new dependency) is the only new UI primitive. |

**Result**: PASS — no violations, Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/006-transactions-table-filters/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (page props + query param contract)
│   └── transactions-index.md
└── checklists/
    └── requirements.md  # From /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Transactions/
│   │       └── TransactionController.php      # NEW — index() renders the page
│   └── Requests/
│       └── Transactions/
│           └── TransactionFilterRequest.php   # NEW — validates query params
└── Models/
    └── Transaction.php                        # MODIFIED — add #[Scope] filter()

routes/
└── web.php                                    # MODIFIED — GET transactions route

resources/js/
├── pages/
│   └── transactions/
│       └── index.tsx                          # NEW — table + filter bar page
└── components/
    ├── transactions/
    │   └── transaction-filters.tsx            # NEW — filter bar control
    └── ui/
        └── table.tsx                          # NEW — shadcn table primitive
```

**Structure Decision**: Web application using the existing Laravel + Inertia layout. Backend code follows the established `app/Http/Controllers/Transactions/` namespace (already home to `ImportController`) and the `app/Http/Requests/<Domain>/` FormRequest pattern (mirroring `Merchants/UpdateMerchantRequest`). Frontend follows `resources/js/pages/<feature>/index.tsx` (mirroring `merchants/index.tsx`), with feature-specific components under `resources/js/components/transactions/` and shared primitives in `components/ui/`.

## Complexity Tracking

No constitution violations — section intentionally empty.
