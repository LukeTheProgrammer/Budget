<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\ImportTransactionsRequest;
use App\Jobs\ImportTransactionsFile;
use App\Services\Transactions\CsvTransactionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ImportController extends Controller
{
    /**
     * Trigger a CSV transaction import. A single file is dispatched to the
     * queue for responsiveness; an "all" request runs the batch synchronously
     * and returns a per-file summary.
     */
    public function store(ImportTransactionsRequest $request, CsvTransactionImporter $importer): RedirectResponse|JsonResponse
    {
        if ($request->boolean('all')) {
            $summary = collect($importer->importAll())->map(fn ($result): array => [
                'file' => $result->file,
                'imported' => $result->imported,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'needs_review' => $result->unconfirmedMerchants,
            ])->values();

            if ($request->wantsJson()) {
                return response()->json(['results' => $summary]);
            }

            $needsReview = $summary->sum('needs_review');
            $status = "Imported {$summary->count()} file(s).";

            if ($needsReview > 0) {
                $status .= " {$needsReview} new merchant(s) need review.";
            }

            return back()->with('status', $status);
        }

        $file = (string) $request->string('file');
        ImportTransactionsFile::dispatch($file);

        if ($request->wantsJson()) {
            return response()->json(['queued' => $file], 202);
        }

        return back()->with('status', "Queued import for {$file}.");
    }
}
