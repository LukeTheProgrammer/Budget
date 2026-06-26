# Data Model: Manage Accounts

No schema migrations are required — the existing `accounts` and `transactions` tables already support everything this feature needs (including `soft_deletes`). This document captures the entities, fields, validation, and behavior as they apply to this feature.

## Entity: Account

Existing table `accounts` (`app/Models/Account.php`). Relevant fields:

| Field | Type | Notes for this feature |
|-------|------|------------------------|
| `id` | int (PK) | Route-model-bound. |
| `user_id` | int (FK → users) | Owner; set to the authenticated user on create; never client-settable. |
| `plaid_connection_id` | int? (FK) | NULL ⇒ **manual** account (fully editable/deletable). Non-NULL ⇒ **linked** (name-only edit, not deletable here). |
| `plaid_account_id` | string? | Institution-derived; never edited here. |
| `name` | string (≤100) | **Required**. Editable for all accounts. |
| `type` | string? | Optional; one of `AccountType` enum values. Cast to `AccountType`. Not editable for linked accounts. |
| `last_four` | char(4)? | Optional; up to 4 digits. Not editable for linked accounts. |
| `currency` | char(3) | 3-letter code, default `USD`. Not editable for linked accounts. |
| `balance_cents` | bigint? | Optional integer cents; may be negative (liabilities). Entered as decimal, stored as cents. Not editable for linked accounts. |
| `deleted_at` | timestamp? | Soft-delete marker. |

**Relationships**:
- `belongsTo(User)` — owner.
- `belongsTo(PlaidConnection)` — optional link source.
- `hasMany(Transaction)` — cascade soft-deleted on account delete (see Behavior).

**Derived**: `isLinked(): bool` = `plaid_connection_id !== null` (already implemented).

### New: `AccountType` enum (`app/Enums/AccountType.php`)

String-backed, TitleCase keys:

| Case | Value |
|------|-------|
| `Checking` | `checking` |
| `Savings` | `savings` |
| `Credit` | `credit` |
| `Cash` | `cash` |
| `Investment` | `investment` |

### Validation rules

Applied via `AccountStoreRequest` / `AccountUpdateRequest`.

| Field | Create (manual) | Update (manual) | Update (linked) |
|-------|-----------------|-----------------|-----------------|
| `name` | required, string, max:100 | required, string, max:100 | required, string, max:100 |
| `type` | nullable, enum | nullable, enum | not accepted |
| `currency` | nullable, size:3 (default USD) | nullable, size:3 | not accepted |
| `last_four` | nullable, digits_between:1,4 | nullable, digits_between:1,4 | not accepted |
| `balance` (decimal) | nullable, numeric (→ cents) | nullable, numeric (→ cents) | not accepted |

- Linked accounts: only `name` is validated/persisted; other keys are ignored/forbidden.
- `user_id`, `plaid_*` are never mass-assignable from the request.

### State / lifecycle

```
(none) --create--> Active (manual)
Active --edit--> Active
Active (manual) --delete--> Soft-deleted  ──╮ cascade
                                            └─> all related Transactions soft-deleted
Linked account: created only via Plaid sync (out of scope here); editable name-only; not deletable on this page.
```

## Entity: Transaction (affected, not owned by this feature)

Existing table `transactions` (`app/Models/Transaction.php`), uses `SoftDeletes`.

- **Behavior change**: when an `Account` is soft-deleted, its transactions are soft-deleted in the same DB transaction (via an `Account` `deleting` model event).
- **Query guard**: raw-join spending scopes/aggregates that read the `transactions` table directly (`Transaction` scopes joining `accounts`; `BudgetController` raw subquery) must add `whereNull('transactions.deleted_at')` (and `accounts.deleted_at` where joined) so soft-deleted rows stay hidden. Eloquent `Transaction::query()` reads already exclude them automatically.

## Ownership & authorization

- Every account read/write is scoped to `auth()->user()`.
- `AccountPolicy`: `view`/`update`/`delete` require `account.user_id === user.id`; `delete` and full (non-name) edits additionally require `! account.isLinked()`.
