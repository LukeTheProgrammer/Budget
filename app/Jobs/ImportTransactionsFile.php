<?php

namespace App\Jobs;

use App\Services\Transactions\CsvTransactionImporter;
use App\Services\Transactions\ImportException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportTransactionsFile implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $relativePath) {}

    /**
     * Execute the job: import the file via the shared service and log the result.
     */
    public function handle(CsvTransactionImporter $importer): void
    {
        try {
            $result = $importer->importFile($this->relativePath);
        } catch (ImportException $e) {
            Log::error('Transaction import failed', [
                'file' => $this->relativePath,
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('Transaction import complete', [
            'file' => $result->file,
            'imported' => $result->imported,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
        ]);
    }
}
