<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\MerchantRule;
use Illuminate\Database\Eloquent\Collection;

/**
 * Back-applies a freshly created matching rule to existing data: every
 * unconfirmed merchant whose raw descriptors the rule matches is absorbed into
 * the rule's target merchant (transactions repointed, descriptors retained as
 * aliases, emptied merchants removed). This is what makes a single correction
 * clean up the backlog the importer accumulated before the rule existed.
 */
class MerchantRuleApplier
{
    public function __construct(private MerchantGrouper $grouper) {}

    /**
     * Apply the rule and return the number of unconfirmed merchants absorbed.
     */
    public function apply(MerchantRule $rule): int
    {
        $target = $rule->merchant;

        /** @var Collection<int, Merchant> $candidates */
        $candidates = Merchant::query()
            ->where('user_id', $rule->user_id)
            ->whereNull('confirmed_at')
            ->where('id', '!=', $rule->merchant_id)
            ->with('aliases')
            ->get();

        $matchingIds = $candidates
            ->filter(fn (Merchant $merchant): bool => $merchant->aliases
                ->contains(fn ($alias): bool => $rule->matches($alias->name)))
            ->pluck('id')
            ->all();

        if ($matchingIds === []) {
            return 0;
        }

        $this->grouper->group($target->user, $target->id, $matchingIds);

        return count($matchingIds);
    }
}
