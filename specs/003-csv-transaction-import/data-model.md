# Phase 1 Data Model: CSV Transaction Import

**No schema changes.** This feature writes into the tables delivered and migrated by spec
`002-transaction-data-and`. This document records how import maps onto that schema and the
in-memory structures the service uses.

## Persistent entities (reused from 002 — unchanged)

- **`transactions`** — destination rows. Relevant columns: `account_id`, `merchant_id`,
  `amount_cents` (signed; **positive = purchase**), `currency`, `description`,
  `posted_at` (date), `import_hash` (char(64)). Idempotency via
  `UNIQUE(account_id, import_hash)`.
- **`merchants`** — resolved/created per row. `UNIQUE(user_id, normalized_name)`; the
  model derives `normalized_name` from `name`.
- **`accounts`** — the single configured default account (v1).
- **`categories`** — untouched by import (category is derived through merchant).

## In-memory structures (new, this feature)

### `ChaseCsvRow` (value object)

One parsed and validated source row.

| Field | Type | Source / Rule |
|-------|------|---------------|
| `lineNumber` | int | 1-based data row index (for failure reporting) |
| `postedAt` | `CarbonImmutable` | `Post Date`, parsed `MM/DD/YYYY` |
| `description` | string | `Description` (+ `Memo` appended when non-empty) |
| `merchantName` | string | trimmed `Description` |
| `amountCents` | int | `round(parsedAmount * -100)`; sign inverted vs Chase; must be non-zero |
| `type` | string | `Type` column (informational) |

Validation (row fails, is skipped, and is reported on any violation):
- `Post Date` parses to a valid date.
- `Amount` parses to a non-zero decimal → non-zero `amountCents`.
- `Description` is non-empty (merchant key requires it).

### `ImportResult` (summary DTO)

| Field | Type | Meaning |
|-------|------|---------|
| `file` | string | relative path of the imported file |
| `imported` | int | rows inserted as new transactions |
| `skipped` | int | rows skipped as duplicates (existing `import_hash`) |
| `failed` | int | rows that failed validation/processing |
| `failures` | list of `{ line: int, reason: string }` | identity + cause per failed row |
| `archived` | bool | whether the file was moved to `processed/` |

`imported + skipped + failed` equals the number of data rows read (FR-009, SC-005).

## Derived values computed during import

- `merchant_id`: `Merchant::firstOrCreate(['user_id','normalized_name'], ['name'])->id`
  where `normalized_name = mb_strtolower(trim(description))` (R5).
- `amount_cents`: see `ChaseCsvRow.amountCents` (R3 — sign inverted).
- `import_hash`: `hash('sha256', "{account_id}|{posted_at:Y-m-d}|{amount_cents}|{normalized_name}")` (R4).
- `currency`: inherited from the account (default `USD`).

## Invariants

- **IM1**: A row is written exactly once; the `(account_id, import_hash)` unique index
  guarantees re-import creates no duplicate (FR-007, SC-002).
- **IM2**: A failed/skipped row never partially writes a transaction (FR-012).
- **IM3**: Imported purchase amounts are positive so existing per-category totals (002)
  remain correct (R3).
- **IM4**: A file is moved to `processed/` only after the whole file is read without an
  aborting error (FR-013).
