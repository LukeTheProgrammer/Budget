# Phase 0 Research: CSV Transaction Import

All Technical Context unknowns are resolved below. The feature builds on the already-
migrated schema from spec `002-transaction-data-and`.

## R1. Fixed CSV layout (the Chase export format)

**Decision**: The single supported layout is the Chase credit-card activity export with
this exact header row:

```
Transaction Date,Post Date,Description,Category,Type,Amount,Memo
```

A file whose first row does not match these column names (order-sensitive) is rejected
in full before any rows are imported (FR-003a).

**Rationale**: Every file in `storage/app/private/*.CSV` is a Chase export of this shape
(verified against the 20 sample files). The clarification chose one fixed documented
layout over auto-detection.

**Alternatives considered**: Multi-bank auto-detection (rejected — YAGNI, Principle V);
caller-supplied column mapping (rejected — no second format exists yet).

## R2. Column → field mapping

| CSV column | Used for | Notes |
|------------|----------|-------|
| `Transaction Date` | (reference only) | Not stored in v1. |
| `Post Date` | `transactions.posted_at` | Parsed `MM/DD/YYYY`. |
| `Description` | merchant name + `transactions.description` | Raw descriptor; also the merchant key source. |
| `Category` | (ignored in v1) | Chase's own category; our categories are user-defined via merchant. May seed merchant category later — out of scope. |
| `Type` | sign/validation aid | `Sale`, `Return`, `Payment`, `Refund`, `Adjustment`, etc. |
| `Amount` | `transactions.amount_cents` | See R3 for sign + parsing. |
| `Memo` | appended to `description` if present | Usually empty. |

**Decision**: `posted_at` comes from **Post Date** (not Transaction Date), matching the
`posted_at` semantics in 002.

## R3. Amount sign convention (critical)

**Decision**: Chase signs **purchases as negative** and **credits/payments as positive**
— the inverse of the 002 schema, where `amount_cents` is positive for purchases and
negative for refunds/credits (FR-006). The importer therefore **negates** the parsed
amount: `amount_cents = round(parsedAmount * -100)`.

- Chase `Sale -27.20` → `+2720` cents (a purchase / positive spend).
- Chase `Return +50.00` / `Payment +100.00` → `-5000` / `-10000` cents (credit).

Parsing: strip a leading currency symbol and thousands separators, allow a leading `-`,
parse as decimal, multiply by 100, round to the nearest integer cent. Reject a row whose
amount is non-numeric or yields zero cents (the 002 model requires non-zero).

**Rationale**: Aligns imported data with the existing reporting query in 002 (positive =
spend). Getting the sign wrong would invert every total.

**Alternatives considered**: Store Chase's raw sign (rejected — breaks SC-002 totals and
the existing per-category aggregate).

## R4. Duplicate detection / `import_hash`

**Decision**: Compute `import_hash = sha256(account_id|posted_at(Y-m-d)|amount_cents|
merchant_normalized_name)` and rely on the existing `UNIQUE(account_id, import_hash)`
index for idempotency. Before insert, the service checks existence by
`(account_id, import_hash)`; on a unique-constraint race it treats the row as a skipped
duplicate.

**Rationale**: Matches the clarified duplicate identity (account + date + amount +
merchant) and reuses the 002 dedupe column without schema change (SC-002, SC-003).

**Alternatives considered**: A per-file processed marker (rejected — overlapping
statements need row-level dedupe); hashing the raw CSV line (rejected — `Memo`/whitespace
noise would defeat overlap detection).

## R5. Merchant resolution

**Decision**: Resolve via `Merchant::firstOrCreate(['user_id' => …, 'normalized_name' =>
mb_strtolower(trim(Description))], ['name' => Description])`. The `Merchant` model already
derives `normalized_name` from `name` and enforces `UNIQUE(user_id, normalized_name)`, so
name variants collapse to one merchant.

**Rationale**: Implements the clarified "match on normalized name, else create" rule with
the model's existing behavior — no new logic.

**Alternatives considered**: Fuzzy matching (rejected — risk/complexity, Principle V).

## R6. Target account

**Decision**: A single configured/default account for v1, read from
`config('transactions.default_account_id')` (backed by an `.env` value), resolved to an
`Account` belonging to the authenticated/owning user. If unset or missing, the import
aborts with a clear error before processing rows.

**Rationale**: Matches the clarification (one account total for v1); avoids per-file
account UX.

## R7. Reading files & the private disk

**Decision**: Use the `local` filesystem disk (root `storage/app/private`). Discover
batch files with `Storage::disk('local')->files()` filtered to `*.csv`/`*.CSV`
(case-insensitive), excluding the processed archive subfolder. Parse with
`SplFileObject`/`fgetcsv` streaming line-by-line to avoid loading large files entirely
into memory.

**Rationale**: `storage/app/private` is Laravel's `local` disk root; native CSV parsing
needs no new dependency (Principle V / Technology Constraints).

## R8. Post-import file handling

**Decision**: On a fully completed import, move the file to
`storage/app/private/processed/` (created if absent) via `Storage::disk('local')->move()`.
A file that is rejected (bad layout) or errors before completion stays in place (FR-013).

**Rationale**: Matches the clarified archive behavior; keeps an audit trail and prevents
batch re-processing.

## R9. Per-row resilience & atomicity

**Decision**: Process rows independently; wrap **each row** in a try/catch and (lightly)
in its own DB write so a malformed row is recorded as a failure and skipped without
aborting the file (FR-008, FR-012). Accumulate failures (row number + reason) in
`ImportResult`. Do not wrap the whole file in a single transaction (a late failure must
not roll back earlier valid rows).

**Rationale**: Satisfies "valid rows still import; failures reported with identity"
(SC-003 of spec, FR-008/FR-012).

## R10. Three entry points, one service

**Decision**: `CsvTransactionImporter` exposes `importFile(string $relativePath): ImportResult`
and `importAll(): array<string, ImportResult>`. The Artisan command, the queued Job, and
the Inertia controller each construct/inject the service and return/echo its result. No
import logic is duplicated (FR-001).

**Rationale**: Single source of truth for behavior; identical outcome across triggers
(SC-004).

## Open items deferred to implementation (non-blocking)

- Whether the front-end endpoint runs the import synchronously or dispatches the Job —
  both call the same service; default to dispatching the Job for responsiveness.
- Optional mapping of Chase's `Category` column to seed merchant categories — out of
  scope for v1.
