<?php

namespace App\Services\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use App\Models\Transaction;
use App\Services\Merchants\NameResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplFileObject;
use Throwable;

/**
 * Imports Chase-format transaction CSV files from the private storage disk into
 * the application's transactions table. This single service backs every entry
 * point (Artisan command, queued job, HTTP controller) so behavior is identical
 * regardless of how the import is triggered.
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

    public function __construct(private NameResolver $nameResolver) {}

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
        $this->nameResolver->forUser($account->user_id);

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
                $this->storeRow($account, $row, $result);
            } catch (Throwable $e) {
                if ($stopOnFailure) {
                    throw new RowImportException($lineNumber, $columns, $e);
                }

                $result->recordFailure($lineNumber, $e->getMessage());
            }
        }

        // Release the file handle before moving the file on disk.
        unset($file);

        $this->archiveFile($relativePath, $result);

        return $result;
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

        $results = [];

        foreach ($this->discoverFiles() as $relativePath) {
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
     * Persist one parsed row as a transaction, resolving/creating its merchant.
     */
    private function storeRow(Account $account, ChaseCsvRow $row, ImportResult $result): void
    {
        $merchant = $this->resolveMerchant($account->user_id, $row->merchantName, $row->categoryName, $result);

        $importHash = hash(
            'sha256',
            implode('|', [
                $account->id,
                $row->postedAt->format('Y-m-d'),
                $row->amountCents,
                $merchant->id,
            ]),
        );

        $transactionProps = [
            'account_id' => $account->id,
            'merchant_id' => $merchant->id,
            'amount_cents' => $row->amountCents,
            'currency' => $account->currency,
            'description' => $row->description,
            'posted_at' => $row->postedAt,
            'import_hash' => $importHash,
        ];

        $transaction = Transaction::updateOrCreate(
            ['import_hash' => $importHash],
            $transactionProps
        );

        if ($transaction->wasRecentlyCreated) {
            $this->applyDefaultTags($transaction, $merchant);
            $result->incrementImported();
        } else {
            $result->incrementSkipped();
        }
    }

    /**
     * Apply the merchant's default tags to a freshly imported transaction. Only
     * runs for newly created transactions (re-imported rows are left untouched),
     * and is a no-op when the merchant has no default tags.
     */
    private function applyDefaultTags(Transaction $transaction, Merchant $merchant): void
    {
        $slugs = $merchant->defaultTags()->pluck('tags.slug')->all();

        if ($slugs !== []) {
            $transaction->tags()->syncWithoutDetaching($slugs);
        }
    }

    /**
     * Resolve the merchant for a raw statement descriptor. The DB-backed
     * NameResolver (exact aliases + prefix/regex rules) is consulted first so
     * known store variants collapse onto a single confirmed merchant. When it
     * can't identify the name, fall back to an alias lookup that also catches
     * merchants created earlier in this same run, and only then auto-create an
     * unconfirmed merchant flagged for review.
     */
    private function resolveMerchant(int $userId, string $rawName, ?string $categoryName, ImportResult $result): Merchant
    {
        $merchant = $this->nameResolver->resolve($rawName);

        if ($merchant !== null) {
            return $this->backfillCategory($merchant, $categoryName);
        }

        // Resolver didn't know it. Catch repeats of unconfirmed merchants
        // created earlier this run (the resolver's cache predates them).
        $normalizedName = mb_strtolower(trim($rawName));

        $alias = MerchantAlias::query()
            ->where('user_id', $userId)
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($alias?->merchant !== null) {
            return $this->backfillCategory($alias->merchant, $categoryName);
        }

        $result->incrementUnconfirmedMerchants();

        return $this->createMerchantWithAlias($userId, $rawName, $rawName, $categoryName);
    }

    /**
     * Assign a category to a merchant that has none yet, resolving/creating the
     * category from the raw CSV name. Merchants that already have a category are
     * left untouched so manual reassignments are not overwritten.
     */
    private function backfillCategory(Merchant $merchant, ?string $categoryName): Merchant
    {
        if ($merchant->category_id !== null || $categoryName === null) {
            return $merchant;
        }

        $category = $this->resolveCategory($merchant->user_id, $categoryName);

        if ($category !== null) {
            $merchant->update(['category_id' => $category->id]);
        }

        return $merchant;
    }

    /**
     * Find or create the user's category for the given raw CSV category name,
     * matching case-insensitively on name. Returns null when no category was
     * supplied on the row.
     */
    private function resolveCategory(int $userId, ?string $categoryName): ?Category
    {
        if ($categoryName === null) {
            return null;
        }

        $category = Category::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
            ->first();

        return $category
            ?? Category::create(['user_id' => $userId, 'name' => $categoryName]);
    }

    /**
     * Create an unconfirmed merchant together with a self-alias, so the merchant
     * is always resolvable via the alias table while it awaits review. Wrapped
     * in a transaction so a merchant is never persisted without its alias.
     */
    private function createMerchantWithAlias(int $userId, string $merchantName, string $aliasName, ?string $categoryName): Merchant
    {
        return DB::transaction(function () use ($userId, $merchantName, $aliasName, $categoryName): Merchant {
            $category = $this->resolveCategory($userId, $categoryName);

            $merchant = Merchant::create([
                'user_id' => $userId,
                'category_id' => $category?->id,
                'name' => $merchantName,
            ]);

            $merchant->aliases()->create(['user_id' => $userId, 'name' => $aliasName]);

            return $merchant;
        });
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
