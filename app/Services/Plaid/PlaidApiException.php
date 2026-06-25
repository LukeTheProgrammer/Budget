<?php

namespace App\Services\Plaid;

use RuntimeException;

/**
 * Raised when the Plaid API returns an error response, carrying Plaid's
 * machine-readable error code (e.g. "ITEM_LOGIN_REQUIRED").
 */
class PlaidApiException extends RuntimeException
{
    public function __construct(
        public readonly ?string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : (string) $errorCode);
    }

    /**
     * Whether this error means the user must re-authenticate the Item.
     */
    public function requiresReauth(): bool
    {
        return $this->errorCode === 'ITEM_LOGIN_REQUIRED';
    }
}
