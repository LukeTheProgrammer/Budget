# Quickstart: Dashboard Spending Insights

## Prerequisites
- Sail stack running: `./vendor/bin/sail up -d`
- Migrated DB with some transactions for your user (use factories/seeder if empty).

## Add the chart dependency (approved)
```bash
./vendor/bin/sail npm install recharts
# Add the shadcn chart primitive at resources/js/components/ui/chart.tsx
```

## Backend
1. Create the controller:
   ```bash
   ./vendor/bin/sail artisan make:controller DashboardController --no-interaction
   ```
2. Add read-only `#[Scope]` methods on `App\Models\Transaction` for: period summary (sum + count), monthly trend (12 months), recent (10), largest (10) — all joining `accounts`, filtering `accounts.user_id`, `amount_cents > 0`, and the date window (mirror the existing `spendingByCategory` scope).
3. In `DashboardController@index`, resolve the `period` query param, compute summary/categories/trend/recent/largest, and `Inertia::render('dashboard', [...])` per `contracts/dashboard.md`.
4. In `routes/web.php`, replace `Route::inertia('dashboard', 'dashboard')` with `Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');`.

## Frontend
1. Build widgets under `resources/js/components/dashboard/`: `period-selector`, `summary-cards`, `category-breakdown` (chart + table), `spending-trend` (chart), `transactions-table` (reused for recent + largest).
2. Compose them in `resources/js/pages/dashboard.tsx` from typed page props; use the Wayfinder `dashboard()` helper for the period selector navigation.
3. Use existing shadcn primitives (`card`, `select`/`toggle-group`, `badge`, `skeleton`) and format money from cents with the transaction currency.
4. Add empty states for each widget.

## Verify (manual — no tests per constitution)
```bash
./vendor/bin/sail npm run dev
```
- Visit `http://localhost/dashboard`. Confirm summary, category chart+table, 12-month trend, and both transaction tables render.
- Switch period presets → all widgets update; figures reconcile with transactions.
- Check an account with no transactions in the period → empty states, no errors.

## Quality gates before finalizing
```bash
./vendor/bin/sail composer run lint        # Pint
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run format
./vendor/bin/sail npm run types:check
```
