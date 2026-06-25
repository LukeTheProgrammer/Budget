# Quickstart: Merchant Display Names & Alias Grouping

Manual verification steps (no automated tests, per constitution Principle II).
Run everything through Laravel Sail.

## Setup

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev      # or: ./vendor/bin/sail npm run build
```

Ensure you are logged in and have some imported transactions (use the existing
CSV import so multiple store-variant merchants exist, e.g. several "HY-VEE …"
rows).

## Verify US1 — Display name (P1)

1. Visit `/merchants`. Confirm each merchant shows its raw imported name and a
   transaction count.
2. On a merchant (e.g. "HY-VEE PR VILLAGE 1532"), set display name to "Hy-Vee".
   Confirm the list now shows "Hy-Vee" while the original name is still visible
   as the underlying/raw name.
3. Clear the display name; confirm the label reverts to the raw imported name.

## Verify US2 — Grouping (P2)

1. Select two or more Hy-Vee variants, choose one as the primary, optionally set
   the display name to "Hy-Vee", and submit the grouping action.
2. Confirm: only the primary merchant remains for those variants; its
   transaction count equals the sum of the merged merchants; the absorbed raw
   names now appear as aliases of the primary.
3. Confirm spending-by-merchant (dashboard/category rollup) reflects the
   combined total with no duplication or loss.
4. (Negative) Confirm the action is a no-op when fewer than two merchants are
   selected, and that the API rejects any attempt to include a merchant not
   owned by the current user.

## Verify US3 — Manage aliases (P3)

1. On the grouped merchant, view its aliases — all merged raw names are listed.
2. Add a new alias; confirm it appears immediately.
3. Try to add an alias whose normalized value already belongs to another
   merchant — confirm it is rejected with a clear message.
4. Remove an alias; confirm it disappears from the list.

## Verify FR-014 — Import auto-match

1. Place a CSV containing a raw name that matches an existing alias (e.g.
   "HY-VEE PR VILLAGE 1532") and run the import.
2. Confirm no new merchant is created — the transaction is associated with the
   alias's (primary) merchant.

## Quality gates (must pass before done)

```bash
./vendor/bin/sail composer run lint        # Pint
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run format
./vendor/bin/sail npm run types:check
```
