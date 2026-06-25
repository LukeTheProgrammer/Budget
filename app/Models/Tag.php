<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $slug
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name'])]
class Tag extends Model
{
    /**
     * The slug is the primary key; it is a non-incrementing string derived from
     * the tag's display name.
     */
    protected $primaryKey = 'slug';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Resolve route-model bindings by slug (the primary key).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Find or create each tag from a list of raw display values, keyed by the
     * slug derived from the value, and return their slugs. Values that slugify
     * to the same key collapse onto a single tag (FR-003, FR-011).
     *
     * @param  iterable<string>  $values
     * @return list<string>
     */
    public static function resolveSlugs(iterable $values): array
    {
        $slugs = [];

        foreach ($values as $value) {
            $slug = Str::slug($value);

            if ($slug === '' || isset($slugs[$slug])) {
                continue;
            }

            static::firstOrCreate(['slug' => $slug], ['name' => $value]);
            $slugs[$slug] = true;
        }

        return array_keys($slugs);
    }

    /**
     * The transactions this tag is applied to.
     *
     * @return BelongsToMany<Transaction, $this>
     */
    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'tag_transaction', 'tag_slug', 'transaction_id');
    }
}
