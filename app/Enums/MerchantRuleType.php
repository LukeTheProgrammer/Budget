<?php

namespace App\Enums;

/**
 * The matching strategy a MerchantRule uses against a raw statement descriptor.
 */
enum MerchantRuleType: string
{
    case Prefix = 'prefix';
    case Regex = 'regex';

    /**
     * Test a raw descriptor against the given pattern for this strategy.
     * Prefix matching is case-insensitive; regex patterns are full PCRE.
     */
    public function matches(string $pattern, string $rawName): bool
    {
        return match ($this) {
            self::Prefix => str_starts_with(mb_strtolower($rawName), mb_strtolower($pattern)),
            self::Regex => @preg_match($pattern, $rawName) === 1,
        };
    }
}
