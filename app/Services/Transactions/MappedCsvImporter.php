<?php

namespace App\Services\Transactions;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use SplFileObject;
use Throwable;

/**
 * Imports an uploaded delimited (CSV) transaction file using a user-supplied
 * column mapping, into a user-selected account. Parsing is layout-agnostic:
 * the header row is matched against the mapping to locate each field's column,
 * and rows are normalized to the app convention before being handed to the
 * shared {@see TransactionRowStore}. This shares dedup and merchant behavior
 * with the fixed-layout {@see CsvTransactionImporter}.
 */
class MappedCsvImporter
{
    /**
     * Date formats attempted, in order, before falling back to Carbon's parser.
     *
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'm/d/y'];

    public function __construct(private TransactionRowStore $rowStore) {}

    /**
     * Import every data row of the uploaded file under the given mapping.
     *
     * @param  array{fields: array<string, string|null>, amount_sign?: string, date_format?: string|null}  $mapping
     */
    public function importUpload(UploadedFile $file, Account $account, array $mapping): ImportResult
    {
        $result = new ImportResult($file->getClientOriginalName());
        $this->rowStore->forUser($account->user_id);

        $csv = new SplFileObject($file->getRealPath(), 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $columnIndex = $this->resolveColumnIndex($csv, $mapping['fields']);
        $amountSign = ($mapping['amount_sign'] ?? 'as_is') === 'invert' ? -1 : 1;
        $dateFormat = $mapping['date_format'] ?? null;

        // resolveColumnIndex() leaves the cursor on the first data row.
        $lineNumber = 1; // header consumed above
        for (; $csv->valid(); $csv->next()) {
            $columns = $csv->current();

            if ($columns === [null] || $columns === false || $columns === null) {
                continue; // trailing blank line
            }

            $lineNumber++;

            try {
                $row = $this->parseRow($columns, $lineNumber, $columnIndex, $amountSign, $dateFormat, $account);
                $this->rowStore->store($account, $row, $result);
            } catch (Throwable $e) {
                $result->recordFailure($lineNumber, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Read the header row and resolve each mapped field to its column index.
     * Unmapped (null) fields are omitted.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, int>
     *
     * @throws ImportException when a mapped header is not present in the file.
     */
    private function resolveColumnIndex(SplFileObject $csv, array $fields): array
    {
        $csv->rewind();
        $header = $csv->current();

        $headers = is_array($header)
            ? array_map(static fn ($value): string => trim((string) $value), $header)
            : [];

        $index = [];

        foreach ($fields as $field => $headerName) {
            if ($headerName === null || $headerName === '') {
                continue;
            }

            $position = array_search($headerName, $headers, true);

            if ($position === false) {
                throw new ImportException("Mapped column [{$headerName}] is not present in the uploaded file.");
            }

            $index[$field] = $position;
        }

        $csv->next();

        return $index;
    }

    /**
     * Parse one data row into a normalized transaction row using the resolved
     * column positions.
     *
     * @param  array<int, string|null>  $columns
     * @param  array<string, int>  $columnIndex
     *
     * @throws \RuntimeException on any per-row validation failure.
     */
    private function parseRow(array $columns, int $lineNumber, array $columnIndex, int $amountSign, ?string $dateFormat, Account $account): NormalizedTransactionRow
    {
        $description = $this->value($columns, $columnIndex['description'] ?? null);

        if ($description === '') {
            throw new \RuntimeException('Missing transaction description.');
        }

        $postedAt = $this->parseDate($this->value($columns, $columnIndex['posted_at'] ?? null), $dateFormat);
        $amountCents = $this->parseAmountCents($this->value($columns, $columnIndex['amount'] ?? null), $amountSign);

        $currency = $this->value($columns, $columnIndex['currency'] ?? null);
        $currency = $currency !== '' ? strtoupper($currency) : $account->currency;

        return new NormalizedTransactionRow(
            lineNumber: $lineNumber,
            postedAt: $postedAt,
            description: $description,
            merchantName: $description,
            amountCents: $amountCents,
            currency: $currency,
        );
    }

    /**
     * Read and clean a single cell by column index, returning '' when missing.
     *
     * @param  array<int, string|null>  $columns
     */
    private function value(array $columns, ?int $position): string
    {
        if ($position === null || ! array_key_exists($position, $columns)) {
            return '';
        }

        return html_entity_decode(trim((string) $columns[$position]), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Parse a date value, honoring an explicit format when supplied, otherwise
     * trying common layouts before falling back to Carbon's flexible parser.
     */
    private function parseDate(string $value, ?string $dateFormat): CarbonImmutable
    {
        if ($value === '') {
            throw new \RuntimeException('Missing transaction date.');
        }

        $formats = $dateFormat !== null && $dateFormat !== '' ? [$dateFormat] : self::DATE_FORMATS;

        foreach ($formats as $format) {
            // Carbon throws (rather than returning false) when the value does
            // not match the format, so each attempt must be guarded.
            try {
                return CarbonImmutable::createFromFormat($format, $value)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            throw new \RuntimeException("Unparseable date [{$value}].");
        }
    }

    /**
     * Parse a decimal amount into signed integer cents using the app convention
     * (positive = purchase/spend), applying the mapping's sign multiplier.
     */
    private function parseAmountCents(string $value, int $amountSign): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        if ($clean === '' || ! is_numeric($clean)) {
            throw new \RuntimeException("Unparseable amount [{$value}].");
        }

        $cents = (int) round((float) $clean * 100 * $amountSign);

        if ($cents === 0) {
            throw new \RuntimeException("Amount [{$value}] is zero.");
        }

        return $cents;
    }
}
