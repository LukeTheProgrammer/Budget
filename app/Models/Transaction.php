<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $account_id
 * @property int|null $merchant_id
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $description
 * @property Carbon $posted_at
 * @property string|null $import_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['account_id', 'merchant_id', 'amount_cents', 'currency', 'description', 'posted_at', 'import_hash'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'posted_at' => 'date',
        ];
    }

    /**
     * Aggregate spending per category for a user over a date range.
     *
     * Transactions roll up to a category through their merchant; a NULL
     * category_id is reported as "Uncategorized". Only outflows (positive
     * amounts) count as spending; refunds and credits are excluded. Returns one
     * row per category with the summed amount in cents, ordered largest first.
     *
     * @param  Builder<Transaction>  $query
     * @param  \DateTimeInterface|string  $start
     * @param  \DateTimeInterface|string  $end
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function spendingByCategory(Builder $query, int $userId, $start, $end): Builder
    {
        return $query
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'transactions.merchant_id')
            ->leftJoin('categories', 'categories.id', '=', 'merchants.category_id')
            ->where('accounts.user_id', $userId)
            ->where('transactions.amount_cents', '>', 0)
            ->whereBetween('transactions.posted_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total_cents')
            ->select([
                'categories.id as category_id',
                'categories.name as category_name',
                'categories.color as color',
                DB::raw('SUM(transactions.amount_cents) as total_cents'),
            ]);
    }

    /**
     * Summarize spending for a user over a date range.
     *
     * Only outflows (positive amounts) count as spending; refunds and credits
     * (negative amounts) are excluded. Returns a single row with the summed
     * amount in cents and the transaction count.
     *
     * @param  Builder<Transaction>  $query
     * @param  \DateTimeInterface|string  $start
     * @param  \DateTimeInterface|string  $end
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function spendingSummary(Builder $query, int $userId, $start, $end): Builder
    {
        return $query
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('accounts.user_id', $userId)
            ->where('transactions.amount_cents', '>', 0)
            ->whereBetween('transactions.posted_at', [$start, $end])
            ->select([
                DB::raw('COALESCE(SUM(transactions.amount_cents), 0) as total_cents'),
                DB::raw('COUNT(*) as transaction_count'),
            ]);
    }

    /**
     * Aggregate spending into monthly totals for a user over a date range.
     *
     * Only outflows (positive amounts) count as spending. Returns one row per
     * month that has spending, keyed by a `YYYY-MM` month string; callers are
     * responsible for zero-filling months with no spending.
     *
     * @param  Builder<Transaction>  $query
     * @param  \DateTimeInterface|string  $start
     * @param  \DateTimeInterface|string  $end
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function monthlySpendingTrend(Builder $query, int $userId, $start, $end): Builder
    {
        return $query
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('accounts.user_id', $userId)
            ->where('transactions.amount_cents', '>', 0)
            ->whereBetween('transactions.posted_at', [$start, $end])
            ->groupBy('month')
            ->orderBy('month')
            ->select([
                DB::raw("DATE_FORMAT(transactions.posted_at, '%Y-%m') as month"),
                DB::raw('SUM(transactions.amount_cents) as total_cents'),
            ]);
    }

    /**
     * The user's most recent spending transactions (outflows only), newest
     * first, capped at the given limit. Merchant and category are eager-loaded
     * for display.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function recentSpending(Builder $query, int $userId, int $limit = 10): Builder
    {
        return $query
            ->where('transactions.amount_cents', '>', 0)
            ->whereHas('account', fn (Builder $account) => $account->where('user_id', $userId))
            ->with(['merchant.category'])
            ->orderByDesc('transactions.posted_at')
            ->orderByDesc('transactions.id')
            ->limit($limit);
    }

    /**
     * The user's largest spending transactions (outflows only) within a date
     * range, highest first, capped at the given limit. Merchant and category
     * are eager-loaded for display.
     *
     * @param  Builder<Transaction>  $query
     * @param  \DateTimeInterface|string  $start
     * @param  \DateTimeInterface|string  $end
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function largestSpending(Builder $query, int $userId, $start, $end, int $limit = 10): Builder
    {
        return $query
            ->where('transactions.amount_cents', '>', 0)
            ->whereHas('account', fn (Builder $account) => $account->where('user_id', $userId))
            ->whereBetween('transactions.posted_at', [$start, $end])
            ->with(['merchant.category'])
            ->orderByDesc('transactions.amount_cents')
            ->orderByDesc('transactions.id')
            ->limit($limit);
    }

    /**
     * Filter the user's transactions for the transactions table, scoped to the
     * authenticated user's accounts and ordered newest first. Each supported
     * filter is applied only when its key is present in $filters.
     *
     * Supported keys: start, end (Y-m-d posted_at bounds, inclusive),
     * account_id, merchant_id, category_id, min_amount_cents, max_amount_cents.
     *
     * @param  Builder<Transaction>  $query
     * @param  array{start?: ?string, end?: ?string, account_id?: ?int, merchant_id?: ?int, category_id?: ?int, min_amount_cents?: ?int, max_amount_cents?: ?int}  $filters
     * @return Builder<Transaction>
     */
    #[Scope]
    protected function filter(Builder $query, int $userId, array $filters = []): Builder
    {
        $query
            ->whereHas('account', fn (Builder $account) => $account->where('user_id', $userId))
            ->with(['merchant.category'])
            ->orderByDesc('transactions.posted_at')
            ->orderByDesc('transactions.id');

        if (isset($filters['start'])) {
            $query->whereDate('transactions.posted_at', '>=', $filters['start']);
        }

        if (isset($filters['end'])) {
            $query->whereDate('transactions.posted_at', '<=', $filters['end']);
        }

        if (isset($filters['account_id'])) {
            $query->where('transactions.account_id', $filters['account_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->where('transactions.merchant_id', $filters['merchant_id']);
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('merchant', fn (Builder $merchant) => $merchant->where('category_id', $filters['category_id']));
        }

        if (isset($filters['min_amount_cents'])) {
            $query->where('transactions.amount_cents', '>=', $filters['min_amount_cents']);
        }

        if (isset($filters['max_amount_cents'])) {
            $query->where('transactions.amount_cents', '<=', $filters['max_amount_cents']);
        }

        return $query;
    }

    /**
     * The account this transaction belongs to.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The merchant where the purchase was made (null when unresolved).
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * The tags applied to this transaction.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_transaction', 'transaction_id', 'tag_slug');
    }

    /**
     * The category this transaction rolls up to, derived through its merchant.
     *
     * @return HasOneThrough<Category, Merchant, $this>
     */
    public function category(): HasOneThrough
    {
        return $this->hasOneThrough(
            Category::class,
            Merchant::class,
            'id',          // merchants.id
            'id',          // categories.id
            'merchant_id', // transactions.merchant_id
            'category_id', // merchants.category_id
        );
    }
}
