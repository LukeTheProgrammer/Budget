<?php

namespace App\Models;

use App\Enums\FlowType;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property FlowType|null $default_flow_type
 * @property string $name
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'category_id', 'default_flow_type', 'name', 'confirmed_at'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'default_flow_type' => FlowType::class,
        ];
    }

    /**
     * Limit the query to merchants awaiting human review (auto-created from an
     * unrecognized descriptor and not yet confirmed).
     *
     * @param  Builder<Merchant>  $query
     */
    public function scopeUnconfirmed(Builder $query): void
    {
        $query->whereNull('confirmed_at');
    }

    /**
     * Limit the query to merchants the user has actually spent money with. A
     * checking account mints "merchants" for payroll deposits and internal
     * transfers; those are needed so classification rules have something to key
     * on, but they are noise on a page about managing spending.
     *
     * @param  Builder<Merchant>  $query
     */
    public function scopeWithExpenseActivity(Builder $query): void
    {
        $query->whereHas(
            'transactions',
            fn (Builder $transactions) => $transactions->whereIn('flow_type', FlowType::spendingValues()),
        );
    }

    /**
     * The user who owns the merchant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category this merchant is assigned to (null when uncategorized).
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The transactions made at this merchant.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The alternate names (store variants) that resolve to this merchant.
     *
     * @return HasMany<MerchantAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(MerchantAlias::class);
    }

    /**
     * The pattern-level matching rules that resolve to this merchant.
     *
     * @return HasMany<MerchantRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(MerchantRule::class);
    }

    /**
     * The tags applied by default to this merchant's transactions on import.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function defaultTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'merchant_default_tag', 'merchant_id', 'tag_slug');
    }
}
