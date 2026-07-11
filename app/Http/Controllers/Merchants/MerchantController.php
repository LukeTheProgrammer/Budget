<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\MerchantFilterRequest;
use App\Http\Requests\Merchants\UpdateMerchantRequest;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\Merchants\DescriptorNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MerchantController extends Controller
{
    /**
     * The number of merchants shown per page.
     */
    private const PER_PAGE = 50;

    /**
     * Show the merchant management page listing the user's merchants with their
     * confirmation state, transaction counts, aliases, and rules. Unconfirmed
     * merchants carry a suggested clean name and prefix for quick review.
     *
     * The list is paginated and filtered in SQL: the `review` tab narrows to
     * merchants still awaiting review, and `search` matches the merchant's own
     * name or any of its aliases.
     */
    public function index(MerchantFilterRequest $request, DescriptorNormalizer $normalizer): Response
    {
        $userId = $request->user()->id;
        $search = $request->search();

        /** @var LengthAwarePaginator<int, Merchant> $merchants */
        $merchants = Merchant::query()
            ->where('user_id', $userId)
            ->addSelect([
                '*',
                'total_amount_cents' => Transaction::selectRaw('COALESCE(SUM(amount_cents), 0)')->whereColumn('merchant_id', 'merchants.id'),
            ])
            ->when($request->reviewOnly(), fn (Builder $query): Builder => $query->whereNull('confirmed_at'))
            ->when($search !== null, fn (Builder $query): Builder => $query->where(
                fn (Builder $match): Builder => $match
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('aliases', fn (Builder $alias): Builder => $alias
                        ->where('normalized_name', 'like', '%' . mb_strtolower($search) . '%')),
            ))
            ->withCount('transactions')
            ->with(['category:id,name', 'aliases:id,merchant_id,name', 'rules:id,merchant_id,match_type,pattern', 'defaultTags'])
            ->orderByDesc('total_amount_cents')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('merchants', [
            'merchants' => collect($merchants->items())
                ->map(fn (Merchant $merchant): array => $this->serializeMerchant($merchant, $normalizer))
                ->all(),
            'pagination' => $this->pagination($merchants),
            'filters' => $request->echoedFilters(),
            'review_count' => Merchant::query()
                ->where('user_id', $userId)
                ->whereNull('confirmed_at')
                ->count(),
            'available_tags' => $this->availableTags(),
            'available_categories' => $this->availableCategories($userId),
        ]);
    }

    /**
     * The paginator state the frontend page nav needs.
     *
     * @param  LengthAwarePaginator<int, Merchant>  $merchants
     * @return array{current_page: int, last_page: int, per_page: int, total: int, links: list<array{url: string|null, label: string, active: bool}>}
     */
    private function pagination(LengthAwarePaginator $merchants): array
    {
        return [
            'current_page' => $merchants->currentPage(),
            'last_page' => $merchants->lastPage(),
            'per_page' => $merchants->perPage(),
            'total' => $merchants->total(),
            'links' => collect($merchants->linkCollection()->all())
                ->map(fn (array $link): array => [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'active' => $link['active'],
                ])
                ->all(),
        ];
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
            ->load(['category:id,name', 'aliases:id,merchant_id,name', 'rules:id,merchant_id,match_type,pattern', 'defaultTags']);
        $merchant->total_amount_cents = (int) $merchant->transactions()->sum('amount_cents');

        return response()->json([
            'merchant' => $this->serializeMerchant($merchant, $normalizer),
            'available_tags' => $this->availableTags(),
            'available_categories' => $this->availableCategories($request->user()->id),
        ]);
    }

    /**
     * Rename a merchant and set the category its transactions roll up into.
     * Editing also confirms it, since an edit is an explicit review of an
     * auto-created merchant.
     */
    public function update(UpdateMerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $merchant->update([
            'name' => $request->validated('name'),
            'category_id' => $request->validated('category_id'),
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
            'category_name' => $merchant->category?->name,
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
     * The user's categories offered as options in the edit dialog.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function availableCategories(int $userId): array
    {
        return Category::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
            ->all();
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
