<?php

namespace App\Services\Transactions;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use SplFileObject;
use Throwable;

/**
 * Imports Chase-format transaction CSV files from the private storage disk into
 * the application's transactions table. This single service backs every entry
 * point (Artisan command, queued job, HTTP controller) so behavior is identical
 * regardless of how the import is triggered. Row persistence (merchant
 * resolution, dedup, tagging) is delegated to the shared
 * {@see TransactionRowStore} so it stays consistent with the mapped upload path.
 */
class CsvTransactionImporter
{
    /**
     * The exact header the fixed Chase activity layout must present.
     *
     * @var list<string>
     */
    private const EXPECTED_HEADER = [
        'Transaction Date',
        'Post Date',
        'Description',
        'Category',
        'Type',
        'Amount',
        'Memo',
    ];

    private ?Account $account = null;

    public function __construct(private TransactionRowStore $rowStore) {}

    /**
     * Override the configured default account for this import run.
     */
    public function forAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Importable CSV files discovered on the private disk.
     *
     * @return list<string>
     */
    public function availableFiles(): array
    {
        return $this->discoverFiles();
    }

    /**
     * Import a specific set of files, capturing per-file failures like
     * {@see importAll()} does.
     *
     * @param  list<string>  $relativePaths
     * @return array<string, ImportResult>
     *
     * @throws RowImportException when $stopOnFailure is true and a row fails.
     */
    public function importFiles(array $relativePaths, bool $stopOnFailure = false): array
    {
        $results = [];

        foreach ($relativePaths as $relativePath) {
            try {
                $results[$relativePath] = $this->importFile($relativePath, $stopOnFailure);
            } catch (ImportException $e) {
                $result = new ImportResult($relativePath);
                $result->recordFailure(0, $e->getMessage());
                $results[$relativePath] = $result;
            }
        }

        return $results;
    }

    /**
     * Import a single CSV file located relative to the `local` disk root
     * (e.g. "Chase7452_Activity20260601.CSV").
     *
     * @throws ImportException when the file is missing/unreadable, its header
     *                         does not match the fixed Chase layout, or the
     *                         default account is not configured.
     * @throws RowImportException when $stopOnFailure is true and a row fails.
     */
    public function importFile(string $relativePath, bool $stopOnFailure = false): ImportResult
    {
        $account = $this->account();
        $result = new ImportResult($relativePath);

        // Load the user's aliases and rules once for the whole file.
        $this->rowStore->forUser($account->user_id);

        $file = $this->openFile($relativePath);
        $this->assertValidHeader($file, $relativePath);

        // assertValidHeader() leaves the cursor on the first data row. A foreach
        // would rewind back to the header, so iterate manually instead.
        $lineNumber = 1; // header consumed above
        for (; $file->valid(); $file->next()) {
            $columns = $file->current();

            if ($columns === [null] || $columns === false || $columns === null) {
                continue; // trailing blank line
            }

            $lineNumber++;

            try {
                $row = $this->parseRow($columns, $lineNumber);
                $this->rowStore->store($account, $this->normalize($account, $row), $result, $row->categoryName);
            } catch (Throwable $e) {
                if ($stopOnFailure) {
                    throw new RowImportException($lineNumber, $columns, $e);
                }

                $result->recordFailure($lineNumber, $e->getMessage());
            }
        }

        // Release the file handle before moving the file on disk.
        unset($file);

        $this->rowStore->finish($account->user_id);

        $this->archiveFile($relativePath, $result);

        return $result;
    }

    /**
     * Convert a parsed Chase row into the storage-engine's normalized row,
     * applying the account currency.
     */
    private function normalize(Account $account, ChaseCsvRow $row): NormalizedTransactionRow
    {
        return new NormalizedTransactionRow(
            lineNumber: $row->lineNumber,
            postedAt: $row->postedAt,
            description: $row->description,
            merchantName: $row->merchantName,
            amountCents: $row->amountCents,
            currency: $account->currency,
        );
    }

    /**
     * Move a fully-read file into the processed/ archive so batch discovery does
     * not reprocess it. Failed/rejected files never reach this point.
     */
    private function archiveFile(string $relativePath, ImportResult $result): void
    {
        // $disk = Storage::disk('local');
        // $destination = 'processed/' . basename($relativePath);

        // if ($disk->exists($destination)) {
        //     $destination = 'processed/' . pathinfo($relativePath, PATHINFO_FILENAME)
        //         . '-' . now()->format('YmdHis') . '.' . pathinfo($relativePath, PATHINFO_EXTENSION);
        // }

        // if ($disk->move($relativePath, $destination)) {
        //     $result->archived = true;
        // }
    }

