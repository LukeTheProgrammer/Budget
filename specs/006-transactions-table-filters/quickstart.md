# Quickstart: Transactions Table with Filters

How to build and manually verify this feature (no automated tests per Constitution Principle II).

## Build order

1. **Route** — add to `routes/web.php` inside the `auth, verified` group:
   `Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');`
2. **Query scope** — add `#[Scope] filter(...)` to `app/Models/Transaction.php` (see data-model.md).
3. **FormRequest** — `./vendor/bin/sail artisan make:request Transactions/TransactionFilterRequest` and add the nullable rules (scoped `exists` for merchant/category).
4. **Controller** — `./vendor/bin/sail artisan make:controller Transactions/TransactionController`; `index()` validates via the request, runs the scope, paginates 50 `->withQueryString()`, maps rows, and returns `Inertia::render('transactions/index', ...)` with options + echoed filters.
5. **Table primitive** — add shadcn table: `./vendor/bin/sail npx shadcn@latest add table` (creates `components/ui/table.tsx`).
6. **Filter bar** — `resources/js/components/transactions/transaction-filters.tsx`: date inputs, merchant `Select`, category `Select`, min/max amount inputs, plus "Clear filters". On change, call the Wayfinder `index` route via `router.get(..., { preserveState: true, preserveScroll: true, replace: true })` with `page` omitted (resets to 1).
7. **Page** — `resources/js/pages/transactions/index.tsx`: render the filter bar + table from props, an empty state, and pagination controls built from `pagination.links`.
8. **Nav** — add a sidebar link to `transactions.index` alongside the existing dashboard/merchants links.

## Manual verification (browser at http://localhost)

- `./vendor/bin/sail up -d && ./vendor/bin/sail npm run dev`, log in as a user with imported transactions.
- Visit `/transactions`: table shows newest-first rows with date, merchant, category, description, amount; empty state if none.
- Set a date range → only in-range rows; set merchant → only that merchant; set category → only that category; set min/max amount → only in-range amounts; combine filters → AND behavior.
- Confirm the URL query string updates on every filter change and `page` resets to 1.
- Reload the filtered URL and open it in a new tab → identical results (shareable/persistent).
- Edit the URL with a bad value (e.g. `merchant_id=999999` or `start=notadate`) → page still loads, bad filter ignored.
- Paginate to page 2, then change a filter → returns to page 1.
- Log in as a second user → none of the first user's transactions, merchants, or categories appear via any filter or crafted ID.

## Quality gates (before finalizing)

- `./vendor/bin/sail composer exec -- pint --dirty --format agent` (PHP).
- `./vendor/bin/sail npm run lint && ./vendor/bin/sail npm run format && ./vendor/bin/sail npm run types:check` (frontend).
