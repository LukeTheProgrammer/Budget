# Quickstart: Tags for Transaction Categorization

How to build and manually verify this feature (no automated tests — Constitution Principle II).

## Prerequisites

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev   # vite HMR + wayfinder regen
```

## Build steps (high level)

1. **Migration** — create `tags` (string `slug` primary key, `name`), `tag_transaction`, and `merchant_default_tag` pivots with cascade FKs and composite uniques. Run `./vendor/bin/sail artisan migrate`.
2. **Models** — add `app/Models/Tag.php` (slug PK config + `transactions()`); add `tags()` to `Transaction` and `defaultTags()` to `Merchant`.
3. **Form requests** — `SyncTransactionTagsRequest`, `SyncMerchantDefaultTagsRequest` (value validation: ≤50 chars, letters/numbers/spaces/hyphens, non-empty).
4. **Controllers** — `TransactionTagController` (store/destroy), `MerchantDefaultTagController` (store/destroy), `TagController` (destroy). Use `Tag::firstOrCreate` by slug + `syncWithoutDetaching`/`detach`.
5. **Routes** — register the five routes in `routes/web.php` (authenticated group); Wayfinder regenerates helpers.
6. **Importer** — in `CsvTransactionImporter::storeRow()`, after create, when `wasRecentlyCreated`, attach the merchant's `defaultTags` slugs.
7. **Controllers feeding pages** — eager-load `tags`/`defaultTags`, include in payloads, pass existing tags for autocomplete.
8. **Frontend** — extend `transactions/index.tsx` (display + add/remove tags) and `merchants/index.tsx` (manage default tags) with shadcn/ui badge/input/command.

## Quality gates (required before finalizing)

```bash
./vendor/bin/sail composer run lint        # Pint
npm run lint && npm run format && npm run types:check
```

## Manual verification checklist

- **Apply tag (FR-005/006)**: On a transaction, add "Groceries", then "Essentials"; reload — both persist and display.
- **Dedup by slug (FR-003)**: Add "Dining Out" then "dining out" — only one tag results.
- **Remove tag (FR-007)**: Remove a tag from a transaction; the tag still exists elsewhere.
- **Validation (FR-004)**: Try empty/whitespace and a 60-char value — both rejected.
- **Merchant defaults (FR-008/009)**: Assign "Coffee" + "Discretionary" to a merchant; reload — saved. Remove one; existing transactions unchanged (FR-013).
- **Import tagging (FR-010)**: With defaults set, import a CSV for that merchant — new transactions carry the defaults, no duplicates (FR-012).
- **No defaults / no merchant (FR-014)**: Import for a merchant with no defaults (or unresolved merchant) — imported with no tags, no error.
- **Re-import**: Re-import the same file — skipped rows are not re-tagged.
- **Global delete (FR-015)**: Delete a tag globally — it disappears from all transactions and merchant defaults.

## Notes

- Tags are global (not user-scoped) per the single-tenant scaffolding.
- The slug is the primary key; renaming a tag is out of scope (delete + recreate instead).
