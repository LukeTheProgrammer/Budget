<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\MerchantRule;
use Illuminate\Support\Collection;

/**
 * Resolves a raw bank statement descriptor to one of a user's merchants using
 * the database as the single source of truth: exact aliases first, then the
 * user-editable prefix/regex rules.
 *
 * Prime once per user with {@see forUser()} so a single import run loads the
 * alias/rule sets one time and matches every row in memory.
 */
class NameResolver
{
    private ?int $userId = null;

    /**
     * Exact-match index keyed by normalized alias name => merchant id.
     *
     * @var array<string, int>
     */
    private array $aliasIndex = [];

    /**
     * The user's rules, ordered by priority (most specific first).
     *
     * @var Collection<int, MerchantRule>
     */
    private Collection $rules;

    /**
     * Merchants keyed by id for cheap hydration of matches.
     *
     * @var array<int, Merchant>
     */
    private array $merchants = [];

    public function __construct()
    {
        $this->rules = new Collection;
    }

    /**
     * Prime (or re-prime) the resolver with a user's aliases and rules. Returns
     * $this for fluent use at an import's start.
     */
    public function forUser(int $userId): self
    {
        if ($this->userId === $userId) {
            return $this;
        }

        $this->userId = $userId;

        $this->aliasIndex = MerchantAlias::query()
            ->where('user_id', $userId)
            ->pluck('merchant_id', 'normalized_name')
            ->all();

        $this->rules = MerchantRule::query()
            ->where('user_id', $userId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $this->merchants = Merchant::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('id')
            ->all();

        return $this;
    }

    /**
     * Resolve a raw descriptor to a merchant, or null when nothing matches.
     * Exact aliases win over rules; among rules, lower priority wins.
     */
    public function resolve(string $rawName): ?Merchant
    {
        $normalized = mb_strtolower(trim($rawName));

        if (isset($this->aliasIndex[$normalized])) {
            return $this->merchants[$this->aliasIndex[$normalized]] ?? null;
        }

        foreach ($this->rules as $rule) {
            if ($rule->matches($rawName)) {
                return $this->merchants[$rule->merchant_id] ?? null;
            }
        }

        return null;
    }
}
