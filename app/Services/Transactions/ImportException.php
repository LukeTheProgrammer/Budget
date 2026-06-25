<?php

namespace App\Services\Transactions;

use RuntimeException;

/**
 * Thrown for file-level import failures (missing/unreadable file, a header that
 * does not match the fixed Chase layout, or an unconfigured default account).
 * Per-row problems are recorded as failures on the ImportResult instead.
 */
class ImportException extends RuntimeException {}
