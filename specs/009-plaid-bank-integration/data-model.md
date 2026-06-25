# Phase 1 Data Model: Plaid Bank Account Integration

## New entity: PlaidConnection (Plaid Item)

Represents a user's authorized link to one financial institution.

**Table**: `plaid_connections`

| Field | Type | Notes |
|-------|------|-------|
| id | bigint PK | |
| user_id | bigint FK → users | owner; cascade on delete |
| plaid_item_id | string, unique | Plaid Item id |
| access_token | text, `encrypted` cast | never exposed to frontend (FR-011) |
| institution_id | string, nullable | Plaid institution id |
| institution_name | string, nullable | display name |
| status | string enum | `active` \| `reauth_required` \| `error` (FR-009) |
| transactions_cursor | text, nullable | `/transactions/sync` cursor (Sync State, R3) |
| last_synced_at | timestamp, nullable | |
| created_at / updated_at | timestamps | |

**Relationships**:
- `belongsTo(User)`
- `hasMany(Account)` (via `accounts.plaid_connection_id`)

**Validation / rules**:
- `plaid_item_id` unique per install.
- `status` transitions: `active` → `reauth_required` (on Plaid `ITEM_LOGIN_REQUIRED`) →
  `active` (after re-auth); any → `error` on persistent failure (FR-009).
- Disconnect (FR-010): call Plaid `/item/remove`, then delete the connection; linked
  `Account` rows keep their transactions but have `plaid_connection_id` nulled (FR-015).

## Modified entity: Account (existing)

Reuses the existing `accounts` table (spec clarification Q1 → A). Added columns:

| Field | Type | Notes |
|-------|------|-------|
| plaid_connection_id | bigint FK → plaid_connections, nullable | null for manual/CSV accounts; null-on-delete |
| plaid_account_id | string, nullable, indexed | Plaid account id; unique with connection |
| type | string, nullable | normalized `depository` \| `credit` (FR-003) |
| balance_cents | bigint, nullable | cached current/available balance in minor units |

Existing columns unchanged: `user_id`, `name`, `last_four` (← Plaid `mask`), `currency`
(← `iso_currency_code`), soft-delete `deleted_at`.

**Rules**:
- A Plaid-linked account has non-null `plaid_connection_id` + `plaid_account_id`; manual
  and CSV accounts leave both null (one shared table).
- `(plaid_connection_id, plaid_account_id)` unique to prevent duplicate account rows
  across syncs.

## Reused entity: Transaction (existing, unchanged schema)

No schema change. Plaid imports populate the existing fields and **reuse `import_hash`**:

| Field | Source from Plaid |
|-------|-------------------|
| account_id | mapped linked `Account` |
| merchant_id | resolved via existing merchant/category resolution (FR-013), nullable |
| amount_cents | Plaid `amount` × 100, sign normalized to app convention |
| currency | `iso_currency_code` |
| description | `name` / `merchant_name` |
| posted_at | `date` (or `authorized_date` for pending) |
| import_hash | stable hash of Plaid `transaction_id` (R3) |

**Rules**:
- Upsert via `Transaction::updateOrCreate(['import_hash' => $hash], $attributes)` (FR-005).
- `modified` set updates the existing row (pending→posted), `removed` set deletes/voids by
  `import_hash` (FR-006).
- No cross-source reconciliation with manual/CSV rows (FR-006a).

## Entity relationship summary

```text
User 1───* PlaidConnection 1───* Account 1───* Transaction *───1 Merchant *───1 Category
                                   ▲
                 manual/CSV Account (plaid_connection_id = null)
```

## State: PlaidConnection.status

```text
        link success
             │
             ▼
        ┌─ active ─┐
 sync   │          │  Plaid ITEM_LOGIN_REQUIRED
 ok ────┘          └────────► reauth_required ──(user re-auths)──► active
             │
   persistent failure
             ▼
           error
```
