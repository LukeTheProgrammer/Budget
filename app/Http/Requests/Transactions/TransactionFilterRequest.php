<?php

namespace App\Http\Requests\Transactions;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class TransactionFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Sanitize the filter inputs so malformed or foreign values are ignored
     * rather than rejected (FR-013). Unparseable dates/numbers become null,
     * and merchant/category ids that the user does not own are dropped so no
     * other user's data can be referenced (FR-002, SC-005).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start' => $this->sanitizeDate($this->input('start')),
            'end' => $this->sanitizeDate($this->input('end')),
            'merchant_id' => $this->sanitizeOwnedId(
                $this->input('merchant_id'),
                Merchant::class,
            ),
            'category_id' => $this->sanitizeOwnedId(
                $this->input('category_id'),
                Category::class,
            ),
            'min_amount' => $this->sanitizeAmount($this->input('min_amount')),
            'max_amount' => $this->sanitizeAmount($this->input('max_amount')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'merchant_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The normalized filters to apply to the transactions query. Amounts are
     * converted from major units (dollars) to integer cents; only keys with a
     * provided value are present.
     *
     * @return array{start?: string, end?: string, merchant_id?: int, category_id?: int, min_amount_cents?: int, max_amount_cents?: int}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('start')) {
            $filters['start'] = $this->date('start')->toDateString();
        }

        if ($this->filled('end')) {
            $filters['end'] = $this->date('end')->toDateString();
        }

        if ($this->filled('merchant_id')) {
            $filters['merchant_id'] = $this->integer('merchant_id');
        }

        if ($this->filled('category_id')) {
            $filters['category_id'] = $this->integer('category_id');
        }

        if ($this->filled('min_amount')) {
            $filters['min_amount_cents'] = (int) round((float) $this->input('min_amount') * 100);
        }

        if ($this->filled('max_amount')) {
            $filters['max_amount_cents'] = (int) round((float) $this->input('max_amount') * 100);
        }

        return $filters;
    }

    /**
     * The filters echoed back to the page in the units the user supplied, so
     * the controls can re-hydrate exactly (FR-011).
     *
     * @return array{start: string|null, end: string|null, merchant_id: int|null, category_id: int|null, min_amount: float|null, max_amount: float|null}
     */
    public function echoedFilters(): array
    {
        return [
            'start' => $this->filled('start') ? $this->date('start')->toDateString() : null,
            'end' => $this->filled('end') ? $this->date('end')->toDateString() : null,
            'merchant_id' => $this->filled('merchant_id') ? $this->integer('merchant_id') : null,
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'min_amount' => $this->filled('min_amount') ? (float) $this->input('min_amount') : null,
            'max_amount' => $this->filled('max_amount') ? (float) $this->input('max_amount') : null,
        ];
    }

    /**
     * Return a parseable date string unchanged, or null when unparseable.
     */
    private function sanitizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtotime($value) !== false ? $value : null;
    }

    /**
     * Return a non-negative numeric amount unchanged, or null otherwise.
     */
    private function sanitizeAmount(mixed $value): ?string
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Return the id only when it identifies a record of the given model owned
     * by the authenticated user; otherwise null.
     *
     * @param  class-string<Model>  $model
     */
    private function sanitizeOwnedId(mixed $value, string $model): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        $owned = $model::query()
            ->whereKey((int) $value)
            ->where('user_id', $this->user()->id)
            ->exists();

        return $owned ? (int) $value : null;
    }
}
