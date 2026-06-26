# Quickstart: Manage Accounts

Manual verification steps (no automated tests, per project constitution). Run the app with Sail and Vite first.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev      # or: composer run dev
```

Open `http://localhost`, sign in, then go to **Settings → Accounts** (`/settings/accounts`).

## 1. Create (User Story 1 — P1)

1. Click **Add account**.
2. Submit with an empty name → expect an inline "name is required" validation error and no account created.
3. Enter name `Cash Wallet`, leave type blank, save → account appears in the list; a success toast shows.
4. Reload the page → the account is still present.

## 2. Edit (User Story 2 — P2)

1. Edit `Cash Wallet`: change name to `Pocket Cash`, set type `Cash`, set a balance, save → updated values appear in the list.
2. Edit again, clear the name, save → inline validation error; change not saved.
3. If a Plaid-linked account exists, open its edit dialog → only **name** is editable; type/last-four/currency/balance are read-only; no Delete option is offered.

## 3. Delete (User Story 3 — P3)

1. For a manual account that has transactions, click **Delete** → a confirmation dialog appears.
2. Dismiss it → the account is unchanged.
3. Confirm deletion → the account disappears from the list and a success toast shows.
4. Visit **Transactions** / **Dashboard** / **Budgets** → the deleted account's transactions no longer appear in those views (hidden), but remain in the database (soft-deleted).

## 4. Ownership isolation (FR-007)

1. While signed in as user A, note an account id.
2. As user B, attempt `PATCH`/`DELETE /settings/accounts/{A's id}` → expect HTTP 403; the account is unaffected.

## Quality gates (required before done)

```bash
./vendor/bin/sail composer run lint          # Pint (PHP)
./vendor/bin/sail npm run lint                # ESLint
./vendor/bin/sail npm run format              # Prettier
./vendor/bin/sail npm run types:check         # tsc --noEmit
```

All must pass. Wayfinder route helpers (`@/routes/accounts`, `@/actions`) regenerate automatically on `npm run dev`/`build`.
