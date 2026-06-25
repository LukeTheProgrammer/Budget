# Implementation Plan: Session Period Selector

**Branch**: `007-session-period-selector` | **Date**: 2026-06-03 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/007-session-period-selector/spec.md`

## Summary

Move the Dashboard's inline period control into a single selector rendered in the app
header (top nav), visible on every authenticated page. The selected period is stored
**server-side in the session** and exposed as a shared Inertia prop so every page reads
the same time frame. Selecting a period (or applying a custom start/end range) posts to
a dedicated route that writes the choice to the session and redirects back, re-rendering
the current page with data scoped to the new window. Data controllers (Dashboard first,
extensible to others) read the resolved window from a shared service instead of a
per-page query string. The existing Dashboard `PeriodSelector` (ToggleGroup) is removed.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13), TypeScript / React 19

**Primary Dependencies**: Inertia v3 (`@inertiajs/react`), Tailwind v4, shadcn/ui
(`select`, `popover`, `input` primitives already present), Lucide icons, Wayfinder

**Storage**: Laravel session (period selection); MySQL 8.4 for transaction data (read-only here)

**Testing**: None — per Constitution Principle II, verification is manual via the running app

**Target Platform**: Local web app via Laravel Sail (`http://localhost`)

**Project Type**: Web application (Inertia SPA, server-rendered pages)

**Performance Goals**: Period change reflected on current page in a single round-trip;
data queries stay within existing dashboard/transactions page-load expectations

**Constraints**: No new dependencies (use existing shadcn/ui primitives and native date
inputs); no production concerns; generated Wayfinder files not hand-edited

**Scale/Scope**: Single-user-per-session local tool; ~1 shared prop, 1 controller +
route, 1 value object/service, 1 React provider + 1 header component, Dashboard refactor

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Local-Development-Only Scope** — PASS. Session-based storage and a single
  round-trip update are the simplest fit; no scaling/production concerns introduced.
- **II. No Automated Tests** — PASS. No tests will be written; plan and tasks omit
  test-authoring. Manual verification steps captured in `quickstart.md`.
- **III. Framework-Idiomatic Code** — PASS. Uses Inertia shared props, a Laravel
  controller + named route, session storage, Wayfinder helpers, and existing shadcn/ui
  primitives. New backend files created via Artisan generators. Generated route/action
  files not edited.
- **IV. Code Quality Gates** — PASS. Pint for PHP; ESLint/Prettier/tsc for frontend.
  Explicit return types and typed props required.
- **V. Simplicity & Convention Over Configuration** — PASS. Reuses the Settings module
  layering, adds no new base directories or dependencies, applies YAGNI (only Dashboard
  is wired to the shared window now; Transactions integration is documented but not
  forced). One small value object encapsulates period→window resolution, reused from the
  existing Dashboard logic.

**Result**: PASS — no violations, Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/007-session-period-selector/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output (manual verification)
├── contracts/
│   └── period.md        # Route/shared-prop contract
└── checklists/
    └── requirements.md  # From /speckit-specify
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── SessionPeriodController.php      # NEW — update period in session
│   │   └── DashboardController.php          # EDIT — read window from shared service
│   ├── Requests/
│   │   └── SessionPeriodRequest.php         # NEW — validate preset | custom range
│   └── Middleware/
│       └── HandleInertiaRequests.php        # EDIT — share resolved `period` prop
├── Support/
│   └── SessionPeriod.php                    # NEW — value object: type, dates → window + label
routes/
└── web.php                                  # EDIT — POST session-period route

resources/js/
├── components/
│   ├── period/
│   │   └── session-period-selector.tsx      # NEW — Select + custom-range popover (top nav)
│   ├── app-sidebar-header.tsx               # EDIT — render selector in header
│   └── dashboard/
│       └── period-selector.tsx              # DELETE — replaced by global selector
├── pages/
│   └── dashboard.tsx                        # EDIT — drop inline PeriodSelector
└── types/index.d.ts (or shared props type)  # EDIT — add `period` shared prop type
```

**Structure Decision**: Web application using the existing Laravel + Inertia layout.
Backend follows the Settings-module reference pattern (controller + form request); a
small framework-agnostic value object lives under `app/Support/` to hold the
period→window resolution currently embedded in `DashboardController`. Frontend adds one
provider-free component group under `resources/js/components/period/` and wires it into
the existing `AppSidebarHeader`.

## Complexity Tracking

> No constitution violations — section intentionally empty.
