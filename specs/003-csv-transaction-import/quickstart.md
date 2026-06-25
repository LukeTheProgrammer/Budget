# Quickstart: CSV Transaction Import

Manual verification steps (no automated tests, per Constitution Principle II). Run
everything through Laravel Sail.

## Prerequisites

1. Schema from spec 002 migrated: `./vendor/bin/sail artisan migrate`.
2. At least one `Account` exists for your user, and its id is configured as the default:
   ```
   # .env
   TRANSACTIONS_DEFAULT_ACCOUNT_ID=1
   ```
   (`config/transactions.php` reads this value.)
3. Sample Chase exports are present in `storage/app/private/*.CSV`.

## 1. Import a single file (Artisan)

```bash
./vendor/bin/sail artisan transactions:import Chase7452_Activity20260601.CSV
```

Expect a summary like `imported=N skipped=0 failed=0` and the file moved to
`storage/app/private/processed/`.

## 2. Verify the data

```bash
./vendor/bin/sail artisan tinker --execute 'echo App\Models\Transaction::count();'
```

Spot-check signs: a Chase `Sale` row should appear as a **positive** `amount_cents`
(purchase); a `Payment`/`Return` should be **negative**.

## 3. Re-import is idempotent

Copy the file back from `processed/` (or re-run before moving) and import again — expect
`imported=0 skipped=N` and no change in `Transaction::count()` (FR-007, SC-002/SC-003).

## 4. Batch import everything

```bash
./vendor/bin/sail artisan transactions:import --all
```

Expect one summary line per file; all files moved to `processed/`.

## 5. Bad-row resilience

Edit a copy of a file to corrupt one row (e.g. blank the `Amount`), import it, and
confirm the valid rows still import while the bad row is reported under `failed` with its
line number (FR-008, FR-012).

## 6. Trigger from the front end / job

- Job: `./vendor/bin/sail artisan tinker --execute 'App\Jobs\ImportTransactionsFile::dispatch("Chase7452_Activity20260601.CSV");'`
  then run the queue worker (`./vendor/bin/sail artisan queue:work`).
- HTTP: `POST /transactions/import` with `{ "file": "Chase7452_Activity20260601.CSV" }`
  while authenticated; confirm the flash/JSON summary matches the CLI result (SC-004).

## Quality gates before finishing

```bash
./vendor/bin/sail composer run lint        # Pint
# (only if the front-end trigger UI changed)
./vendor/bin/sail npm run lint && ./vendor/bin/sail npm run types:check
```
