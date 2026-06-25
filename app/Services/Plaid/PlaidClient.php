<?php

namespace App\Services\Plaid;

use App\Models\PlaidConnection;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * A thin in-house client for the handful of Plaid endpoints this app uses.
 *
 * Wraps Laravel's HTTP client rather than depending on a third-party SDK. The
 * client id and environment secret are injected into every request body as Plaid
 * requires.
 */
class PlaidClient
{
    public function __construct(private PlaidConfig $config) {}

    /**
     * Create a link_token used to initialize Plaid Link on the client.
     *
     * @param  PlaidConnection|null  $connection  When provided, produces an
     *                                            update-mode token for re-authenticating an existing Item.
     */
    public function createLinkToken(User $user, ?PlaidConnection $connection = null): string
    {
        $payload = [
            'client_name' => config('app.name'),
            'user' => ['client_user_id' => (string) $user->id],
            'language' => 'en',
            'country_codes' => ['US'],
        ];

        if ($connection !== null) {
            $payload['access_token'] = $connection->access_token;
        } else {
            $payload['products'] = ['transactions'];
        }

        return $this->post('/link/token/create', $payload)['link_token'];
    }

    /**
     * Exchange a public_token from a completed Link flow for an access token.
     *
     * @return array{access_token: string, item_id: string}
     */
    public function exchangePublicToken(string $publicToken): array
    {
        $data = $this->post('/item/public_token/exchange', [
            'public_token' => $publicToken,
        ]);

        return [
            'access_token' => $data['access_token'],
            'item_id' => $data['item_id'],
        ];
    }

    /**
     * Fetch accounts and current balances for an Item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccounts(string $accessToken): array
    {
        return $this->post('/accounts/get', [
            'access_token' => $accessToken,
        ])['accounts'];
    }

    /**
     * Fetch incremental transaction changes for an Item since the given cursor.
     *
     * @return array{added: array<int, array<string, mixed>>, modified: array<int, array<string, mixed>>, removed: array<int, array<string, mixed>>, next_cursor: string, has_more: bool}
     */
    public function syncTransactions(string $accessToken, ?string $cursor = null): array
    {
        $data = $this->post('/transactions/sync', array_filter([
            'access_token' => $accessToken,
            'cursor' => $cursor,
        ], fn ($value) => $value !== null));

        return [
            'added' => $data['added'] ?? [],
            'modified' => $data['modified'] ?? [],
            'removed' => $data['removed'] ?? [],
            'next_cursor' => $data['next_cursor'] ?? '',
            'has_more' => $data['has_more'] ?? false,
        ];
    }

    /**
     * Revoke access to an Item, disconnecting it from this app.
     */
    public function removeItem(string $accessToken): void
    {
        $this->post('/item/remove', ['access_token' => $accessToken]);
    }

    /**
     * Issue a POST to a Plaid endpoint with credentials injected, returning the
     * decoded JSON body. Throws on a non-2xx response.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($path, [
            'client_id' => $this->config->clientId(),
            'secret' => $this->config->secret(),
            ...$payload,
        ]);

        if ($response->failed()) {
            throw new PlaidApiException(
                $response->json('error_code'),
                $response->json('error_message') ?? "Plaid request to {$path} failed.",
            );
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->config->baseUrl())
            ->acceptJson()
            ->asJson()
            // Retry transient connection failures; Plaid error responses are
            // handled explicitly in post() and are not retried here.
            ->retry(2, 200, throw: false);
    }
}
