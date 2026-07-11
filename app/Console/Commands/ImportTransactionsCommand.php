<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\Transactions\CsvTransactionImporter;
use App\Services\Transactions\ImportException;
use App\Services\Transactions\ImportResult;
use App\Services\Transactions\RowImportException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[Signature('transactions:import
    { file? : Relative path under storage/app/private }
    { --account= : Account ID to import into (defaults to the configured account, or prompts) }
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

        if (! $all && $file === null && ! $this->input->isInteractive()) {
            $this->error('Provide a file argument or use --all.');

            return self::INVALID;
        }

        try {
            $account = $this->resolveAccount();

            if ($account !== null) {
                $importer->forAccount($account);
            }

            $files = match (true) {
                $all => $importer->availableFiles(),
                $file !== null => [$file],
                default => $this->promptForFiles($importer->availableFiles()),
            };

            if ($files === []) {
                $this->warn('No CSV files to import.');

                return self::SUCCESS;
            }

            $results = $importer->importFiles($files, $stopOnFailure);
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
     * Resolve the account to import into: the --account option, an interactive
     * selection, or null to fall back to the importer's configured default.
     *
     * @throws ImportException when the requested account does not exist or none
     *                         can be chosen.
     */
    private function resolveAccount(): ?Account
    {
        $accountId = $this->option('account');

        if ($accountId !== null) {
            /** @var ?Account $account */
            $account = Account::find($accountId);

            if ($account === null) {
                throw new ImportException("Account [{$accountId}] does not exist.");
            }

            return $account;
        }

        if (! $this->input->isInteractive()) {
            return null; // configured default account
        }

        /** @var array<int, string> $options */
        $options = Account::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [
                $account->id => trim($account->name . ' ' . ($account->last_four !== null ? "(••••{$account->last_four})" : '')),
            ])
            ->all();

        if ($options === []) {
            throw new ImportException('No accounts exist to import into.');
        }

        $default = config('transactions.default_account_id');

        return Account::find(select(
            label: 'Which account are these transactions for?',
            options: $options,
            default: isset($options[$default]) ? $default : null,
            scroll: 10,
        ));
    }

    /**
     * Let the user pick which of the discovered CSV files to import.
     *
     * @param  list<string>  $available
     * @return list<string>
     */
    private function promptForFiles(array $available): array
    {
        if ($available === []) {
            return [];
        }

        return multiselect(
            label: 'Which files do you want to import?',
            options: array_combine($available, $available),
            default: $available,
            scroll: 15,
            required: true,
            hint: 'Space to toggle, enter to confirm.',
        );
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
