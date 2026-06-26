# Research: Manage Accounts

All Technical Context items were resolvable from the existing codebase and the clarified spec; there were no open `NEEDS CLARIFICATION` markers. This document records the design decisions that shape Phase 1.

## Decision 1 — Reuse the Settings module pattern

- **Decision**: Implement as a settings page (`settings-accounts`) with `Settings\AccountController`, form requests under `app/Http/Requests/Settings/`, and a nav entry in `resources/js/layouts/settings/layout.tsx`.
- **Rationale**: The spec clarification places the UI in the settings section. `ProfileController`/`SecurityController` and the `settings-connections` page are direct precedents (Inertia::render of a `settings-*` page, `Inertia::flash('toast', …)` on success, Wayfinder route helpers in the nav). The `app.tsx` layout resolver already maps any `settings-` page to `[AppLayout, SettingsLayout]`.
- **Alternatives considered**: Dedicated top-level `/accounts` page under `AppLayout` — rejected per the clarification answer (settings section chosen).

## Decision 2 — Account type as a PHP enum

- **Decision**: Add `App\Enums\AccountType` (string-backed: `Checking`, `Savings`, `Credit`, `Cash`, `Investment`) and cast `Account::$type` to it. Validate the request field with `Rule::enum(AccountType::class)`, `nullable`.
- **Rationale**: Spec clarification: type is an optional selection from a fixed set, free-form not accepted. The `accounts.type` column is already a nullable string, so the enum casts cleanly with no migration. Enum keys use TitleCase per project PHP rules. The frontend renders the same set in a shadcn `Select`.
- **Alternatives considered**: Free-form string (rejected by clarification); DB enum column / lookup table (over-engineered, violates Simplicity).

## Decision 3 — Manual vs linked accounts

- **Decision**: Only manual accounts (`plaid_connection_id IS NULL`, i.e. `Account::isLinked() === false`) may be created here, fully edited, and deleted. Linked accounts are listed read-only except their display `name`, which may be edited; they cannot be deleted on this page.
- **Rationale**: Matches FR-010 and the assumption that disconnecting a linked institution is handled by the existing Connections flow (`PlaidConnectionController::destroy`). Institution-derived fields (`type`, `last_four`, `balance_cents`, `currency`, `plaid_*`) must not be hand-edited as they are overwritten on sync.
- **Enforcement**: `AccountUpdateRequest` restricts editable fields to `name` when the account is linked; `AccountPolicy::delete` returns false for linked accounts; the React UI hides destructive/disabled controls accordingly.

## Decision 4 — Deletion hides the account AND its transactions

- **Decision**: On delete, soft-delete the account and cascade soft-delete its transactions in the same operation (model `deleting` event on `Account`, wrapped in a DB transaction). Restoration is out of scope.
- **Rationale**: Spec clarification requires both to disappear from normal views while remaining in the database. Most transaction reads use `Transaction::query()` (Eloquent), which respects the `SoftDeletes` global scope, so cascade soft-deleting the transactions hides them everywhere those queries are used. Both models already use `SoftDeletes`.
- **Raw-join caveat (must address)**: Several spending aggregates use raw `->join('accounts', …)` on the `transactions`/`accounts` tables (`Transaction` scopes around lines 67–126; `BudgetController` `->from('transactions')` subquery). Raw joins bypass Eloquent global scopes, so trashed rows would still appear. These queries MUST add explicit `whereNull('transactions.deleted_at')` (and `whereNull('accounts.deleted_at')` where accounts are joined) to honor the hide-on-delete requirement.
- **Alternatives considered**:
  - *Block deletion when transactions exist* — rejected by clarification.
  - *Leave transactions visible (orphaned)* — rejected by clarification.
  - *Filter every read query by account trashed-state instead of cascading* — more invasive and fragile across the many call sites; cascade soft-delete centralizes the behavior.

## Decision 5 — Balance entry in major units, stored as cents

- **Decision**: The form collects balance as a decimal amount in the account's currency; the controller converts to integer cents for `balance_cents` (and back to a decimal for editing). Negative values allowed (liabilities). Empty balance stays `null`.
- **Rationale**: `balance_cents` is an integer-cents column (consistent with `transactions.amount_cents`). Existing UI formats cents-with-currency (see insights/budgets components), so display reuse is straightforward.

## Decision 6 — Authorization via policy

- **Decision**: Add `AccountPolicy` with `view`, `update`, `delete` checking `account.user_id === user.id` (and `delete`/full-edit additionally requiring the account be manual). Controller uses `$this->authorize(...)` / route-model-bound `Account`.
- **Rationale**: FR-007 ownership isolation; mirrors the explicit `abort_unless($connection->user_id === …, 403)` ownership checks already used in Plaid controllers, but uses the idiomatic policy mechanism for a user-facing CRUD resource.

## Open items

None. All Technical Context fields resolved; ready for Phase 1.
