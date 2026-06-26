# Phase 1 Data Model: Upload Transaction Files

## New: SavedImportMapping

Persists a user's confirmed column mapping for an account so it can pre-fill subsequent uploads (FR-017, FR-018).

**Table**: `saved_import_mappings`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK → `users.id` | cascade on delete; owner |
| `account_id` | FK → `accounts.id` | cascade on delete; target account |
| `mapping` | json | see payload shape below |
| `created_at` / `updated_at` | timestamps | |

**Constraints**:
- Unique index on (`user_id`, `account_id`) — at most one remembered mapping per account.
- Upserted (`updateOrCreate`) on each successful import.

**`mapping` JSON shape**:

```json
{
  "fields": {
    "posted_at": "Post Date",
    "amount": "Amount",
    "description": "Description",
    "currency": null
  },
  "amount_sign": "as_is",
  "date_format": null
}
```

- `fields.*` values are the exact header strings from the file (null = unmapped). `posted_at`, `amount`, `description` are required; `currency` optional.
- `amount_sign`: `"as_is"` | `"invert"` (D5).
- `date_format`: optional explicit PHP date format; null = tolerant auto-parse (D6).

**Relationships**:
- `belongsTo(User)`, `belongsTo(Account)`.
- `Account hasMany(SavedImportMapping)` (or `hasOne` per current user) — add `savedImportMappings()` / accessor on `Account`.

**Validation rules** (enforced in `UploadTransactionsRequest`):
- `account_id` must exist and be owned by the authenticated user (FR-015).
- All required fields mapped to a non-empty header present in the uploaded file (FR-006).
- A single header may not be assigned to two different required fields (FR-007).
- `amount_sign` in {`as_is`, `invert`}.

## Reused (unchanged): Transaction

No schema change. Imported rows populate existing fillable columns:
`account_id`, `merchant_id`, `amount_cents`, `currency`, `description`, `posted_at`, `import_hash`.

- `amount_cents`: signed integer cents; positive = spend (sign applied per `amount_sign`).
- `import_hash`: `sha256(account_id | posted_at(Y-m-d) | amount_cents | merchant_id)` — identical to existing engine for cross-path dedup (FR-012).
- `merchant_id`: resolved via existing `NameResolver` from the mapped description (new merchants created unconfirmed).
- `currency`: from mapped currency column, else the account's currency.

## Reused (unchanged): Account, Merchant, MerchantAlias, Category, Tag

Used as-is. Account provides ownership scoping and default currency. Merchant resolution/creation and default-tag application are reused from the existing engine via the extracted `TransactionRowStore`.

## Transient (not persisted): NormalizedTransactionRow

In-memory value object produced by `MappedCsvImporter` and consumed by `TransactionRowStore`:

| Field | Type | Source |
|-------|------|--------|
| `lineNumber` | int | row position (for failure reporting) |
| `postedAt` | CarbonImmutable | mapped date column, parsed |
| `description` | string | mapped description column |
| `merchantName` | string | mapped description column |
| `amountCents` | int | mapped amount column, signed per `amount_sign` |
| `currency` | string | mapped currency column or account default |

## State / lifecycle

No status fields. Flow per upload request: validate → parse file with mapping → for each row build `NormalizedTransactionRow` → `TransactionRowStore` upserts Transaction by `import_hash` (created = imported, existing = skipped, throw = failed) → upsert `SavedImportMapping` → return `ImportResult` summary.
