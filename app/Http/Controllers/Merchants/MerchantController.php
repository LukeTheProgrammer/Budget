<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\UpdateMerchantRequest;
use App\Models\Merchant;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\Merchants\DescriptorNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MerchantController extends Controller
{
    /**
     * Show the merchant management page listing the user's merchants with their
     * confirmation state, transaction counts, aliases, and rules. Unconfirmed
     * merchants carry a suggested clean name and prefix for quick review.
     */
    public function index(Request $request, DescriptorNormalizer $normalizer): Response
    {
        $merchants = Merchant::query()
            ->where('user_id', $request->user()->id)
            ->addSelect([
                '*',
                'total_amount_cents' => Transaction::selectRaw('COALESCE(SUM(amount_cents), 0)')->whereColumn('merchant_id', 'merchants.id'),
            ])
            ->withCount('transactions')
            ->with(['aliases:id,merchant_id,name', 'rules:id,merchant_id,match_type,pattern', 'defaultTags'])
            ->orderBy('name')
            ->get()
            ->map(fn (Merchant $merchant): array => $this->serializeMerchant($merchant, $normalizer))
            ->sortBy([
                ['confirmed', 'asc'],
                ['transactions_sum', 'desc'],
            ])
            ->values();

        return Inertia::render('merchants', [
            'merchants' => $merchants,
            'available_tags' => $this->availableTags(),
        ]);
    }

    /**
     * Return a single merchant serialized in the same shape as the index, plus
     * the available tag list, as JSON. Powers the shared edit-merchant dialog
     * when it is opened from a page that does not already carry merchant props
     * (e.g. the budgets dashboard).
     */
    public function show(Request $request, Merchant $merchant, DescriptorNormalizer $normalizer): JsonResponse
    {
        abort_unless($merchant->user_id === $request->user()->id, 403);

        $merchant->loadCount('transactions')
            ->load(['aliases:id,merchant_id,name', 'rules:id,merchant_id,match_type,pattern', 'defaultTags']);
        $merchant->total_amount_cents = (int) $merchant->transactions()->sum('amount_cents');

        return response()->json([
            'merchant' => $this->serializeMerchant($merchant, $normalizer),
            'available_tags' => $this->availableTags(),
        ]);
    }

    /**
     * Rename a merchant. Renaming also confirms it, since an edit is an explicit
     * review of an auto-created merchant.
     */
    public function update(UpdateMerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $merchant->update([
            'name' => $request->validated('name'),
            'confirmed_at' => $merchant->confirmed_at ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Merchant updated.')]);

        return back();
    }

    /**
     * Serialize a merchant (with its eager-loaded aliases, rules, default tags,
     * `transactions_count`, and `total_amount_cents`) into the array shape the
     * frontend merchant types expect.
     *
     * @return array<string, mixed>
     */
    private function serializeMerchant(Merchant $merchant, DescriptorNormalizer $normalizer): array
    {
        return [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'confirmed' => $merchant->confirmed_at !== null,
            'suggested_name' => $merchant->confirmed_at === null
                ? $normalizer->suggestedName($merchant->name)
                : null,
            'suggested_prefix' => $merchant->confirmed_at === null
                ? $normalizer->suggestedPrefix($merchant->name)
                : null,
            'category_id' => $merchant->category_id,
            'transactions_count' => $merchant->transactions_count,
            'transactions_sum' => $merchant->total_amount_cents,
            'aliases' => $merchant->aliases
                ->map(fn ($alias): array => ['id' => $alias->id, 'name' => $alias->name])
                ->values(),
            'rules' => $merchant->rules
                ->map(fn ($rule): array => [
                    'id' => $rule->id,
                    'match_type' => $rule->match_type->value,
                    'pattern' => $rule->pattern,
                ])
                ->values(),
            'default_tags' => $merchant->defaultTags
                ->map(fn (Tag $tag): array => ['slug' => $tag->slug, 'name' => $tag->name])
                ->values(),
        ];
    }

    /**
     * The full tag list offered as default-tag options in the edit dialog.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    private function availableTags(): array
    {
        return Tag::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => ['slug' => $tag->slug, 'name' => $tag->name])
            ->all();
    }
}
