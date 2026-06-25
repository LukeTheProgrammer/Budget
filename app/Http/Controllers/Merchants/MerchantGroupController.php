<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\GroupMerchantsRequest;
use App\Services\Merchants\MerchantGrouper;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MerchantGroupController extends Controller
{
    /**
     * Merge the selected merchants into the chosen primary merchant.
     */
    public function store(GroupMerchantsRequest $request, MerchantGrouper $grouper): RedirectResponse
    {
        /** @var list<int> $merchantIds */
        $merchantIds = $request->validated('merchant_ids');

        $merged = $grouper->group(
            $request->user(),
            (int) $request->validated('primary_merchant_id'),
            $merchantIds,
            $request->validated('name'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Grouped :count merchants into :name.', [
                'count' => count($merchantIds),
                'name' => $merged->name,
            ]),
        ]);

        return back();
    }
}
