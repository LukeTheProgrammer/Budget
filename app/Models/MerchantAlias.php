<?php

namespace App\Models;

use Database\Factories\MerchantAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $merchant_id
 * @property string $name
 * @property string $normalized_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'merchant_id', 'name', 'normalized_name'])]
class MerchantAlias extends Model
{
    /** @use HasFactory<MerchantAliasFactory> */
    use HasFactory;

    /**
     * Keep the normalized matching key in sync whenever the alias name changes.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'name' => $value,
                'normalized_name' => mb_strtolower(trim($value)),
            ],
        );
    }

    /**
     * The user who owns the alias.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The merchant this alias resolves to.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
