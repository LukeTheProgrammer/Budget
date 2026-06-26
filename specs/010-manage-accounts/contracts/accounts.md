# Contracts: Manage Accounts

The feature exposes server-rendered Inertia pages plus form-submitting HTTP endpoints (no JSON API). All routes live in `routes/settings.php` under the `auth` middleware group and resolve through Wayfinder helpers in the React page. Route names are nested under `accounts.*`.

## HTTP endpoints

| Method | URI | Name | Controller action | Purpose |
|--------|-----|------|-------------------|---------|
| GET | `/settings/accounts` | `accounts.index` | `Settings\AccountController@index` | Render `settings-accounts` page with the user's accounts. |
| POST | `/settings/accounts` | `accounts.store` | `Settings\AccountController@store` | Create a manual account. |
| PATCH | `/settings/accounts/{account}` | `accounts.update` | `Settings\AccountController@update` | Update an account the user owns (linked ⇒ name only). |
| DELETE | `/settings/accounts/{account}` | `accounts.destroy` | `Settings\AccountController@destroy` | Soft-delete a manual account + cascade soft-delete its transactions. |

`{account}` is route-model-bound; the policy aborts 403 if not owned by the requester.

## index — page props

`Inertia::render('settings-accounts', { accounts, accountTypes })`

```ts
type AccountListItem = {
  id: number;
  name: string;
  type: string | null;          // AccountType value or null
  last_four: string | null;
  currency: string;             // e.g. "USD"
  balance_cents: number | null; // may be negative
  is_linked: boolean;           // true ⇒ name-only edit, not deletable here
  institution_name: string | null; // present when linked, for display
};

type PageProps = {
  accounts: AccountListItem[];   // ordered by name; only non-trashed
  accountTypes: { value: string; label: string }[]; // dropdown options
};
```

## store — request

```
POST /settings/accounts
{
  name: string;            // required, max 100
  type?: string | null;    // optional, must be a valid AccountType value
  currency?: string;       // optional 3-letter, defaults "USD"
  last_four?: string|null; // optional, 1–4 digits
  balance?: string|number|null; // optional decimal in major units; stored as cents
}
```

- **Success**: redirect back to `accounts.index` with `Inertia::flash('toast', {type:'success', message:'Account created.'})`. New account appears in the list.
- **Validation error (422)**: field errors surfaced inline by the Inertia form; no account created.

## update — request

```
PATCH /settings/accounts/{account}
// manual account: same body as store
// linked account: only { name } is accepted/persisted
```

- **Authorization**: 403 if the account is not owned by the user.
- **Success**: redirect to `accounts.index` with success toast; updated values shown.
- **Validation error (422)**: inline errors; no change persisted.

## destroy — request

```
DELETE /settings/accounts/{account}
```

- **Authorization**: 403 if not owned, or if the account is linked (linked accounts are removed via the Connections disconnect flow, not here).
- **Success**: account and its transactions soft-deleted; redirect to `accounts.index` with success toast; both vanish from normal views.

## UI contract (page behavior)

- Account list shows at least name, type, balance (formatted with currency), and a linked badge/institution name for linked accounts.
- "Add account" opens a create dialog (shadcn `Dialog` + `Select` for type).
- Each manual account row offers Edit and Delete; Delete opens a confirmation dialog before issuing the request.
- Linked account rows offer Edit (name field only; other fields shown disabled/read-only) and no Delete.
- Empty state: a friendly prompt to add the first account when the list is empty.
