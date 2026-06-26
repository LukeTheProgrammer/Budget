<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\UploadTransactionsRequest;
use App\Models\Account;
use App\Models\SavedImportMapping;
use App\Services\Transactions\ImportResult;
use App\Services\Transactions\MappedCsvImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class UploadController extends Controller
{
    /**
     * Show the upload screen with the user's accounts and any saved mappings
     * to pre-fill the column mapper.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('transactions-upload', [
            'accounts' => $this->accounts($request),
            'savedMappings' => $this->savedMappings($request),
        ]);
    }

    /**
     * Run a synchronous mapped import of the uploaded file, remember the mapping
     * for the account, and re-render the screen with the result summary.
     */
    public function store(UploadTransactionsRequest $request, MappedCsvImporter $importer): Response
    {
        $validated = $request->validated();

        /** @var Account $account */
        $account = $request->user()->accounts()->findOrFail($validated['account_id']);

        $result = $importer->importUpload($request->file('file'), $account, $validated['mapping']);

        SavedImportMapping::updateOrCreate(
            ['user_id' => $request->user()->id, 'account_id' => $account->id],
            ['mapping' => $validated['mapping']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->summaryMessage($result)]);

        return Inertia::render('transactions-upload', [
            'accounts' => $this->accounts($request),
            'savedMappings' => $this->savedMappings($request),
            'importResult' => [
                'imported' => $result->imported,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'needsReview' => $result->unconfirmedMerchants,
                'failures' => $result->failures,
            ],
        ]);
    }

    /**
     * The authenticated user's accounts in a shape the upload screen needs.
     *
     * @return Collection<int, array{id: int, name: string, currency: string}>
     */
    private function accounts(Request $request): Collection
    {
        return $request->user()->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
            ])
            ->values();
    }

    /**
     * The user's saved mappings keyed by account id, for client pre-fill.
     *
     * @return array<int, array<string, mixed>>
     */
    private function savedMappings(Request $request): array
    {
        return SavedImportMapping::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('account_id')
            ->map(fn (SavedImportMapping $mapping): array => $mapping->mapping)
            ->all();
    }

    /**
     * Human-readable one-line summary of an import result.
     */
    private function summaryMessage(ImportResult $result): string
    {
        $message = "Imported {$result->imported}, skipped {$result->skipped}, failed {$result->failed}.";

        if ($result->unconfirmedMerchants > 0) {
            $message .= " {$result->unconfirmedMerchants} new merchant(s) need review.";
        }

        return $message;
    }
}
