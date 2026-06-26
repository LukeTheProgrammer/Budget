# Implementation Plan: Manage Accounts

**Branch**: `010-manage-accounts` | **Date**: 2026-06-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/010-manage-accounts/spec.md`

## Summary

Add a settings page where a signed-in user can create, edit, and delete their own **manual** accounts (accounts not linked to a Plaid connection). The page lists existing accounts and offers create/edit dialogs with name (required), optional type (predefined dropdown), currency, last-four, and balance fields. Deleting a manual account soft-deletes it and cascade soft-deletes its transactions so both vanish from normal views while remaining in the database. Plaid-linked accounts are shown read-only except for renaming, and are not deletable here (disconnect remains the existing Connections flow). Built end-to-end with the existing Settings module pattern: route in `routes/settings.php`, `Settings\AccountController`, form requests, an `AccountPolicy`, a `settings-accounts` Inertia/React page, and Wayfinder helpers.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13) backend; TypeScript / React 19 frontend

**Primary Dependencies**: Inertia v3, Wayfinder, Tailwind v4, shadcn/ui (new-york), Lucide — all already installed; no new dependencies

**Storage**: MySQL 8.4 via Laravel Sail. Existing `accounts` table (soft-deletes already present) and `transactions` table (soft-deletes already present). No schema changes required.

**Testing**: None — per Constitution Principle II (No Automated Tests). Manual verification only.

**Target Platform**: Local-only web app at `http://localhost` (Sail/Docker)

**Project Type**: Inertia SPA (Laravel backend + React frontend, single repo)

**Performance Goals**: N/A (local single-user tool); standard interactive web responsiveness

**Constraints**: Follow Settings module layering; framework-idiomatic; pass Pint + ESLint + Prettier + tsc

**Scale/Scope**: Single user, a handful of accounts; one new settings page + one controller + two form requests + one policy + nav entry

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | Pass | No production concerns introduced; soft-delete uses existing model capability. |
| II. No Automated Tests | Pass | No tests authored; verification via quickstart manual steps. |
| III. Framework-Idiomatic Code | Pass | Reuses Settings module pattern, Eloquent, Inertia render, Wayfinder, shadcn/ui primitives, Artisan generators for new files. |
| IV. Code Quality Gates | Pass | Plan requires Pint + ESLint/Prettier/tsc before completion. |
| V. Simplicity & Convention | Pass | No new dependencies, no new base directories, no schema migration; smallest slice that satisfies the spec. |

**Result**: PASS — no violations. Complexity Tracking not required. (Re-checked post-Phase 1: still PASS.)

## Project Structure

### Documentation (this feature)

```text
specs/010-manage-accounts/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (HTTP + UI contracts)
│   └── accounts.md
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/Settings/
│   │   └── AccountController.php          # NEW — index, store, update, destroy
│   └── Requests/Settings/
│       ├── AccountStoreRequest.php        # NEW
│       └── AccountUpdateRequest.php       # NEW
├── Policies/
│   └── AccountPolicy.php                  # NEW — ownership: view/update/delete
├── Enums/
│   └── AccountType.php                    # NEW — Checking/Savings/Credit/Cash/Investment
└── Models/
    ├── Account.php                        # EDIT — cast type to AccountType; cascade soft-delete transactions
    └── Transaction.php                    # EDIT — guard raw-join spending scopes against trashed rows

routes/
└── settings.php                          # EDIT — accounts resource routes

resources/js/
├── pages/
│   └── settings-accounts.tsx             # NEW — list + create/edit/delete UI
├── components/accounts/                   # NEW — dialogs/row components as needed
│   ├── account-form-dialog.tsx
│   └── delete-account-dialog.tsx
└── layouts/settings/layout.tsx           # EDIT — add "Accounts" nav item
```

**Structure Decision**: Inertia SPA single-repo layout. The feature follows the **Settings module reference pattern** exactly (the spec's clarified UI location is the settings section): controller under `app/Http/Controllers/Settings/`, form requests under `app/Http/Requests/Settings/`, page named `settings-accounts` so `app.tsx` assigns `[AppLayout, SettingsLayout]` via the `settings-` prefix, and Wayfinder generates `@/routes/accounts` + `@/actions` helpers consumed by the React page.

## Complexity Tracking

No constitution violations — section intentionally empty.
