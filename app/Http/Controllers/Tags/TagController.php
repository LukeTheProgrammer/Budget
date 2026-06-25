<?php

namespace App\Http\Controllers\Tags;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TagController extends Controller
{
    /**
     * Delete a tag globally. The cascade on the pivot tables removes it from
     * every transaction and merchant default it was applied to (FR-015).
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }
}
