<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\SyncTransactionTagsRequest;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransactionTagController extends Controller
{
    /**
     * Apply one or more tags to a transaction, creating any new tags on the fly
     * and reusing existing ones by slug. A tag is attached at most once.
     */
    public function store(SyncTransactionTagsRequest $request, Transaction $transaction): RedirectResponse
    {
        /** @var list<string> $values */
        $values = $request->validated('tags');

        $transaction->tags()->syncWithoutDetaching(Tag::resolveSlugs($values));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags updated.')]);

        return back();
    }

    /**
     * Remove a single tag from a transaction without deleting the tag itself.
     */
    public function destroy(Transaction $transaction, Tag $tag): RedirectResponse
    {
        abort_unless(
            $transaction->account()->where('user_id', auth()->id())->exists(),
            403,
        );

        $transaction->tags()->detach($tag->slug);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag removed.')]);

        return back();
    }
}
