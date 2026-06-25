<?php

namespace App\Services\Plaid;

use InvalidArgumentException;

/**
 * Resolves the active Plaid environment, credentials, and base URL from the
 * application's `services.plaid` configuration.
 */
class PlaidConfig
{
    /**
     * @param  array{env?: string, client_id?: string|null, secrets?: array<string, string|null>, base_urls?: array<string, string>}  $config
     */
    public function __construct(private array $config) {}

    /**
     * Build the config from the application config repository.
     */
    public static function fromConfig(): self
    {
        return new self(config('services.plaid', []));
    }

    /**
     * The active environment name (e.g. "sandbox" or "production").
     */
    public function environment(): string
    {
        return $this->config['env'] ?? 'sandbox';
    }

    /**
     * The Plaid client id.
     */
    public function clientId(): string
    {
        return $this->require($this->config['client_id'] ?? null, 'client_id');
    }

    /**
     * The secret for the active environment.
     */
    public function secret(): string
    {
        return $this->require(
            $this->config['secrets'][$this->environment()] ?? null,
            "secret for environment [{$this->environment()}]",
        );
    }

    /**
     * The Plaid API base URL for the active environment.
     */
    public function baseUrl(): string
    {
        return $this->require(
            $this->config['base_urls'][$this->environment()] ?? null,
            "base URL for environment [{$this->environment()}]",
        );
    }

    private function require(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Missing Plaid {$name} configuration.");
        }

        return $value;
    }
}
