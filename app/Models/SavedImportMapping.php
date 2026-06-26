<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A remembered column mapping for uploading transaction files, scoped to a
 * single user and account. Pre-fills the mapping UI on subsequent uploads to
 * the same account and is upserted on each successful import.
 *
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property array<string, mixed> $mapping
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'account_id', 'mapping'])]
class SavedImportMapping extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
        ];
    }

    /**
     * The user who owns this saved mapping.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The account this mapping pre-fills uploads for.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
