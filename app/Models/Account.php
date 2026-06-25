<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $plaid_connection_id
 * @property string|null $plaid_account_id
 * @property string $name
 * @property string|null $type
 * @property string|null $last_four
 * @property string $currency
 * @property int|null $balance_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['user_id', 'plaid_connection_id', 'plaid_account_id', 'name', 'type', 'last_four', 'currency', 'balance_cents'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
        ];
    }

    /**
     * The user who owns the account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Plaid connection this account was imported from, if any. Manual and
     * CSV-imported accounts have no connection.
     *
     * @return BelongsTo<PlaidConnection, $this>
     */
    public function plaidConnection(): BelongsTo
    {
        return $this->belongsTo(PlaidConnection::class);
    }

    /**
     * Whether this account is linked to a financial institution via Plaid.
     */
    public function isLinked(): bool
    {
        return $this->plaid_connection_id !== null;
    }

    /**
     * The transactions recorded against this account.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
