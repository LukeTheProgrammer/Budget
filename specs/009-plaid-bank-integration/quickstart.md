# Quickstart: Plaid Bank Account Integration (Sandbox)

Manual verification steps (no automated tests, per Constitution Principle II). Run inside
Laravel Sail.

## 1. Prerequisites

1. Create a free Plaid account and grab Sandbox `client_id` and `secret` from the Plaid
   Dashboard.
2. Add to `.env`:
   ```env
   PLAID_CLIENT_ID=your_client_id
   PLAID_SECRET=your_sandbox_secret
   PLAID_ENV=sandbox
   ```
   (`.env.example` gets the same keys with empty values.)
3. Ensure the queue worker is running so the import job processes:
   ```bash
   ./vendor/bin/sail artisan queue:work
   ```

## 2. Dependencies (require approval before install)

- Backend (if using the community SDK option): `./vendor/bin/sail composer require tomorrowideas/plaid-sdk-php`
  — or skip if using the in-house `Http` wrapper (no install).
- Frontend: `./vendor/bin/sail npm install react-plaid-link`

## 3. Migrate

```bash
./vendor/bin/sail artisan migrate
```

Creates `plaid_connections` and adds Plaid columns to `accounts`.

## 4. Link an account

1. Visit `http://localhost/settings/connections`.
2. Click **Link a bank** — Plaid Link opens.
3. In Sandbox, choose any institution and use credentials `user_good` / `pass_good`.
4. Complete the flow → connection appears with institution name and linked accounts
   (name, type, last four, balance). *(US1)*

## 5. Verify transaction import

1. After linking, the queued `SyncPlaidConnection` job runs the initial import.
2. Visit `http://localhost/transactions` — Sandbox transactions appear against the linked
   account, de-duplicated, with merchants/categories resolved where matched. *(US2)*

## 6. On-demand re-sync

1. On `settings/connections`, click **Sync** for the connection.
2. Confirm no duplicate transactions are created and balances refresh. *(US3, SC-004)*

## 7. Disconnect

1. Click **Disconnect** for a connection.
2. Confirm it disappears from the list and no longer syncs, while previously imported
   transactions remain on `transactions`. *(US4, FR-015)*

## 8. Re-auth path (optional, Sandbox)

Use Plaid Sandbox's `/sandbox/item/reset_login` to force `ITEM_LOGIN_REQUIRED`; confirm
the connection shows **Needs attention** and offers re-authentication. *(US4 AS2, FR-009)*

## Quality gates before finishing

```bash
./vendor/bin/sail composer run lint        # Pint
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run types:check
```
