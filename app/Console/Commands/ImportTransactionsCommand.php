<?php

namespace App\Console\Commands;

use App\Services\Transactions\CsvTransactionImporter;
use App\Services\Transactions\ImportException;
use App\Services\Transactions\ImportResult;
use App\Services\Transactions\RowImportException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transactions:import 
    { file? : Relative path under storage/app/private }
    { --all : Import every CSV in the private storage folder }
    { --stop-on-failure : Halt and dump the offending row when any row fails to import }
')]
#[Description('Import transactions from a Chase-format CSV file in the private storage folder.')]
class ImportTransactionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CsvTransactionImporter $importer): int
    {
        $file = $this->argument('file');
        $all = (bool) $this->option('all');
        $stopOnFailure = (bool) $this->option('stop-on-failure');

        if (! $all && $file === null) {
            $this->error('Provide a file argument or use --all.');

            return self::INVALID;
        }

        try {
            $results = $all
                ? $importer->importAll($stopOnFailure)
                : [$file => $importer->importFile($file, $stopOnFailure)];
        } catch (RowImportException $e) {
            $this->error("Row {$e->lineNumber} failed: {$e->getMessage()}");
            $this->line('Row data:');
            dump($e->columns);

            return self::FAILURE;
        } catch (ImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($results as $result) {
            $this->renderResult($result);
        }

        return self::SUCCESS;
    }

    /**
     * Print a one-file summary plus any failed-row details.
     */
    private function renderResult(ImportResult $result): void
    {
        $this->line("<info>{$result->file}</info>: imported={$result->imported} skipped={$result->skipped} failed={$result->failed} needs-review={$result->unconfirmedMerchants}");

        foreach ($result->failures as $failure) {
            $this->warn("  line {$failure['line']}: {$failure['reason']}");
        }
    }
}
