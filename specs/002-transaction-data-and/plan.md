# Implementation Plan: Transaction Data & Merchant-Category Spending Analysis

**Branch**: `002-transaction-data-and` | **Date**: 2026-06-01 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-transaction-data-and/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. Scope here is the
**database schema** (migrations + Eloquent models + factories/seeders) for transaction
data and all related tables. UI, import pipelines, and reporting endpoints are out of
scope for this plan.

## Summary

Design the relational schema that stores credit card transactions and lets the app
categorize spending by merchant and aggregate it over time. Core tables: `accounts`
(credit cards owned by a user), `categories` (user-defined spending groups), `merchants`
(normalized vendors, each mapped to one category), and `transactions` (signed amounts in
integer cents, tied to an account and an optional merchant). Reporting (per-category
spend over time) is satisfied by querying transactions joined to merchants → categories,
indexed on `(account_id, posted_at)` and `merchant_id`.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13)

**Primary Dependencies**: Laravel 13, Eloquent ORM, Laravel Sail

**Storage**: MySQL 8.4 (via Sail container)

**Testing**: None — automated tests are prohibited per Constitution Principle II. Schema
verification is manual via `artisan migrate` + `database-schema` inspection / tinker.

**Target Platform**: Local development only (`http://localhost`, Docker/Sail)

**Project Type**: Web application (Laravel + Inertia/React SPA)

**Performance Goals**: Local single-user scale; no production throughput targets.

**Constraints**: Money stored as integer minor units (cents) to avoid float drift; all
schema changes via Artisan-generated migrations.

**Scale/Scope**: Personal-finance volumes (thousands–tens of thousands of transactions).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Local-Development-Only Scope | ✅ Pass | No production/scaling concerns in schema. |
| II. No Automated Tests | ✅ Pass | No tests planned; manual verification only. |
| III. Framework-Idiomatic Code | ✅ Pass | Eloquent models, `make:model -mf` generators, conventional pivots/FKs. |
| IV. Code Quality Gates | ✅ Pass | Migrations/models will pass Pint; typed model methods. |
| V. Simplicity & YAGNI | ✅ Pass | 4 domain tables, no speculative override/tagging tables; merchant→category 1:N. |

No violations — Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/002-transaction-data-and/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (the schema)
├── quickstart.md        # Phase 1 output (how to apply/inspect)
└── contracts/
    └── schema.md        # Phase 1 output (table/column contract)
```

### Source Code (repository root)

```text
database/
├── migrations/
│   ├── XXXX_create_categories_table.php
│   ├── XXXX_create_merchants_table.php
│   ├── XXXX_create_accounts_table.php
│   └── XXXX_create_transactions_table.php
├── factories/
│   ├── CategoryFactory.php
│   ├── MerchantFactory.php
│   ├── AccountFactory.php
│   └── TransactionFactory.php
└── seeders/
    └── BudgetSeeder.php          # optional sample data for local dev

app/Models/
├── Category.php
├── Merchant.php
├── Account.php
└── Transaction.php   # (User.php already exists)
```

**Structure Decision**: Standard Laravel layout — migrations under
`database/migrations`, Eloquent models under `app/Models`, factories/seeders for local
sample data (no test usage). No new top-level directories (Constitution V).

## Complexity Tracking

> No constitution violations — section intentionally empty.
