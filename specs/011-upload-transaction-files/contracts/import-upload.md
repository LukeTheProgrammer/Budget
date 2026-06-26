# Contract: Upload & import transactions (synchronous)

## Routes

- `GET /transactions/upload` → `UploadController@create`
  - Inertia page `transactions/upload`.
  - Props: `accounts` (id, name, currency for the user's accounts) and `savedMappings` (per-account saved mapping payloads to pre-fill).

- `POST /transactions/upload` → `UploadController@store`
  - Auth required (`auth`, `verified`). Runs the import **synchronously** (FR-016).

## Request (`POST /transactions/upload`, multipart/form-data)

| Field | Type | Rules |
|-------|------|-------|
| `file` | file | required; mimes/extension csv/txt; `max:` size (e.g. 5120 KB) (FR-013) |
| `account_id` | integer | required; exists; owned by auth user (FR-002, FR-015) |
| `mapping[fields][posted_at]` | string | required; must be a header present in `file` |
| `mapping[fields][amount]` | string | required; header present in `file` |
| `mapping[fields][description]` | string | required; header present in `file` |
| `mapping[fields][currency]` | string\|null | optional; header present if provided |
| `mapping[amount_sign]` | string | required; in `as_is`,`invert` |
| `mapping[date_format]` | string\|null | optional |

Validation (in `UploadTransactionsRequest`):
- All required field headers present in the uploaded file's header row (FR-006).
- No header assigned to two different required fields (FR-007).
- Reject unsupported/oversized files with a clear message (FR-013).

## Behavior
1. Validate request (file, account ownership, mapping completeness/consistency).
2. `MappedCsvImporter` parses every data row, applies the mapping → `NormalizedTransactionRow`s.
3. Each row is stored via `TransactionRowStore` (merchant resolution + `import_hash` dedup + default tags). Created = imported, existing hash = skipped, exception = failed (recorded, not fatal) (FR-009..FR-012, FR-014).
4. Upsert `SavedImportMapping` for (user, account) with the submitted mapping (FR-017, FR-018).

## Response (Inertia)
- `302` redirect back to the upload page (or transactions index) with flash:
  - `status`: human summary, e.g. `"Imported 142, skipped 8, failed 2. 5 new merchant(s) need review."`
  - `importResult`: `{ imported, skipped, failed, needsReview, failures: [{ line, reason }] }` for inline display (Story 1 AC4, FR-011, FR-014).

## Errors
- `422` with validation errors (invalid file, unmapped required field, unowned/missing account, header conflict). No transactions written.
- Per-row parse/validation failures do not abort: valid rows still import; failures returned in `importResult.failures` (FR-014, SC-006).
