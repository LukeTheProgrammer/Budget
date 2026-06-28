<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\TransactionFilterRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * The number of transactions shown per page.
     */
    private const PER_PAGE = 50;

    /**
     * Render the filterable transactions table for the authenticated user.
     */
    public function index(TransactionFilterRequest $request): Response
    {
        $userId = $request->user()->id;

        /** @var LengthAwarePaginator<int, Transaction> $transactions */
        $transactions = Transaction::query()
            ->filter($userId, $request->filters())
            ->with('tags')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('transactions', [
            'transactions' => $this->transactionRows($transactions),
            'pagination' => $this->pagination($transactions),
            'filters' => $request->echoedFilters(),
            'account_options' => $this->accountOptions($userId),
            'merchant_options' => $this->merchantOptions($userId),
            'category_options' => $this->categoryOptions($userId),
            'available_tags' => $this->availableTags(),
            'currency' => $this->currencyFor($userId),
        ]);
    }

    /**
     * Map the paginated transactions to the table's display row shape.
     *
     * @param  LengthAwarePaginator<int, Transaction>  $transactions
     * @return list<array{id: int, posted_at: string, merchant_label: string, category_name: string|null, description: string|null, amount_cents: int, currency: string, tags: list<array{slug: string, name: string}>}>
     */
    private function transactionRows(LengthAwarePaginator $transactions): array
    {
        return collect($transactions->items())
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'posted_at' => $transaction->posted_at->toDateString(),
                'merchant_label' => $transaction->merchant?->name ?? 'Unknown',
                'category_name' => $transaction->merchant?->category?->name,
                'description' => $transaction->description,
                'amount_cents' => $transaction->amount_cents,
                'currency' => $transaction->currency,
                'tags' => $transaction->tags
                    ->map(fn (Tag $tag): array => ['slug' => $tag->slug, 'name' => $tag->name])
                    ->all(),
            ])
            ->all();
    }

    /**
     * All tags in the system, for the tag-entry autocomplete suggestions.
     *
     * @return list<array{slug: string, name: string}>
     */
    private function availableTags(): array
    {
        return Tag::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => ['slug' => $tag->slug, 'name' => $tag->name])
            ->all();
    }

    /**
     * Build the pagination metadata prop for the page.
     *
     * @param  LengthAwarePaginator<int, Transaction>  $transactions
     * @return array{current_page: int, last_page: int, per_page: int, total: int, links: list<array{url: string|null, label: string, active: bool}>}
     */
    private function pagination(LengthAwarePaginator $transactions): array
    {
        return [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'links' => collect($transactions->linkCollection()->all())
                ->map(fn (array $link): array => [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'active' => $link['active'],
                ])
                ->all(),
        ];
    }

    /**
     * The authenticated user's accounts as filter options.
     *
     * @return list<array{id: int, label: string}>
     */
    private function accountOptions(int $userId): array
    {
        return Account::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'label' => $account->name,
            ])
            ->all();
    }

    /**
     * The authenticated user's merchants as filter options.
     *
     * @return list<array{id: int, label: string}>
     */
    private function merchantOptions(int $userId): array
    {
        return Merchant::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn (Merchant $merchant): array => [
                'id' => $merchant->id,
                'label' => $merchant->name,
            ])
            ->all();
    }

    /**
     * The authenticated user's categories as filter options.
     *
     * @return list<array{id: int, label: string}>
     */
    private function categoryOptions(int $userId): array
    {
        return Category::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->name,
            ])
            ->all();
    }

    /**
     * Determine the single currency to display, assuming one currency per user
     * for this iteration. Falls back to USD when the user has no accounts.
     */
    private function currencyFor(int $userId): string
    {
        return Account::query()
            ->where('user_id', $userId)
            ->value('currency') ?? 'USD';
    }
}
