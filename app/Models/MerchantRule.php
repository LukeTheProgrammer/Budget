<?php

namespace App\Models;

use App\Enums\MerchantRuleType;
use Database\Factories\MerchantRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user-editable matching rule that resolves a family of raw statement
 * descriptors to a single merchant (e.g. every "TST* BLUE SUSHI ..." variant).
 * Rules are the pattern-level counterpart to the exact MerchantAlias table.
 *
 * @property int $id
 * @property int $user_id
 * @property int $merchant_id
 * @property MerchantRuleType $match_type
 * @property string $pattern
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'merchant_id', 'match_type', 'pattern', 'priority'])]
class MerchantRule extends Model
{
    /** @use HasFactory<MerchantRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_type' => MerchantRuleType::class,
        ];
    }

    /**
     * The user who owns the rule.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The merchant this rule resolves to.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Whether the given raw descriptor matches this rule.
     */
    public function matches(string $rawName): bool
    {
        return $this->match_type->matches($this->pattern, $rawName);
    }
}
