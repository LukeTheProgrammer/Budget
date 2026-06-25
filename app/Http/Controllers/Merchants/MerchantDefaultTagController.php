<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\SyncMerchantDefaultTagsRequest;
use App\Models\Merchant;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MerchantDefaultTagController extends Controller
{
    /**
     * Add one or more default tags to a merchant, creating new tags on the fly
     * and reusing existing ones by slug. Existing transactions are not changed.
     */
    public function store(SyncMerchantDefaultTagsRequest $request, Merchant $merchant): RedirectResponse
    {
        /** @var list<string> $values */
        $values = $request->validated('tags');

        $merchant->defaultTags()->syncWithoutDetaching(Tag::resolveSlugs($values));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default tags updated.')]);

        return back();
    }

    /**
     * Remove a default tag from a merchant. The tag itself and any existing
     * transactions tagged with it are left unchanged.
     */
    public function destroy(Merchant $merchant, Tag $tag): RedirectResponse
    {
        abort_unless($merchant->user_id === auth()->id(), 403);

        $merchant->defaultTags()->detach($tag->slug);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default tag removed.')]);

        return back();
    }
}
