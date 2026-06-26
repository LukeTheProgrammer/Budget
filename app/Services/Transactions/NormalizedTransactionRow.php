<?php

namespace App\Services\Transactions;

use Carbon\CarbonImmutable;

/**
 * A single parsed and validated transaction row, normalized to the
 * application's storage convention regardless of source layout.
 *
 * Amounts are stored as signed integer cents using the application's
 * convention (positive = purchase/spend, negative = refund/credit), already
 * adjusted from the source file's sign by the importer that produced this row.
 */
final readonly class NormalizedTransactionRow
{
    public function __construct(
        public int $lineNumber,
        public CarbonImmutable $postedAt,
        public string $description,
        public string $merchantName,
        public int $amountCents,
        public string $currency,
    ) {}
}
