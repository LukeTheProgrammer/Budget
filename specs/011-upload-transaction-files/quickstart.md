# Quickstart: Upload Transaction Files

Manual verification (no automated tests — Constitution II). Run inside Sail.

## Setup
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate          # creates saved_import_mappings
./vendor/bin/sail npm run dev
```
Ensure you have at least one account (Settings → Accounts) and are signed in.

## Happy path (User Story 1)
1. Go to `/transactions/upload`.
2. Select an account from the dropdown.
3. Choose a CSV file with a header row (e.g. a Chase export: `Transaction Date, Post Date, Description, Category, Type, Amount, Memo`).
4. Confirm the headers appear and the mapper lets you assign:
   - Date → `Post Date`
   - Amount → `Amount` (set sign to **Invert** for Chase, since it signs purchases negative)
   - Description/Merchant → `Description`
   - Currency → (leave unmapped → uses account currency)
5. Check the preview shows the first rows aligned to the fields.
6. Import. Confirm the inline summary shows imported/skipped/failed counts.
7. Visit `/transactions` and confirm rows appear on the chosen account with correct dates and signs (purchases positive, refunds negative).

## Guided mapping (User Story 2)
- Reload the upload page, pick the same account → the saved mapping pre-fills (FR-018).
- Remove the Amount mapping → import button is blocked with a clear message (FR-006).
- Assign the same header to two required fields → blocked with a conflict message (FR-007).

## Preview (User Story 3)
- Change the Date mapping to a different column → preview updates to reflect it.

## Edge cases to spot-check
- Re-upload the same file → second import reports rows as **skipped**, creates no duplicates (FR-012).
- File with a bad date/amount in one row → that row is reported under failures; the rest still import (FR-014, SC-006).
- Upload a non-CSV or oversized file → rejected with a clear message (FR-013).
- Header-only / empty file → blocked with "no data rows".

## Quality gates before finalizing
```bash
./vendor/bin/sail composer run lint        # Pint
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run format
```
