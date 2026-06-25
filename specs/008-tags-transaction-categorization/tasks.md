# Tasks: Tags for Transaction Categorization

**Feature**: `008-tags-transaction-categorization` | **Plan**: [plan.md](./plan.md)

**Input**: Design documents from `/specs/008-tags-transaction-categorization/`

**Note**: Per project Constitution (Principle II), **no automated tests** are written. Verification is manual via the running app (see [quickstart.md](./quickstart.md)). Run quality gates (Pint, ESLint/Prettier/tsc) before finalizing.

**Conventions**: `[P]` = parallelizable (different files, no incomplete dependency). `[US#]` maps to a user story. All backend commands run via `./vendor/bin/sail`.

---

## Phase 1: Setup

- [X] T001 Confirm dev stack is running: `./vendor/bin/sail up -d` and `./vendor/bin/sail npm run dev` (Vite + Wayfinder regeneration active).

---

## Phase 2: Foundational (blocking prerequisites)

- [X] T002 Create migration `database/migrations/2026_06_06_000003_create_tags_tables.php` via `./vendor/bin/sail artisan make:migration create_tags_tables`: define `tags` (`slug` string(60) primary key, `name` string(50), timestamps); `tag_transaction` (`tag_slug` FK→`tags.slug` cascade, `transaction_id` FK→`transactions.id` cascade, unique [`tag_slug`,`transaction_id`]); `merchant_default_tag` (`tag_slug` FK→`tags.slug` cascade, `merchant_id` FK→`merchants.id` cascade, unique [`tag_slug`,`merchant_id`]).
- [X] T003 Run `./vendor/bin/sail artisan migrate` and confirm all three tables exist with expected columns/keys.
- [X] T004 Create `app/Models/Tag.php` via `./vendor/bin/sail artisan make:model Tag`: set `$primaryKey='slug'`, `$keyType='string'`, `$incrementing=false`, `#[Fillable(['slug','name'])]`, `getRouteKeyName(): string => 'slug'`, and `transactions()` belongsToMany relation through `tag_transaction`. Add `@property` PHPDoc.

**Checkpoint**: Tags table and model exist; ready for relations and per-story work.

---

## Phase 3: User Story 1 — Apply tags to transactions (Priority: P1) 🎯 MVP

**Goal**: Users can add (free-form + autocomplete, create-on-the-fly by slug) and remove tags on a transaction; tags persist, dedupe by slug, and validate.

**Independent test**: Open a transaction, add "Groceries" then "Essentials", reload (both persist); add "dining out" and "Dining Out" (one tag); remove a tag (still exists elsewhere); submit empty/60-char value (rejected).

- [X] T005 [US1] Add `tags()` belongsToMany relation (through `tag_transaction`) to `app/Models/Transaction.php`, with `@return` PHPDoc.
- [X] T006 [P] [US1] Create `app/Http/Requests/Transactions/SyncTransactionTagsRequest.php` via `make:request`: authorize the transaction belongs to the user's accounts; validate `tags` as array, each value `required|string|max:50` trimmed and `regex` allowing letters/numbers/spaces/hyphens, rejecting empty/whitespace-only.
- [X] T007 [US1] Create `app/Http/Controllers/Transactions/TransactionTagController.php` via `make:controller`: `store` (for each value `Tag::firstOrCreate(['slug'=>Str::slug($v)],['name'=>$v])` then `syncWithoutDetaching`) and `destroy` (detach the bound `{tag}` from the transaction); both redirect back with flash.
- [X] T008 [US1] Register routes in `routes/web.php` (authenticated group): `POST transactions/{transaction}/tags` → `transactions.tags.store`; `DELETE transactions/{transaction}/tags/{tag}` → `transactions.tags.destroy`.
- [X] T009 [US1] In `app/Http/Controllers/Transactions/TransactionController@index`: eager-load `tags` on the transaction query, add each transaction's `tags` (slug+name) to the row payload, and pass an `available_tags` (slug+name) list for autocomplete.
- [X] T010 [US1] Extend `resources/js/pages/transactions/index.tsx`: render each transaction's tags as removable shadcn/ui badges and add a free-form tag input with autocomplete suggestions from `available_tags`; wire add/remove to the Wayfinder-generated `transactions.tags.store`/`destroy` helpers.

**Checkpoint**: User Story 1 is independently functional — tagging works end-to-end without merchants/import.

---

## Phase 4: User Story 2 — Set default tags for a merchant (Priority: P2)

**Goal**: Users can add/remove a merchant's default tags; changes do not retroactively alter existing transactions.

