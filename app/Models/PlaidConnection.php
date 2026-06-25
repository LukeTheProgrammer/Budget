<?php

namespace App\Models;

use App\Enums\PlaidConnectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's authorized link to one financial institution (a Plaid Item).
 *
 * @property int $id
 * @property int $user_id
 * @property string $plaid_item_id
 * @property string $access_token
 * @property string|null $institution_id
 * @property string|null $institution_name
 * @property PlaidConnectionStatus $status
 * @property string|null $transactions_cursor
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'plaid_item_id', 'access_token', 'institution_id', 'institution_name', 'status', 'transactions_cursor', 'last_synced_at'])]
class PlaidConnection extends Model
{
    /**
     * The attributes hidden from serialization. The access token must never be
     * exposed to the frontend.
     *
     * @var list<string>
     */
    protected $hidden = ['access_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'status' => PlaidConnectionStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The user who owns this connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The accounts exposed by this connection.
     *
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
