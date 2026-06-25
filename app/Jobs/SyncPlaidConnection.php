<?php

namespace App\Jobs;

use App\Enums\PlaidConnectionStatus;
use App\Models\PlaidConnection;
use App\Services\Plaid\PlaidAccountSync;
use App\Services\Plaid\PlaidApiException;
use App\Services\Plaid\PlaidTransactionSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPlaidConnection implements ShouldQueue
{
    use Queueable;

    /**
     * The number of attempts for transient failures.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public PlaidConnection $plaidConnection) {}

    /**
     * Sync accounts/balances then transactions for the connection, recording the
     * outcome on the connection's status and last-synced timestamp.
     */
    public function handle(PlaidAccountSync $accountSync, PlaidTransactionSync $transactionSync): void
    {
        try {
            $accountSync->sync($this->plaidConnection);
            $transactionSync->sync($this->plaidConnection);
        } catch (PlaidApiException $e) {
            // Re-auth won't fix itself on retry — flag it and stop.
            if ($e->requiresReauth()) {
                $this->plaidConnection->update(['status' => PlaidConnectionStatus::ReauthRequired]);

                Log::warning('Plaid connection needs re-authentication', [
                    'connection_id' => $this->plaidConnection->id,
                ]);

                return;
            }

            $this->handleTransientFailure($e);
        } catch (Throwable $e) {
            $this->handleTransientFailure($e);
        }

        $this->plaidConnection->update([
            'status' => PlaidConnectionStatus::Active,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Record and rethrow a (potentially transient) failure, only marking the
     * connection errored once retries are exhausted so a successful retry can
     * still flip it back to active.
     */
    private function handleTransientFailure(Throwable $e): never
    {
        if ($this->attempts() >= $this->tries) {
            $this->plaidConnection->update(['status' => PlaidConnectionStatus::Error]);
        }

        Log::error('Plaid connection sync failed', [
            'connection_id' => $this->plaidConnection->id,
            'attempt' => $this->attempts(),
            'reason' => $e->getMessage(),
        ]);

        throw $e;
    }
}