**Independent test**: On a merchant, assign "Coffee" + "Discretionary", reload (saved); remove one (gone from merchant; existing transactions unchanged).

- [X] T011 [US2] Add `defaultTags()` belongsToMany relation (through `merchant_default_tag`) to `app/Models/Merchant.php`, with `@return` PHPDoc.
- [X] T012 [P] [US2] Create `app/Http/Requests/Merchants/SyncMerchantDefaultTagsRequest.php` via `make:request`: authorize merchant ownership; validate `tags` values with the same rules as T006.
- [X] T013 [US2] Create `app/Http/Controllers/Merchants/MerchantDefaultTagController.php` via `make:controller`: `store` (`firstOrCreate` by slug + `syncWithoutDetaching` onto `defaultTags`, no retroactive transaction tagging) and `destroy` (detach `{tag}` from `defaultTags`); redirect back with flash.
- [X] T014 [US2] Register routes in `routes/web.php`: `POST merchants/{merchant}/default-tags` → `merchants.default-tags.store`; `DELETE merchants/{merchant}/default-tags/{tag}` → `merchants.default-tags.destroy`.
- [X] T015 [US2] In `app/Http/Controllers/Merchants/MerchantController@index`: eager-load `defaultTags`, include them per merchant in the payload, and pass `available_tags` for autocomplete.
- [X] T016 [US2] Extend `resources/js/pages/merchants/index.tsx`: per-merchant default-tag management UI (removable badges + autocomplete input) wired to the `merchants.default-tags.store`/`destroy` Wayfinder helpers.

**Checkpoint**: Merchant default tags can be managed independently of import.

---

## Phase 5: User Story 3 — Automatically tag transactions on import (Priority: P2)

**Goal**: Newly imported transactions inherit their merchant's default tags (import only, no duplicates, no error when no merchant/defaults).

**Independent test**: With defaults set on a merchant, import a CSV for that merchant — new transactions carry the defaults, no duplicates; re-import — skipped rows not re-tagged; import for merchant with no defaults / unresolved merchant — no tags, no error.

**Depends on**: US1 (Transaction `tags()` relation, T005) and US2 (Merchant `defaultTags()` relation, T011).

- [X] T017 [US3] In `app/Services/Transactions/CsvTransactionImporter::storeRow()`, after `Transaction::updateOrCreate(...)`, when `$transaction->wasRecentlyCreated` and the resolved merchant has `defaultTags`, attach those tag slugs via `syncWithoutDetaching` (guarded so re-imported/skipped rows and no-default/no-merchant rows are untouched).

**Checkpoint**: Default-tag inheritance works on import via the shared importer (covers Artisan command, queued job, and HTTP controller paths).

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T018 [P] Create `app/Http/Controllers/Tags/TagController.php` (`destroy`) and register `DELETE tags/{tag}` → `tags.destroy` in `routes/web.php`; deleting a tag cascades through both pivots (FR-015). Expose a delete affordance in the autocomplete/tag UI on either page.
- [X] T019 Run quality gates: `./vendor/bin/sail composer run lint` (Pint) and `npm run lint && npm run format && npm run types:check`; fix any issues.
- [ ] T020 Manual verification pass against the [quickstart.md](./quickstart.md) checklist (all FR scenarios) in the running app.

---

## Dependencies & Execution Order

- **Setup (P1)** → **Foundational (P2: T002–T004)** must complete before any user story.
- **User Story 1 (P1, T005–T010)**: depends only on Foundational. **MVP.**
- **User Story 2 (P2, T011–T016)**: depends only on Foundational; independent of US1.
- **User Story 3 (P2, T017)**: depends on T005 (US1) and T011 (US2).
- **Polish (T018–T020)**: after the stories it touches; T019/T020 last.

## Parallel Opportunities

- After Foundational: US1 and US2 can proceed in parallel (different files).
- `[P]` within stories: T006 (US1 request) parallel with T005/T007 prep; T012 (US2 request) parallel with T011/T013; T018 (Tag delete) parallel with other polish once routes file is free.
- Form-request tasks (T006, T012) and the global-delete controller (T018) are the main `[P]` items; route-file edits (T008, T014, T018) touch `routes/web.php` so should be serialized with each other.

## Implementation Strategy

- **MVP**: Phases 1–3 (Setup + Foundational + US1) deliver working transaction tagging — a viable, demoable slice.
- **Increment 2**: US2 (merchant defaults).
- **Increment 3**: US3 (import inheritance) — the payoff that ties US1 + US2 together.
- **Finish**: Polish (global delete, quality gates, manual verification).
