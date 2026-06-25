# Schema Contract: Transaction Data

This is the internal data contract for the budget app's spending-analysis schema. It is
the source of truth migrations must satisfy. (No external API/UI contract in scope.)

## Tables & Required Columns

### categories
- `id` PK
- `user_id` FK→users (cascade)
- `name` string(100), required
- `color` string(7), nullable
- timestamps
- UNIQUE(user_id, name)

### merchants
- `id` PK
- `user_id` FK→users (cascade)
- `category_id` FK→categories, nullable (set null on delete)
- `name` string(200), required
- `normalized_name` string(200), required
- timestamps
- UNIQUE(user_id, normalized_name)

### accounts
- `id` PK
- `user_id` FK→users (cascade)
- `name` string(100), required
- `last_four` char(4), nullable
- `currency` char(3), default 'USD'
- timestamps + softDeletes

### transactions
- `id` PK
- `account_id` FK→accounts (cascade)
- `merchant_id` FK→merchants, nullable (set null on delete)
- `amount_cents` bigint signed, required, non-zero
- `currency` char(3), default 'USD'
- `description` string(255), nullable
- `posted_at` date, required
- `import_hash` char(64), nullable
- timestamps + softDeletes
- UNIQUE(account_id, import_hash)
- INDEX(account_id, posted_at), INDEX(merchant_id)

## Invariants

- I1: Every transaction references a valid account owned by a user.
- I2: A transaction's effective category = `merchant.category_id` (NULL ⇒ Uncategorized).
- I3: `SUM(amount_cents)` over a category/period equals the arithmetic sum of its
  transactions (SC-002).
- I4: Re-inserting a transaction with the same `(account_id, import_hash)` is rejected by
  the unique index (SC-003).
- I5: Deleting a category nulls its merchants' `category_id`; deleting an account
  soft-deletes (retains) its transactions.

## Migration Order

1. `categories`
2. `merchants` (depends on categories)
3. `accounts`
4. `transactions` (depends on accounts, merchants)
