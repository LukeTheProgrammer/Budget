# Phase 1 Data Model: Transaction Schema

Money is stored as signed integer **cents** (`amount_cents`). All FKs use
`unsignedBigInteger` / `foreignId`. Default engine InnoDB, `utf8mb4`.

## Entity Relationship Overview

```text
users (existing)
  └─< accounts ──< transactions >── merchants >── categories
                                         │              │
        (account_id)        (merchant_id, nullable)  (category_id, nullable)
  users └─< categories (user_id)
```

- A **User** has many **Accounts** and many **Categories**.
- An **Account** has many **Transactions**.
- A **Category** has many **Merchants**.
- A **Merchant** belongs to one **Category** (nullable) and has many **Transactions**.
- A **Transaction** belongs to one **Account** and (optionally) one **Merchant**; its
  category is derived via `merchant.category`.

---

## Table: `categories`

User-defined spending groups (Groceries, Dining, Fuel...).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint unsigned | PK, auto-inc | |
| user_id | bigint unsigned | FK → users.id, cascade on delete | owner |
| name | varchar(100) | not null | |
| color | varchar(7) | nullable | hex for UI charts, e.g. `#3b82f6` |
| created_at / updated_at | timestamp | nullable | |

- **Unique**: `(user_id, name)` — no duplicate category names per user.
- **Index**: `user_id`.

## Table: `merchants`

Normalized vendors. Each maps to at most one category.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint unsigned | PK, auto-inc | |
| user_id | bigint unsigned | FK → users.id, cascade on delete | owner |
| category_id | bigint unsigned | FK → categories.id, nullable, null on delete | uncategorized when null |
| name | varchar(200) | not null | display name |
| normalized_name | varchar(200) | not null | lowercased/trimmed key for matching variants |
| created_at / updated_at | timestamp | nullable | |

- **Unique**: `(user_id, normalized_name)` — collapse name variants (edge case).
- **Index**: `category_id`, `user_id`.
- On category delete → `category_id` set null (merchant becomes uncategorized).

## Table: `accounts`

Credit card accounts owned by a user.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint unsigned | PK, auto-inc | |
| user_id | bigint unsigned | FK → users.id, cascade on delete | owner |
| name | varchar(100) | not null | e.g. "Visa ...1234" |
| last_four | char(4) | nullable | masked card number |
| currency | char(3) | not null, default 'USD' | ISO 4217 |
| created_at / updated_at | timestamp | nullable | |
| deleted_at | timestamp | nullable | soft delete |

- **Index**: `user_id`.

## Table: `transactions`

A single purchase (positive) or refund/credit (negative).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint unsigned | PK, auto-inc | |
| account_id | bigint unsigned | FK → accounts.id, cascade on delete | |
| merchant_id | bigint unsigned | FK → merchants.id, nullable, null on delete | FR-009 |
| amount_cents | bigint | not null | signed; negative = refund/credit (FR-006) |
| currency | char(3) | not null, default 'USD' | denormalized from account for clarity |
| description | varchar(255) | nullable | raw statement descriptor |
| posted_at | date | not null | transaction/post date (FR-001) |
| import_hash | char(64) | nullable | dedupe key (FR-008) |
| created_at / updated_at | timestamp | nullable | |
| deleted_at | timestamp | nullable | soft delete |

- **Unique**: `(account_id, import_hash)` — idempotent re-import (SC-003). Null hashes are
  exempt (MySQL allows multiple NULLs in a unique index).
- **Index**: `(account_id, posted_at)` — time-range reporting; `merchant_id`; `posted_at`.

---

## Validation Rules (enforced in app / form requests)

- `amount_cents` must be a non-zero integer.
- `currency` must be a 3-letter ISO code.
- `posted_at` must be a valid date, not in the future beyond today (configurable).
- `categories.name` / `merchants.name` required, trimmed.
- `merchant.normalized_name` derived as `strtolower(trim(name))` on save.

## Derived Reporting (no schema; query shape)

Per-category spend over a period:

```sql
SELECT c.id, c.name, SUM(t.amount_cents) AS total_cents
FROM transactions t
JOIN accounts a   ON a.id = t.account_id
LEFT JOIN merchants  m ON m.id = t.merchant_id
LEFT JOIN categories c ON c.id = m.category_id
WHERE a.user_id = :user
  AND t.posted_at BETWEEN :start AND :end
GROUP BY c.id, c.name;   -- NULL category row = "Uncategorized"
```

## Eloquent Relationships

- `User`: `hasMany(Account)`, `hasMany(Category)`.
- `Account`: `belongsTo(User)`, `hasMany(Transaction)`.
- `Category`: `belongsTo(User)`, `hasMany(Merchant)`.
- `Merchant`: `belongsTo(User)`, `belongsTo(Category)`, `hasMany(Transaction)`.
- `Transaction`: `belongsTo(Account)`, `belongsTo(Merchant)`;
  `category()` via `hasOneThrough(Category, Merchant)` for convenient access.

Casts: `amount_cents` → `integer`, `posted_at` → `date`. Optionally an `amount`
accessor returning a money value (cents/100) for display.
