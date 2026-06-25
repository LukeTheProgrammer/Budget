<?php

namespace App\Services\Merchants;

/**
 * Heuristically cleans raw bank descriptors for the review UI: it suggests a
 * human-friendly merchant name and a start-anchored prefix pattern by stripping
 * payment-processor tags and the varying store/order tokens that cause one
 * merchant to appear under many descriptors. Suggestions only — never
 * authoritative; the user confirms or edits them.
 */
class DescriptorNormalizer
{
    /**
     * Leading payment-processor / aggregator tags to strip (case-insensitive).
     * Ordered longest-first so multi-word tags win.
     *
     * @var list<string>
     */
    private const PREFIX_PATTERNS = [
        '/^FOOD\s+AT\s*\*\s*/i',
        '/^TSA\s+PRECHECK\s+BY\s+/i',
        '/^PAYPAL\s*\*\s*/i',
        '/^GOOGLE\s*\*\s*/i',
        '/^SQ\s*\*\s*/i',
        '/^TST\s*\*\s*/i',
        '/^DD\s*\*\s*/i',
        '/^PY\s*\*\s*/i',
        '/^WF\s*\*\s*/i',
        '/^WL\s*\*\s*/i',
        '/^TBL\s*\*\s*/i',
        '/^UEP\s*\*\s*/i',
        '/^IN\s*\*\s*/i',
        '/^MED\s*\*\s*/i',
        '/^DT\s*\*\s*/i',
        '/^TM\s*\*\s*/i',
        '/^SP\s+/i',
    ];

    /**
     * Trailing variable tokens to strip when deriving a clean name or a prefix
     * (store/order numbers, "- LOCATION" suffixes, "#1234").
     *
     * @var list<string>
     */
    private const TRAILING_PATTERNS = [
        '/\s+#\s*\d+.*$/',        // " #1234 ..."
        '/\s+-\s+.*$/',           // " - KC WEST"
        '/\s+\d{3,}.*$/',         // " 15276 ..." store/order numbers
        '/\s+[A-Z]{2}\s*$/',      // trailing state code
    ];

    /**
     * Strip a leading processor tag from a raw descriptor.
     */
    public function stripPrefix(string $raw): string
    {
        $value = trim($raw);

        foreach (self::PREFIX_PATTERNS as $pattern) {
            $stripped = preg_replace($pattern, '', $value);

            if (is_string($stripped) && $stripped !== $value) {
                return trim($stripped);
            }
        }

        return $value;
    }

    /**
     * A best-effort human-friendly name for a raw descriptor.
     */
    public function suggestedName(string $raw): string
    {
        $value = $this->stripPrefix($raw);

        foreach (self::TRAILING_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '', $value) ?? $value;
        }

        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        if ($value === '') {
            return trim($raw);
        }

        // Title-case unless the token already mixes case (likely already clean).
        if ($value === mb_strtoupper($value) || $value === mb_strtolower($value)) {
            $value = mb_convert_case($value, MB_CASE_TITLE);
        }

        return $value;
    }

    /**
     * A start-anchored prefix that should also catch sibling variants of the
     * same merchant. Keeps the processor tag (prefix matching is anchored) but
     * drops the trailing variable tokens.
     */
    public function suggestedPrefix(string $raw): string
    {
        $value = trim($raw);

        foreach (self::TRAILING_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '', $value) ?? $value;
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
