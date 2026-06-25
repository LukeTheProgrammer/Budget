# Phase 1 Contracts: Plaid HTTP Routes

All routes live inside the existing `auth` + `verified` middleware group in
`routes/web.php` and are scoped to the authenticated user. Names use the `plaid.*`
prefix so Wayfinder generates typed helpers under `@/routes/plaid` / `@/actions`.

## POST `/plaid/link-token` — `plaid.link-token`

Creates a Plaid `link_token` to initialize Plaid Link on the client.

- **Controller**: `PlaidLinkTokenController@store`
- **Request body**: none (user derived from session)
- **Response (JSON)**: `{ "link_token": "link-sandbox-..." }`
- **Errors**: 503 if Plaid unavailable (FR-014); 401 if unauthenticated.

## POST `/plaid/exchange` — `plaid.exchange`

Exchanges a Plaid `public_token` (from a successful Link flow) for an access token and
creates the `PlaidConnection` + linked `Account` rows, then dispatches the initial sync.

- **Controller**: `PlaidLinkController@store`
- **Request** (`ExchangePublicTokenRequest`):
  ```json
  { "public_token": "public-sandbox-...", "institution": { "institution_id": "ins_1", "name": "Chase" } }
  ```
  Validation: `public_token` required string; `institution.name` nullable string.
- **Behavior**: exchange token → store `PlaidConnection` (encrypted access token) →
  upsert accounts → dispatch `SyncPlaidConnection` job (FR-002, FR-003).
- **Response**: Inertia redirect back to `settings/connections` with flash success.
- **Errors**: 422 invalid token; 503 Plaid error (no partial connection created — US1 AS2).

## GET `/settings/connections` — `connections.index`

Lists the user's connections with health and linked accounts (Inertia page).

- **Controller**: `PlaidConnectionController@index`
- **Inertia props**:
  ```ts
  {
    connections: Array<{
      id: number; institution_name: string | null;
      status: 'active' | 'reauth_required' | 'error';
      last_synced_at: string | null;
      accounts: Array<{ id: number; name: string; type: string | null;
                        last_four: string | null; balance_cents: number | null }>;
    }>;
  }
  ```

## POST `/plaid/connections/{connection}/sync` — `plaid.sync`

Triggers an on-demand sync for one connection (FR-007).

- **Controller**: `PlaidSyncController@store`
- **Authorization**: connection must belong to the authenticated user.
- **Behavior**: dispatch `SyncPlaidConnection` job; return redirect with flash.
- **Response**: Inertia redirect back with "Sync started" flash.

## DELETE `/plaid/connections/{connection}` — `plaid.connections.destroy`

Disconnects a connection (FR-010): calls Plaid `/item/remove`, nulls
`accounts.plaid_connection_id`, deletes the `PlaidConnection`. Transactions retained
(FR-015).

- **Controller**: `PlaidConnectionController@destroy`
- **Authorization**: connection must belong to the authenticated user.
- **Response**: Inertia redirect back with flash success.

## Internal contract: Plaid API calls (server → Plaid)

Encapsulated in `App\Services\Plaid\PlaidClient`:

| Method | Plaid endpoint | Purpose |
|--------|----------------|---------|
| `createLinkToken(User)` | `/link/token/create` | init Link |
| `exchangePublicToken(string)` | `/item/public_token/exchange` | get access_token + item_id |
| `getAccounts(string $accessToken)` | `/accounts/balance/get` | accounts + balances |
| `syncTransactions(string $accessToken, ?string $cursor)` | `/transactions/sync` | added/modified/removed + next cursor |
| `removeItem(string $accessToken)` | `/item/remove` | revoke access |

Credentials (`client_id`, `secret`, base URL by `PLAID_ENV`) injected from
`config/services.php` `plaid`.
