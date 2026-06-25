<?php

namespace App\Services\Transactions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a row fails to import and the caller has requested that the
 * import stop on the first failure. Carries the raw CSV columns and line
 * number so the failing row can be dumped alongside the error.
 */
class RowImportException extends RuntimeException
{
    /**
     * @param  array<int, string|null>  $columns
     */
    public function __construct(
        public readonly int $lineNumber,
        public readonly array $columns,
        Throwable $previous,
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
