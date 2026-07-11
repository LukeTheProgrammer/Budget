# Implementation Plan: Deposits and Transfers

**Branch**: `012-transaction-flow-types` | **Date**: 2026-07-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/012-transaction-flow-types/spec.md`

## Summary

Give every transaction a **flow type** (expense, income, transfer, refund), assign it automatically at every import entry point, let the user correct it in a way that sticks (via a per-merchant default), and rewrite every spending aggregation so it counts expenses and refunds only — never income or transfers. Add income and net cash flow to the dashboard, a flow-type column and filter to the transactions list, and pair the two legs of an internal transfer so neither side is counted.

The technical core is small and centralizing: a `FlowType` enum, three new columns (two on `transactions`, one on `merchants`), one classifier service consulted by all three import paths, one pairing service, and a mechanical replacement of the `amount_cents > 0` predicate — which appears in six query scopes and one controller — with a flow-type predicate.

## Technical Context

**Language/Version**: PHP 8.5 (Laravel 13), TypeScript / React 19

**Primary Dependencies**: Inertia v3, Eloquent, Tailwind v4, shadcn/ui, Wayfinder. No new dependencies.

**Storage**: MySQL 8.4 via Laravel Sail

**Testing**: None — Constitution Principle II. Verification is manual in the browser.

**Target Platform**: Local development only (`http://localhost`, Sail)

**Project Type**: Web application (Laravel + Inertia SPA, single repo)

**Performance Goals**: Import and dashboard queries stay interactive at personal scale (one user, tens of accounts, low tens of thousands of transactions). Classification must be O(rows) with no per-row queries.

**Constraints**: The sign convention is fixed — positive `amount_cents` is an outflow, negative is an inflow. Existing import dedup (`import_hash`) and per-user isolation are reused unchanged.

**Scale/Scope**: 2 migrations, 1 enum, 2 services, 1 controller, ~7 query-scope edits, 3 React pages touched, 1 backfill command.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | PASS | No production concerns introduced. Backfill is a one-shot local Artisan command. |
| II. No Automated Tests | PASS | No tests planned or required. Verification steps live in `quickstart.md`. |
| III. Framework-Idiomatic Code | PASS | Backed PHP enum + Eloquent casts, `#[Scope]` query scopes matching `Transaction`'s existing style, Artisan generators for migration/enum/controller/command, Wayfinder for the new route, existing shadcn primitives for badge and select. |
| IV. Code Quality Gates | PASS | Pint on modified PHP; ESLint, Prettier, and `tsc --noEmit` on modified frontend, before finalizing. |
| V. Simplicity & Convention Over Configuration | PASS | Reuses the existing merchant record as the home for the classification rule rather than adding a rules table; no new base directories; no new dependencies. |

Post-Phase-1 re-check: **PASS** — the design adds no abstractions beyond two services, both of which live in the existing `app/Services/Transactions/` directory alongside `TransactionRowStore`.

## Project Structure

### Documentation (this feature)

```text
specs/012-transaction-flow-types/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output — manual verification script
├── contracts/
│   └── http.md          # Phase 1 output — routes and Inertia prop contracts
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── FlowType.php                                   # NEW — expense | income | transfer | refund
├── Console/Commands/
│   └── ClassifyTransactionFlowTypes.php               # NEW — retroactive backfill (FR-016)
├── Models/
│   ├── Transaction.php                                # flow_type cast, transferPair relation,
│   │                                                  #   scopes rewritten off amount_cents > 0,
│   │                                                  #   new incomeSummary scope
│   └── Merchant.php                                   # default_flow_type cast, expense-activity scope
├── Services/
│   ├── Transactions/
│   │   ├── FlowTypeClassifier.php                     # NEW — the single classification rule
│   │   ├── TransferPairer.php                         # NEW — links the two legs of a transfer
│   │   └── TransactionRowStore.php                    # classify before persisting
│   └── Plaid/
│       └── PlaidTransactionSync.php                   # classify before persisting
├── Http/Controllers/
│   ├── DashboardController.php                        # income + net cash flow props
│   ├── BudgetController.php                           # flow-type predicate replaces amount_cents > 0
│   ├── InsightsController.php                         # flow-type predicate
│   ├── Merchants/MerchantController.php               # expense-activity-only listing by default
│   └── Transactions/
│       ├── TransactionController.php                  # flow_type in row payload + filter
│       └── TransactionFlowTypeController.php          # NEW — PATCH a transaction's flow type
└── Http/Requests/Transactions/
    ├── TransactionFilterRequest.php                   # flow_type[] filter
    └── UpdateFlowTypeRequest.php                      # NEW

database/migrations/
├── ...._add_flow_type_to_transactions_table.php       # NEW — flow_type, flow_type_source, transfer_pair_id
└── ...._add_default_flow_type_to_merchants_table.php  # NEW

resources/js/
├── pages/
│   ├── transactions.tsx                               # flow-type badge column + filter + editor
│   ├── dashboard.tsx                                  # income / spending / net cash flow
│   └── merchants.tsx                                  # "show non-expense merchants" toggle
└── components/
    └── flow-type-badge.tsx                            # NEW — shared badge + amount direction

routes/web.php                                         # PATCH transactions/{transaction}/flow-type
```

**Structure Decision**: The existing Laravel + Inertia layout is used as-is. New backend code slots into existing directories (`app/Enums`, `app/Services/Transactions`, `app/Http/Controllers/Transactions`), following the Settings module's controller/form-request layering and the `TransactionRowStore` service pattern. No new top-level directories.

## Key Design Decisions

Full reasoning is in [research.md](./research.md); the schema is in [data-model.md](./data-model.md).

1. **`flow_type` is a stored column on `transactions`, not a derived value.** It must survive re-import, be user-correctable, and be filterable and indexable — a computed accessor could do none of those.

2. **The reusable classification rule lives on `merchants.default_flow_type`, not in a new table.** The spec's clarification keys corrections on the merchant, and merchants are already per-user and already carry per-merchant defaults (`category_id`, default tags). The existing `merchant_rules` table is a *name-resolution* mechanism (descriptor → merchant) and is deliberately left alone.

3. **One classifier, three call sites.** `FlowTypeClassifier::classify()` is the only place the rules live; `TransactionRowStore` (file upload and mapped import), `PlaidTransactionSync`, and the backfill command all call it. Precedence: user-set value on the transaction, then the merchant default, then the automatic heuristics.

4. **The spending predicate becomes `flow_type IN (expense, refund)`.** Because refunds are stored negative and expenses positive, `SUM(amount_cents)` over that set nets refunds out automatically (FR-006) with no special-casing — which is exactly why the existing sign convention is worth keeping.

5. **Pairing runs after an import batch, not per row**, so the counterpart leg is already present when we look for it. It is idempotent, so re-running it (or the backfill) never double-links.

## Complexity Tracking

> No constitution violations. Section intentionally empty.
