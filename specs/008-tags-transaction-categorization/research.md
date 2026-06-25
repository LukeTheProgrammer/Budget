# Research: Tags for Transaction Categorization

All Technical Context items are resolved (no NEEDS CLARIFICATION remained after the clarification session). This document records the key design decisions and their rationale.

## Decision 1: Slug as the model's primary key

- **Decision**: `Tag` uses `slug` (string) as its primary key. On the model set `protected $primaryKey = 'slug'`, `protected $keyType = 'string'`, `public $incrementing = false`. The migration declares `$table->string('slug', 60)->primary()`.
- **Rationale**: The spec mandates "a slug version of the tag as the primary key instead of an integer." Slug-as-PK gives natural deduplication and human-readable pivot rows. Length 60 comfortably holds a slug of a 50-char display value.
- **Alternatives considered**: Numeric id + unique slug column (rejected — contradicts the explicit requirement); UUID (rejected — adds opacity with no benefit for a local tool).

## Decision 2: Slug generation & equivalence

- **Decision**: Derive the slug with Laravel's `Str::slug($name)`. Treat two display values as the same tag when their slugs match. Persist a display `name` alongside the slug to preserve the user's casing.
- **Rationale**: `Str::slug` lowercases, trims, and collapses spacing/punctuation to hyphens, satisfying FR-003 (case/spacing-insensitive equivalence) for free. Keeping a separate `name` satisfies the "preserve entered casing where shown" assumption.
- **Alternatives considered**: Storing only the slug and title-casing for display (rejected — loses user intent like acronyms).

## Decision 3: Reuse via firstOrCreate by slug

- **Decision**: When applying or assigning a tag, compute the slug and `Tag::firstOrCreate(['slug' => $slug], ['name' => $name])`.
- **Rationale**: Atomic get-or-create keyed on the PK guarantees FR-011 (reuse, no duplicates) and works identically across the UI and importer code paths.
- **Alternatives considered**: Pre-query then conditionally insert (rejected — race-prone and more code).

## Decision 4: Many-to-many via two pivot tables

- **Decision**: `tag_transaction` (`tag_slug`, `transaction_id`) and `merchant_default_tag` (`tag_slug`, `merchant_id`), each with a composite unique key and cascade-on-delete foreign keys. Relations: `Transaction::tags()`, `Merchant::defaultTags()`, `Tag::transactions()`.
- **Rationale**: Resolved in clarification as many-to-many. Composite unique key enforces FR-012 (a tag at most once per transaction) and the merchant-default uniqueness. Cascade deletes implement FR-015 (global tag delete removes all links) and clean up when a transaction/merchant is deleted.
- **Alternatives considered**: Strict one-to-many tag rows per transaction (rejected in clarification — breaks reuse and merchant defaults).

## Decision 5: Import hook point

- **Decision**: In `CsvTransactionImporter::storeRow()`, after `Transaction::updateOrCreate(...)`, when `$transaction->wasRecentlyCreated`, sync the resolved merchant's `defaultTags` slugs onto the transaction via `attach`/`syncWithoutDetaching`.
- **Rationale**: This is the single shared import path behind the Artisan command, queued job, and HTTP controller, so all entry points get default tagging (FR-010). Guarding on `wasRecentlyCreated` ensures re-imported (skipped) rows are not re-tagged, and `syncWithoutDetaching` keeps a tag at most once (FR-012). Applying only here honors the "import only" clarification (FR-010), leaving manual creation/merchant reassignment untouched.
- **Alternatives considered**: Eloquent `created` model event (rejected — fires for all transaction creation paths, violating the import-only scope and harder to reason about).

## Decision 6: Validation rules

- **Decision**: Form requests validate each tag value: `required`, `string`, `max:50`, trimmed, and `regex` allowing letters, numbers, spaces, and hyphens; reject empty/whitespace-only. Slugs are derived server-side, never trusted from the client.
- **Rationale**: Encodes FR-004 and the value-constraint clarification at the boundary, consistent with the existing `app/Http/Requests` pattern.

## Decision 7: UI integration

- **Decision**: Extend `transactions/index.tsx` to display each transaction's tags and offer add (free-form input with autocomplete from existing tags) / remove. Extend `merchants/index.tsx` to manage a merchant's default tags. Use shadcn/ui primitives (badge/input/command) and Wayfinder-generated route helpers for requests.
- **Rationale**: Matches the hybrid entry clarification (free-form + autocomplete) and the constitution's reuse-existing-pages, idiomatic-Inertia guidance; avoids new top-level pages.
- **Alternatives considered**: A dedicated tags-management page (rejected — YAGNI; global delete can live inline, e.g., from the autocomplete list).
