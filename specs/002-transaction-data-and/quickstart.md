# Quickstart: Applying the Transaction Schema

Local-only (Laravel Sail). No tests — verify manually per Constitution Principle II.

## 1. Generate models + migrations + factories

```bash
./vendor/bin/sail artisan make:model Category -mf
./vendor/bin/sail artisan make:model Merchant -mf
./vendor/bin/sail artisan make:model Account -mf
./vendor/bin/sail artisan make:model Transaction -mf
```

(`-mf` creates the migration and factory alongside each model.)

## 2. Fill in migrations

Use the columns/indexes from [data-model.md](./data-model.md) and
[contracts/schema.md](./contracts/schema.md). Keep the migration order:
categories → merchants → accounts → transactions.

Money columns use `$table->bigInteger('amount_cents')` (signed). Use
`$table->foreignId('user_id')->constrained()->cascadeOnDelete()` etc.

## 3. Define relationships & casts

Add the Eloquent relationships from data-model.md to each model, plus casts
(`amount_cents` → integer, `posted_at` → date) and a `normalized_name` mutator on
`Merchant`.

## 4. Migrate

```bash
./vendor/bin/sail artisan migrate
```

## 5. Verify manually

```bash
# Inspect structure
./vendor/bin/sail artisan db:show --counts
# or use the Boost database-schema tool

# Smoke-check relationships in tinker (do NOT write a test file)
./vendor/bin/sail artisan tinker --execute '
  $u = App\Models\User::first();
  $c = $u->categories()->create(["name" => "Groceries"]);
  $m = $u->merchants()->create(["name" => "Whole Foods", "normalized_name" => "whole foods", "category_id" => $c->id]);
  $a = $u->accounts()->create(["name" => "Visa", "currency" => "USD"]);
  $t = $a->transactions()->create(["merchant_id" => $m->id, "amount_cents" => 4599, "currency" => "USD", "posted_at" => now()]);
  echo $t->merchant->category->name;  // Groceries
'
```

## 6. Optional sample data

Add a `BudgetSeeder` and register it in `DatabaseSeeder` for convenient local data:

```bash
./vendor/bin/sail artisan make:seeder BudgetSeeder
./vendor/bin/sail artisan db:seed --class=BudgetSeeder
```

## 7. Format

```bash
./vendor/bin/sail bin pint --dirty
```
