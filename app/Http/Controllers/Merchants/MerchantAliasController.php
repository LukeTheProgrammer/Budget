<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\StoreMerchantAliasRequest;
use App\Models\Merchant;
use App\Models\MerchantAlias;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MerchantAliasController extends Controller
{
    /**
     * Add an alias to the given merchant.
     */
    public function store(StoreMerchantAliasRequest $request, Merchant $merchant): RedirectResponse
    {
        $name = (string) $request->validated('name');

        // Uniqueness (including the merchant's own self-alias) is enforced by
        // StoreMerchantAliasRequest, so we can create unconditionally here.
        $merchant->aliases()->create([
            'user_id' => $merchant->user_id,
            'name' => $name,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alias added.')]);

        return back();
    }

    /**
     * Remove an alias from the given merchant.
     */
    public function destroy(Merchant $merchant, MerchantAlias $alias): RedirectResponse
    {
        abort_unless(
            $merchant->user_id === auth()->id() && $alias->merchant_id === $merchant->id,
            403,
        );

        $alias->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alias removed.')]);

        return back();
    }
}
