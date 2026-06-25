# Phase 1 Data Model: Merchant Display Names & Alias Grouping

## Entity: Merchant (modified)

Existing table `merchants`. One new column.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | existing |
| user_id | FK → users, cascade delete | existing |
| category_id | FK → categories, nullable | existing |
| name | string(200) | existing — original imported name; preserved (FR-003) |
| **display_name** | **string(200), nullable** | **NEW — user-facing friendly name (FR-001)** |
| created_at / updated_at | timestamps | existing |

- **Indexes**: none beyond the `user_id` foreign key. Matching/uniqueness now
  lives entirely on `merchant_aliases(user_id, normalized_name)`; a merchant's
  own name is held as a "self-alias", so `merchants` no longer carries a
  `normalized_name` column.
- **Accessor**: `label` returns `display_name ?? name` (FR-002). UI renders `label`.
- **Relations**: `belongsTo(User)`, `belongsTo(Category)`, `hasMany(Transaction)`,
  **`hasMany(MerchantAlias)`** (NEW).
- **Validation**: `display_name` optional, max 200, trimmed; empty input clears
  it to `null` (FR-004).

## Entity: MerchantAlias (new)

New table `merchant_aliases`.

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | |
| user_id | FK → users, cascade delete | per-user scoping (FR-011) |
| merchant_id | FK → merchants, cascade delete | the resolved primary merchant |
| name | string(200) | original/raw alias text (e.g. "HY-VEE PR VILLAGE 1532") |
| normalized_name | string(200) | `mb_strtolower(trim(name))` (FR-013) |
| created_at / updated_at | timestamps | |

- **Indexes**: `unique(user_id, normalized_name)` (FR-010 — an alias name maps
  to at most one merchant per user). Index `merchant_id` for listing.
- **Mutator**: `name` setter also sets `normalized_name`, mirroring
  `Merchant::name()`.
- **Relations**: `belongsTo(User)`, `belongsTo(Merchant)`.
- **Validation rules**:
  - Adding an alias: `name` required, max 200; after normalization it must not
    already exist for the user (on any merchant) — else reject with a message
    (FR-010, US3 scenario 3).
  - An alias whose normalized value equals the owning merchant's own
    `normalized_name` is treated as already represented and not duplicated
    (edge case).

## Relationships overview

```text
User 1───* Merchant 1───* MerchantAlias
                 │
                 1
                 │
                 *
            Transaction   (transaction.merchant_id → merchant.id)
```

## Grouping merge — state transition (MerchantGrouper)

Inputs: acting `User`, a **primary** merchant id, a set of **absorbed** merchant
ids (≥1), optional `display_name`.

Within one `DB::transaction`:

1. **Authorize**: every merchant id (primary + absorbed) belongs to the acting
   user; otherwise abort with no changes (FR-011, US2 scenario 4). Reject if
   fewer than 2 distinct merchants or primary ∈ absorbed (no-op edge case).
2. For each absorbed merchant:
   a. `Transaction::where('merchant_id', absorbed.id)->update(['merchant_id' => primary.id])` (FR-006, FR-012).
   b. Create a `MerchantAlias` on `primary` from the absorbed merchant's `name`
      (skip if normalized value already present on primary) (FR-007).
   c. Re-point the absorbed merchant's existing aliases to `primary` (or
      recreate, skipping duplicates) — all aliases retained (edge case).
   d. `absorbed.delete()` (FR-007a).
3. If a `display_name` was supplied, set it on `primary`; otherwise leave the
   primary's existing name/display_name unchanged (FR-005a). Primary's
   `category_id` is left as-is (edge case: absorbed category does not override).
4. Commit. Result: primary now owns all member transactions and all member raw
   names as aliases; no transaction orphaned (FR-012, SC-002, SC-003).

## Import resolution change (CsvTransactionImporter)

Per imported row, resolve merchant as:

```text
normalized = mb_strtolower(trim(row.merchantName))
alias = MerchantAlias::firstWhere(user_id = account.user_id, normalized_name = normalized)
merchant = alias
    ? alias.merchant
    : create Merchant {user_id, name: row.merchantName}
        + self-alias MerchantAlias {user_id, merchant_id, name: row.merchantName}
```

A new merchant is always created together with a self-alias of its own name, so
the alias table is the sole matching key. `import_hash` uses `merchant.id` (the
resolved/primary merchant) in place of the removed `normalized_name`.