    /**
     * Discover and import every CSV file in the private disk root (excluding the
     * processed/ archive). A file that throws is captured as a failed result and
     * does not stop the batch.
     *
     * When $stopOnFailure is true, a RowImportException from any file
     * propagates and halts the batch.
     *
     * @return array<string, ImportResult>
     */
    public function importAll(bool $stopOnFailure = false): array
    {
        $this->account();

        return $this->importFiles($this->discoverFiles(), $stopOnFailure);
    }

    /**
     * Discover importable CSV files in the private disk root, excluding the
     * processed/ archive subfolder. Matches *.csv / *.CSV case-insensitively.
     *
     * @return list<string>
     */
    private function discoverFiles(): array
    {
        return collect(Storage::disk('local')->files())
            ->reject(fn (string $path): bool => str_starts_with($path, 'processed/'))
            ->filter(fn (string $path): bool => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv')
            ->values()
            ->all();
    }

    /**
     * Open a CSV file from the private disk for streaming, validating existence.
     *
     * @throws ImportException
     */
    private function openFile(string $relativePath): SplFileObject
    {
        $absolute = storage_path('app/private/' . $relativePath);

        if (! is_file($absolute) || ! is_readable($absolute)) {
            throw new ImportException("CSV file [{$relativePath}] does not exist or is not readable.");
        }

        $file = new SplFileObject($absolute, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        return $file;
    }

    /**
     * Read and validate the header row against the fixed Chase layout.
     *
     * @throws ImportException
     */
    private function assertValidHeader(SplFileObject $file, string $relativePath): void
    {
        $file->rewind();
        $header = $file->current();

        $normalized = is_array($header)
            ? array_map(static fn ($value): string => trim((string) $value), $header)
            : [];

        if ($normalized !== self::EXPECTED_HEADER) {
            throw new ImportException(
                "CSV file [{$relativePath}] header does not match the expected Chase layout: "
                . implode(',', self::EXPECTED_HEADER)
            );
        }

        $file->next();
    }

    /**
     * Parse and validate one CSV data row into a value object.
     *
     * @param  array<int, string|null>  $columns
     *
     * @throws \RuntimeException on any validation failure (recorded per-row).
     */
    private function parseRow(array $columns, int $lineNumber): ChaseCsvRow
    {
        if (count($columns) < 7) {
            throw new \RuntimeException('Row does not have the expected 7 columns.');
        }

        [, $postDate, $description, $category, $type, $amount, $memo] = array_map(
            static fn ($value): string => html_entity_decode(trim((string) $value), ENT_QUOTES | ENT_HTML5),
            $columns,
        );

        if ($description === '') {
            throw new \RuntimeException('Missing transaction description.');
        }

        $postedAt = $this->parseDate($postDate);
        $amountCents = $this->parseAmountCents($amount);

        $fullDescription = $memo !== '' ? "{$description} {$memo}" : $description;

        return new ChaseCsvRow(
            lineNumber: $lineNumber,
            postedAt: $postedAt,
            description: $fullDescription,
            merchantName: $description,
            amountCents: $amountCents,
            type: $type,
            categoryName: $category !== '' ? $category : null,
        );
    }

    /**
     * Parse a Chase MM/DD/YYYY date.
     */
    private function parseDate(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('m/d/Y', $value);

        if ($date === false) {
            throw new \RuntimeException("Unparseable post date [{$value}].");
        }

        return $date->startOfDay();
    }

    /**
     * Parse a Chase decimal amount into signed integer cents using the app
     * convention (positive = purchase). Chase signs purchases negative, so the
     * parsed value is inverted.
     */
    private function parseAmountCents(string $value): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        if ($clean === '' || ! is_numeric($clean)) {
            throw new \RuntimeException("Unparseable amount [{$value}].");
        }

        $cents = (int) round((float) $clean * -100);

        if ($cents === 0) {
            throw new \RuntimeException("Amount [{$value}] is zero.");
        }

        return $cents;
    }

    /**
     * Resolve the single configured default account, memoized for the lifetime
     * of the service. Throws before any row is processed when unconfigured or
     * missing (v1 supports one account).
     *
     * @throws ImportException
     */
    private function account(): Account
    {
        if ($this->account !== null) {
            return $this->account;
        }

        $accountId = config('transactions.default_account_id');

        if ($accountId === null || $accountId === '') {
            throw new ImportException(
                'No default import account configured. Set TRANSACTIONS_DEFAULT_ACCOUNT_ID in your environment.'
            );
        }

        /** @var ?Account $account */
        $account = Account::find($accountId);

        if ($account === null) {
            throw new ImportException("Configured default import account [{$accountId}] does not exist.");
        }

        return $this->account = $account;
    }
}
