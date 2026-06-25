<?php

namespace App\Services\Transactions;

/**
 * Summary of a single CSV file import: how many rows were imported, skipped as
 * duplicates, or failed, plus the identity and reason of each failure.
 */
final class ImportResult
{
    /**
     * @param  list<array{line: int, reason: string}>  $failures
     */
    public function __construct(
        public readonly string $file,
        public int $imported = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public int $unconfirmedMerchants = 0,
        public array $failures = [],
        public bool $archived = false,
    ) {}

    public function incrementImported(): void
    {
        $this->imported++;
    }

    /**
     * Record a merchant that was auto-created from an unrecognized descriptor
     * and now needs human review.
     */
    public function incrementUnconfirmedMerchants(): void
    {
        $this->unconfirmedMerchants++;
    }

    public function incrementSkipped(): void
    {
        $this->skipped++;
    }

    /**
     * Record a row that could not be imported.
     */
    public function recordFailure(int $line, string $reason): void
    {
        $this->failed++;
        $this->failures[] = ['line' => $line, 'reason' => $reason];
    }

    /**
     * Total data rows accounted for (imported + skipped + failed).
     */
    public function total(): int
    {
        return $this->imported + $this->skipped + $this->failed;
    }
}
