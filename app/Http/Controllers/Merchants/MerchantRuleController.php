<?php

namespace App\Http\Controllers\Merchants;

use App\Enums\MerchantRuleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\StoreMerchantRuleRequest;
use App\Models\Merchant;
use App\Models\MerchantRule;
use App\Services\Merchants\MerchantRuleApplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MerchantRuleController extends Controller
{
    /**
     * Attach a matching rule to the merchant, confirm it, and back-apply the
     * rule so existing unconfirmed merchants that match are absorbed.
     */
    public function store(StoreMerchantRuleRequest $request, Merchant $merchant, MerchantRuleApplier $applier): RedirectResponse
    {
        $rule = $merchant->rules()->create([
            'user_id' => $merchant->user_id,
            'match_type' => MerchantRuleType::from($request->validated('match_type')),
            'pattern' => $request->validated('pattern'),
        ]);

        if ($merchant->confirmed_at === null) {
            $merchant->update(['confirmed_at' => now()]);
        }

        $absorbed = $applier->apply($rule);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $absorbed > 0
                ? __('Rule added and :count merchants merged in.', ['count' => $absorbed])
                : __('Rule added.'),
        ]);

        return back();
    }

    /**
     * Remove a matching rule from the merchant.
     */
    public function destroy(Merchant $merchant, MerchantRule $rule): RedirectResponse
    {
        abort_unless(
            $merchant->user_id === auth()->id() && $rule->merchant_id === $merchant->id,
            403,
        );

        $rule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule removed.')]);

        return back();
    }
}
