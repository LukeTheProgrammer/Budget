# Internal Contract: CSV Import Service & Entry Points

This feature exposes no external/public API. The contract below is the internal surface
that the Command, Job, and Controller all depend on. It is the source of truth the
implementation must satisfy (Constitution Principle II: verified manually, no tests).

## Service: `App\Services\Transactions\CsvTransactionImporter`

```php
final class CsvTransactionImporter
{
    /**
     * Import a single CSV file (path relative to the `local` disk root,
     * e.g. "Chase7452_Activity20260601.CSV").
     *
     * @throws ImportException when the file is missing/unreadable, the header
     *         does not match the fixed Chase layout, or the default account is
     *         not configured. Per-row problems do NOT throw — they are counted
     *         as failures in the result.
     */
    public function importFile(string $relativePath): ImportResult;

    /**
     * Discover and import every *.csv/*.CSV in the private disk root
     * (excluding the processed/ archive). Returns one result per file, keyed
     * by relative path. A file that throws is captured as a failed result and
     * does not stop the batch.
     *
     * @return array<string, ImportResult>
     */
    public function importAll(): array;
}
```

### Behavior guarantees

| ID | Guarantee | Spec ref |
|----|-----------|----------|
| C1 | Header not matching `Transaction Date,Post Date,Description,Category,Type,Amount,Memo` → throws, zero rows written. | FR-003a |
| C2 | Each data row becomes a `Transaction` on the default account with merchant resolved/created. | FR-003, FR-004, FR-005 |
| C3 | Purchase amounts stored positive, credits negative (Chase sign inverted). | FR-006, R3 |
| C4 | Rows whose `(account_id, import_hash)` already exists are counted as `skipped`, not inserted. | FR-007 |
| C5 | An invalid row is counted as `failed` (with line + reason) and never aborts the file. | FR-008, FR-012 |
| C6 | Returns an `ImportResult` with `imported`/`skipped`/`failed` counts + failures. | FR-009, SC-005 |
| C7 | On full completion the file is moved to `processed/`; on hard failure it stays. | FR-013 |
| C8 | Missing/unconfigured default account → throws before any row is processed. | clarified account rule |

## Entry point 1 — Artisan command

```
php artisan transactions:import {file? : relative path under storage/app/private}
                                {--all : import every unprocessed CSV in the folder}
```

- `transactions:import Chase7452_Activity20260601.CSV` → one file.
- `transactions:import --all` → `importAll()`.
- Prints a per-file summary table (imported/skipped/failed) and lists failed lines.
- Exit code `0` when all files processed (even with skipped/failed rows); non-zero only
  on a hard failure (missing file, bad header, unconfigured account).

## Entry point 2 — Queued job

```php
ImportTransactionsFile::dispatch(string $relativePath);
```

- Thin wrapper: resolves `CsvTransactionImporter` and calls `importFile()`.
- Logs the resulting summary. Honors the existing database queue (`jobs` table).

## Entry point 3 — HTTP (Inertia/React trigger)

```
POST /transactions/import        (auth required)
body: { "file": "<relative path>" }  // or { "all": true }
```

- Validated by a Form Request.
- Default behavior: dispatches `ImportTransactionsFile` (or runs `importAll`) and returns
  an Inertia redirect with a flash summary; supports a JSON response for programmatic use.
- Produces the same `ImportResult` outcome as the other triggers (SC-004).

## Error type

`App\Services\Transactions\ImportException` (extends `RuntimeException`) carries a
human-readable message surfaced by each entry point (CLI error line, job log, HTTP 422 /
flash error).
