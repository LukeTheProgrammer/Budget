<?php

namespace App\Services\Transactions;

use App\Enums\FlowTypeSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\Transaction;
use App\Services\Merchants\NameResolver;
use Illuminate\Support\Facades\DB;

/**
 * Persists normalized transaction rows into the database, resolving merchants,
 * de-duplicating by import hash, and applying default tags. This is the shared
 * storage engine behind every import entry point (fixed-layout Chase CSV,
 * mapped front-end upload), so dedup and merchant behavior stay identical
 * regardless of how a row was parsed.
 *
 * Callers MUST prime the resolver for the relevant user via
 * {@see NameResolver::forUser()} before storing rows.
 */
class TransactionRowStore
{
    public function __construct(
        private NameResolver $nameResolver,
        private FlowTypeClassifier $flowTypeClassifier,
        private TransferPairer $transferPairer,
    ) {}

    /**
     * Prime the merchant resolver and flow-type classifier for a user.
     * Convenience pass-through so callers do not need to depend on either
     * collaborator directly.
     */
    public function forUser(int $userId): void
    {
        $this->nameResolver->forUser($userId);
        $this->flowTypeClassifier->forUser($userId);
    }

    /**
     * Close out an import run: pair up the two legs of any internal transfers
     * the batch produced. Callers MUST invoke this once every row is stored,
     * because a transfer's counterpart leg may only appear later in the file
     * (or in a file imported earlier) and cannot be matched row by row.
     */
    public function finish(int $userId): void
    {
        $this->transferPairer->pairForUser($userId);
    }

    /**
     * Persist one normalized row as a transaction, resolving/creating its
     * merchant and de-duplicating on the import hash. An optional raw category
     * name (supplied by layouts that carry one, e.g. Chase) is back-filled onto
     * newly resolved merchants.
     */
    public function store(Account $account, NormalizedTransactionRow $row, ImportResult $result, ?string $categoryName = null): void
    {
        $merchant = $this->resolveMerchant($account->user_id, $row->merchantName, $categoryName, $result);

        $importHash = hash(
            'sha256',
            implode('|', [
                $account->id,
                $row->postedAt->format('Y-m-d'),
                $row->amountCents,
                $merchant->id,
            ]),
        );

        $transactionProps = [
            'account_id' => $account->id,
            'merchant_id' => $merchant->id,
            'amount_cents' => $row->amountCents,
            'currency' => $row->currency,
            'description' => $row->description,
            'posted_at' => $row->postedAt,
            'import_hash' => $importHash,
        ];

        $existing = Transaction::query()
            ->where('import_hash', $importHash)
            ->first();

        // A flow type the user set by hand outlives any re-import of the same
        // row; only automatic classifications are recomputed.
        if ($existing?->flow_type_source !== FlowTypeSource::User) {
            $transactionProps['flow_type'] = $this->flowTypeClassifier->classify(
                $account,
                $merchant,
                $row->amountCents,
                $row->description,
            );
            $transactionProps['flow_type_source'] = FlowTypeSource::Auto;
        }

        $transaction = Transaction::updateOrCreate(
            ['import_hash' => $importHash],
            $transactionProps
        );

        if ($transaction->wasRecentlyCreated) {
            $this->applyDefaultTags($transaction, $merchant);
            $result->incrementImported();
        } else {
            $result->incrementSkipped();
        }
    }

    /**
     * Apply the merchant's default tags to a freshly imported transaction. Only
     * runs for newly created transactions (re-imported rows are left untouched),
     * and is a no-op when the merchant has no default tags.
     */
    private function applyDefaultTags(Transaction $transaction, Merchant $merchant): void
    {
        $slugs = $merchant->defaultTags()->pluck('tags.slug')->all();

        if ($slugs !== []) {
            $transaction->tags()->syncWithoutDetaching($slugs);
        }
    }

    /**
     * Resolve the merchant for a raw statement descriptor. The DB-backed
     * NameResolver (exact aliases + prefix/regex rules) is consulted first so
     * known store variants collapse onto a single confirmed merchant. When it
     * can't identify the name, fall back to an alias lookup that also catches
     * merchants created earlier in this same run, and only then auto-create an
     * unconfirmed merchant flagged for review.
     */
    private function resolveMerchant(int $userId, string $rawName, ?string $categoryName, ImportResult $result): Merchant
    {
        $merchant = $this->nameResolver->resolve($rawName);

        if ($merchant !== null) {
            return $this->backfillCategory($merchant, $categoryName);
        }

        // Resolver didn't know it. Catch repeats of unconfirmed merchants
        // created earlier this run (the resolver's cache predates them).
        $normalizedName = mb_strtolower(trim($rawName));

        $alias = MerchantAlias::query()
            ->where('user_id', $userId)
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($alias?->merchant !== null) {
            return $this->backfillCategory($alias->merchant, $categoryName);
        }

        $result->incrementUnconfirmedMerchants();

        return $this->createMerchantWithAlias($userId, $rawName, $rawName, $categoryName);
    }

    /**
     * Assign a category to a merchant that has none yet, resolving/creating the
     * category from the raw name. Merchants that already have a category are
     * left untouched so manual reassignments are not overwritten.
     */
    private function backfillCategory(Merchant $merchant, ?string $categoryName): Merchant
    {
        if ($merchant->category_id !== null || $categoryName === null) {
            return $merchant;
        }

        $category = $this->resolveCategory($merchant->user_id, $categoryName);

        if ($category !== null) {
            $merchant->update(['category_id' => $category->id]);
        }

        return $merchant;
    }

    /**
     * Find or create the user's category for the given raw category name,
     * matching case-insensitively on name. Returns null when no category was
     * supplied on the row.
     */
    private function resolveCategory(int $userId, ?string $categoryName): ?Category
    {
        if ($categoryName === null) {
            return null;
        }

        $category = Category::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
            ->first();

        return $category
            ?? Category::create(['user_id' => $userId, 'name' => $categoryName]);
    }

    /**
     * Create an unconfirmed merchant together with a self-alias, so the merchant
     * is always resolvable via the alias table while it awaits review. Wrapped
     * in a transaction so a merchant is never persisted without its alias.
     */
    private function createMerchantWithAlias(int $userId, string $merchantName, string $aliasName, ?string $categoryName): Merchant
    {
        return DB::transaction(function () use ($userId, $merchantName, $aliasName, $categoryName): Merchant {
            $category = $this->resolveCategory($userId, $categoryName);

            $merchant = Merchant::create([
                'user_id' => $userId,
                'category_id' => $category?->id,
                'name' => $merchantName,
            ]);

            $merchant->aliases()->create(['user_id' => $userId, 'name' => $aliasName]);

            return $merchant;
        });
    }
}
