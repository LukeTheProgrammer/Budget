# Data Model: Tags for Transaction Categorization

## Entity: Tag

A short, slug-keyed string label used to categorize transactions. Global (not user-scoped), consistent with the current single-tenant scaffolding.

| Field | Type | Notes |
|-------|------|-------|
| `slug` | string(60), **primary key** | Derived from `name` via `Str::slug`. Non-incrementing string key. |
| `name` | string(50) | Display value as entered by the user (preserves casing). |
| `created_at` / `updated_at` | timestamps | Standard Eloquent timestamps. |

**Model config**: `$primaryKey = 'slug'`, `$keyType = 'string'`, `$incrementing = false`, `#[Fillable(['slug', 'name'])]`.

**Validation rules** (FR-003, FR-004, value clarification):
- `name`: required, trimmed, ≤50 chars, only letters/numbers/spaces/hyphens, not empty/whitespace-only.
- `slug`: derived server-side (`Str::slug(name)`); equivalence/uniqueness determined by slug.

**Relationships**:
- `transactions()` — belongsToMany `Transaction` through `tag_transaction`.
- (inverse of merchant defaults reachable via `Merchant::defaultTags()`).

## Entity: Transaction (existing — extended)

Adds a many-to-many relationship to `Tag`.

**New relationship**:
- `tags()` — belongsToMany `Tag` through `tag_transaction` (FR-005, FR-006).

No column changes to the `transactions` table.

## Entity: Merchant (existing — extended)

Adds a many-to-many relationship to `Tag` for default tags.

**New relationship**:
- `defaultTags()` — belongsToMany `Tag` through `merchant_default_tag` (FR-008, FR-009).

No column changes to the `merchants` table.

## Pivot: tag_transaction

| Field | Type | Notes |
|-------|------|-------|
| `tag_slug` | string(60), FK → `tags.slug` cascade on delete | |
| `transaction_id` | foreignId → `transactions.id` cascade on delete | |

- **Unique** (`tag_slug`, `transaction_id`) — enforces a tag at most once per transaction (FR-012).
- No timestamps required.

## Pivot: merchant_default_tag

| Field | Type | Notes |
|-------|------|-------|
| `tag_slug` | string(60), FK → `tags.slug` cascade on delete | |
| `merchant_id` | foreignId → `merchants.id` cascade on delete | |

- **Unique** (`tag_slug`, `merchant_id`) — a default tag appears once per merchant.
- No timestamps required.

## Lifecycle & Integrity Rules

- **Create-on-use**: tags are created via `firstOrCreate` by slug when applied to a transaction or assigned as a merchant default (FR-001, FR-011).
- **Global delete** (FR-015): deleting a `Tag` row cascades through both pivots, removing it from all transactions and merchant defaults. Renaming is out of scope.
- **Removing a link** (FR-007, FR-009): detaching a tag from a transaction or merchant does not delete the `Tag` itself.
- **Import application** (FR-010, FR-013): merchant default tags are attached only to newly created transactions during import; later changes to a merchant's defaults do not retroactively alter existing transactions.
- **No-merchant / no-defaults import** (FR-014): a transaction whose merchant is null or has no defaults is imported with no tags and no error.

## Entity Relationship Summary

```text
Tag (slug PK) ──< tag_transaction >── Transaction
Tag (slug PK) ──< merchant_default_tag >── Merchant ──< (import) >── Transaction
```
