---

description: "Task list for Merchant Display Names & Alias Grouping"
---

# Tasks: Merchant Display Names & Alias Grouping

**Input**: Design documents from `/specs/004-merchant-aliases-display-name/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/merchants.md, quickstart.md

**Tests**: NONE. Per the project constitution (Principle II — No Automated Tests) and project memory, no unit/feature/integration/browser tests are written for this app. Verification is manual via `quickstart.md`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story the task belongs to (US1, US2, US3)
- All commands run through Laravel Sail (`./vendor/bin/sail …`).

## Path Conventions

Single Laravel + Inertia project (repo root). Backend under `app/`, migrations under `database/migrations/`, frontend under `resources/js/`, routes in `routes/web.php`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Generate the backend scaffolding the feature builds on.

- [X] T001 Generate scaffolding via Artisan (creates empty class/migration files to be filled in later phases): `./vendor/bin/sail artisan make:model MerchantAlias -mf`, `./vendor/bin/sail artisan make:migration add_display_name_to_merchants_table`, `./vendor/bin/sail artisan make:controller Merchants/MerchantController`, `./vendor/bin/sail artisan make:controller Merchants/MerchantGroupController`, `./vendor/bin/sail artisan make:controller Merchants/MerchantAliasController`, `./vendor/bin/sail artisan make:request Merchants/UpdateMerchantRequest`, `./vendor/bin/sail artisan make:request Merchants/GroupMerchantsRequest`, `./vendor/bin/sail artisan make:request Merchants/StoreMerchantAliasRequest`, `./vendor/bin/sail artisan make:class Services/Merchants/MerchantGrouper`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema and model changes that ALL user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T002 [P] Implement the `add_display_name_to_merchants_table` migration in `database/migrations/****_add_display_name_to_merchants_table.php` — add nullable `display_name` string(200) after `normalized_name`
- [X] T003 [P] Implement the `create_merchant_aliases_table` migration in `database/migrations/****_create_merchant_aliases_table.php` — columns `id`, `user_id` (FK users cascadeOnDelete), `merchant_id` (FK merchants cascadeOnDelete), `name` string(200), `normalized_name` string(200), timestamps; `unique(['user_id','normalized_name'])`; index `merchant_id`
- [X] T004 Run migrations: `./vendor/bin/sail artisan migrate` (depends on T002, T003)
- [X] T005 [P] Implement `MerchantAlias` model in `app/Models/MerchantAlias.php` — `#[Fillable(['user_id','merchant_id','name','normalized_name'])]`, `name` Attribute mutator setting `normalized_name = mb_strtolower(trim($value))` (mirror `Merchant::name()`), `user()` BelongsTo, `merchant()` BelongsTo, PHPDoc property block
- [X] T006 [P] Update `Merchant` model in `app/Models/Merchant.php` — add `display_name` to `#[Fillable]` and PHPDoc; add `label` Attribute accessor returning `display_name ?? name`; add `aliases()` HasMany(MerchantAlias)
- [X] T007 Implement `MerchantController@index` in `app/Http/Controllers/Merchants/MerchantController.php` returning `Inertia::render('merchants/index', ...)` with the user's merchants (`id, name, display_name, label, category_id, transactions_count, aliases:[{id,name}]`) ordered by label using `withCount('transactions')->with('aliases')->where('user_id', auth id)`; register `GET /merchants` → `merchants.index` in `routes/web.php` (auth+verified group); create base `resources/js/pages/merchants/index.tsx` listing merchants (label + raw name + transaction count) under AppLayout, and add a "Merchants" nav link to the app sidebar (depends on T005, T006)

**Checkpoint**: Merchants list page renders with friendly labels — foundation ready.

---

## Phase 3: User Story 1 - Give a merchant a friendly display name (Priority: P1) 🎯 MVP

**Goal**: Users set/clear an optional `display_name` shown everywhere, with the raw imported name preserved.

**Independent Test**: On `/merchants`, set a display name on one merchant, confirm the label updates app-wide; clear it and confirm it reverts to the raw name.

- [X] T008 [US1] Implement `UpdateMerchantRequest` in `app/Http/Requests/Merchants/UpdateMerchantRequest.php` — `authorize()` checks `$this->route('merchant')->user_id === $this->user()->id`; rules `display_name => ['nullable','string','max:200']`; normalize empty string to `null` (FR-004)
- [X] T009 [US1] Implement `MerchantController@update` in `app/Http/Controllers/Merchants/MerchantController.php` — fill `display_name` from validated data, save, flash success toast, redirect back; register `PATCH /merchants/{merchant}` → `merchants.update` in `routes/web.php` (depends on T008)
- [X] T010 [US1] Add inline display-name editing to `resources/js/pages/merchants/index.tsx` using the Wayfinder-generated `merchants.update` action (`@inertiajs/react` `useForm`/`<Form>`), submitting PATCH and showing the updated label (depends on T009)

**Checkpoint**: US1 fully functional — display names editable and reflected in the list.

---

## Phase 4: User Story 2 - Group multiple store variants into one merchant (Priority: P2)

**Goal**: Users merge ≥2 of their merchants into a primary; transactions move over, raw names become aliases, absorbed records deleted; optional display name set during grouping. Future imports auto-match aliases.

**Independent Test**: Group several Hy-Vee variants; confirm one merchant remains with the summed transaction count, the merged raw names appear as its aliases, and a subsequent import of a matching raw name links to it without creating a new merchant.

- [X] T011 [US2] Implement `MerchantGrouper` service in `app/Services/Merchants/MerchantGrouper.php` — `group(User $user, int $primaryId, array $merchantIds, ?string $displayName = null)` inside `DB::transaction`: authorize all ids belong to user (else throw), require ≥2 distinct merchants and primary not in absorbed set; for each absorbed merchant reassign `Transaction::where('merchant_id', absorbed)->update(['merchant_id' => primary])`, create alias on primary from its `name` (skip if normalized matches an existing primary alias or the primary's own normalized_name), re-point its existing aliases to primary (skip duplicates), then delete the absorbed merchant; optionally set primary `display_name` (per data-model.md state transition; FR-005a, FR-006, FR-007, FR-007a, FR-011, FR-012)
- [X] T012 [US2] Implement `GroupMerchantsRequest` in `app/Http/Requests/Merchants/GroupMerchantsRequest.php` — rules: `primary_merchant_id` required|integer|exists; `merchant_ids` required|array|min:2 with distinct integers; `display_name` nullable|string|max:200; `authorize()`/custom rule ensuring every id belongs to `$this->user()` (FR-011)
- [X] T013 [US2] Implement `MerchantGroupController@store` in `app/Http/Controllers/Merchants/MerchantGroupController.php` — call `MerchantGrouper::group(...)`, flash a summary toast, redirect back; register `POST /merchants/group` → `merchants.group` in `routes/web.php` (depends on T011, T012)
- [X] T014 [US2] Add grouping UI to `resources/js/pages/merchants/index.tsx` — multi-select merchants, choose primary, optional display-name field, submit via Wayfinder `merchants.group` action; refresh list to show the merged merchant and its aliases (depends on T013)
- [X] T015 [US2] Make merchant resolution alias-aware in `app/Services/Transactions/CsvTransactionImporter.php` `storeRow()` — before `Merchant::firstOrCreate`, look up `MerchantAlias::firstWhere(['user_id' => $account->user_id, 'normalized_name' => mb_strtolower(trim($row->merchantName))])`; if found use `$alias->merchant`, else fall back to existing `firstOrCreate` (FR-014, research.md Decision 3)

**Checkpoint**: US1 and US2 both work independently; imports respect aliases.

---

## Phase 5: User Story 3 - Manage a merchant's aliases (Priority: P3)

**Goal**: Users view, add, and remove a merchant's aliases, with per-user uniqueness enforced.

**Independent Test**: On a merchant, list aliases, add a new one, attempt a duplicate (rejected), and remove an alias.

- [X] T016 [US3] Implement `StoreMerchantAliasRequest` in `app/Http/Requests/Merchants/StoreMerchantAliasRequest.php` — `authorize()` checks merchant ownership; rules `name => ['required','string','max:200']`; custom rule rejecting a name whose normalized value already exists for the user on any merchant (FR-010, US3 scenario 3); treat a value equal to the merchant's own normalized_name as already represented (edge case)
- [X] T017 [US3] Implement `MerchantAliasController@store` and `@destroy` in `app/Http/Controllers/Merchants/MerchantAliasController.php` — store creates the alias on the merchant; destroy authorizes the alias belongs to the merchant and user then deletes; both flash a toast and redirect back; register `POST /merchants/{merchant}/aliases` → `merchants.aliases.store` and `DELETE /merchants/{merchant}/aliases/{alias}` → `merchants.aliases.destroy` in `routes/web.php` (depends on T016)
- [X] T018 [US3] Add alias view/add/remove UI to `resources/js/pages/merchants/index.tsx` using the Wayfinder `merchants.aliases.store`/`merchants.aliases.destroy` actions, surfacing the duplicate-rejection validation message (depends on T017)

**Checkpoint**: All user stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T019 [P] Regenerate Wayfinder typed helpers and rebuild the frontend: `./vendor/bin/sail npm run dev` (or `npm run build`); confirm `@/actions`/`@/routes` imports for the new merchant routes resolve
- [X] T020 Run quality gates and fix any findings: `./vendor/bin/sail composer run lint` (Pint), `./vendor/bin/sail npm run lint`, `./vendor/bin/sail npm run format`, `./vendor/bin/sail npm run types:check`
- [ ] T021 Walk through `specs/004-merchant-aliases-display-name/quickstart.md` manually to verify US1–US3 and FR-014

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies — start immediately.
- **Foundational (Phase 2)**: depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–5)**: all depend on Foundational. Can proceed in priority order (P1 → P2 → P3) or in parallel by different developers.
- **Polish (Phase 6)**: depends on the desired user stories being complete.

### User Story Dependencies

- **US1 (P1)**: only needs Foundational. Independent.
- **US2 (P2)**: only needs Foundational. Independent of US1 (sets `display_name` directly via the grouper). Includes import auto-match (T015).
- **US3 (P3)**: only needs Foundational. Independent; benefits from aliases created by US2 but does not require it.

### Within Each User Story

- Form request → controller/route → frontend.
- US2: `MerchantGrouper` service (T011) and request (T012) before controller (T013) before UI (T014); import change (T015) is independent of the UI.

### Parallel Opportunities

- Foundational: T002 and T003 (different migration files) are [P]; T005 and T006 (different model files) are [P]. T004 (migrate) waits on T002+T003; T007 waits on T005+T006.
- Across stories: once Phase 2 is done, US1 / US2 / US3 backend work can be staffed in parallel.
- **Caution**: T010, T014, and T018 all edit `resources/js/pages/merchants/index.tsx` — they are NOT parallel with each other; sequence them or coordinate edits.

---

## Parallel Example: Foundational Phase

```bash
# Different files — safe to run together:
Task T002: implement add_display_name_to_merchants_table migration
Task T003: implement create_merchant_aliases_table migration
# then, after migrate (T004):
Task T005: implement MerchantAlias model
Task T006: update Merchant model
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 → 4. Validate display names via quickstart → demo.

### Incremental Delivery

1. Setup + Foundational → merchants list renders.
2. US1 → display-name editing (MVP).
3. US2 → grouping/merge + import auto-match.
4. US3 → manual alias management.
5. Polish → Wayfinder regen + quality gates + quickstart walkthrough.

---

## Notes

- No automated tests are created (constitution Principle II); T021 is manual verification.
- Use Artisan generators (T001) so files match framework conventions; do not hand-edit Wayfinder-generated files.
- Run Pint and the JS gates (T020) before considering the feature done.
- [P] = different files, no dependencies. The three frontend tasks share one page file and must not run in parallel.
