# Implementation Plan: Dashboard Spending Insights

**Branch**: `005-dashboard-spending-insights` | **Date**: 2026-06-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-dashboard-spending-insights/spec.md`

## Summary

Replace the placeholder Dashboard with spending-insight widgets driven by the authenticated user's transactions: a period summary (total spent, transaction count, change vs. previous period), a spending-by-category breakdown (chart + ranked table), a 12-month spending trend, and recent/largest transaction tables. A single `DashboardController` aggregates the data server-side (reusing the existing `Transaction::spendingByCategory` scope pattern) and passes it to an Inertia React page. Period selection (this month / last month / last 3 months) is driven by a query parameter so all widgets recalculate together. Charts use `recharts` via the shadcn/ui chart primitive (approved).

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), TypeScript 5.7 / React 19

**Primary Dependencies**: Inertia v3, Wayfinder, Tailwind v4, shadcn/ui, **recharts (new — approved)**, lucide-react

**Storage**: MySQL 8.4 (existing `accounts`, `transactions`, `merchants`, `categories` tables) — no schema changes

**Testing**: None — per Constitution Principle II, verification is manual in-browser

**Target Platform**: Local web app via Laravel Sail (`http://localhost`)

**Project Type**: Web application (Inertia SPA — Laravel backend + React frontend in one repo)

**Performance Goals**: Dashboard renders all widgets within 2s for up to 12 months of history (SC-002); spending figures reconcile exactly with underlying transactions (SC-003)

**Constraints**: Single currency assumed (multi-currency out of scope); spending = positive `amount_cents` outflows only, refunds/credits (negative) excluded; aggregation server-side to avoid shipping raw transactions

**Scale/Scope**: Single-user dashboard, one page, ~5 widgets, one read-only controller

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Local-Development-Only Scope**: PASS — no production concerns introduced; pure read-only feature.
- **II. No Automated Tests**: PASS — no tests will be written; manual browser verification only. Tasks must not include test authoring.
- **III. Framework-Idiomatic Code**: PASS — uses `Inertia::render`, an Artisan-generated controller, existing Eloquent scope patterns, Wayfinder route helper, and the shadcn/ui chart component (idiomatic recharts wrapper). Generated Wayfinder files untouched.
- **IV. Code Quality Gates**: PASS — PHP via Pint; frontend via ESLint/Prettier/`types:check`. No new gate exemptions.
- **V. Simplicity & Convention Over Configuration**: PASS with one approved exception — adding `recharts` is a new dependency, explicitly approved by the user for idiomatic shadcn charts (see Complexity Tracking). No new base directories; follows the Merchants module layering.

**Result**: PASS (one approved dependency addition tracked below).

## Project Structure

### Documentation (this feature)

```text
specs/005-dashboard-spending-insights/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── dashboard.md     # Inertia page-props contract
└── checklists/
    └── requirements.md  # From /speckit-specify
```

### Source Code (repository root)

```text
app/Http/Controllers/
└── DashboardController.php          # NEW — aggregates + renders dashboard props

app/Models/
└── Transaction.php                  # MODIFIED — add spending scopes (period totals, monthly trend, recent, largest)

routes/
└── web.php                          # MODIFIED — swap Route::inertia('dashboard') for DashboardController@index

resources/js/pages/
└── dashboard.tsx                    # MODIFIED — compose widgets from page props

resources/js/components/dashboard/   # NEW — feature widgets
├── period-selector.tsx
├── summary-cards.tsx
├── category-breakdown.tsx           # chart + ranked table
├── spending-trend.tsx               # 12-month chart
└── transactions-table.tsx           # reused for recent + largest

resources/js/components/ui/
└── chart.tsx                        # NEW — shadcn chart primitive (recharts wrapper)
```

**Structure Decision**: Web application (Inertia SPA). Backend follows the existing Merchants module layering — a thin read-only controller that aggregates via Eloquent scopes on `Transaction` and renders an Inertia page. Frontend widgets live under a new `resources/js/components/dashboard/` folder (a sub-folder of the existing components directory, not a new top-level base folder), consistent with how feature components are grouped.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| New dependency `recharts` (Principle V / Technology Constraints require approval) | Charts (category breakdown + 12-month trend) are core to the feature; recharts is the dependency behind the idiomatic shadcn/ui chart component | Hand-built SVG/CSS charts were offered as a zero-dependency alternative; user explicitly approved recharts for polish and idiomatic shadcn integration |
