<?php

namespace App\Services\Transactions;

use Carbon\CarbonImmutable;

/**
 * A single parsed and validated data row from a Chase activity CSV export.
 *
 * Amounts are stored as signed integer cents using the application's
 * convention (positive = purchase, negative = refund/credit), already
 * inverted from Chase's raw sign by the importer.
 */
final readonly class ChaseCsvRow
{
    public function __construct(
        public int $lineNumber,
        public CarbonImmutable $postedAt,
        public string $description,
        public string $merchantName,
        public int $amountCents,
        public string $type,
        public ?string $categoryName,
    ) {}
}
