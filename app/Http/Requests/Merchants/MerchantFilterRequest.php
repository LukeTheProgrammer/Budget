<?php

namespace App\Http\Requests\Merchants;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MerchantFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Drop an unrecognized tab rather than rejecting the request, so a stale or
     * hand-edited URL falls back to the default view instead of erroring.
     */
    protected function prepareForValidation(): void
    {
        $tab = $this->input('tab');

        $this->merge([
            'tab' => in_array($tab, ['all', 'review'], true) ? $tab : null,
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
            'tab' => ['nullable', 'in:all,review'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Whether only merchants awaiting review should be listed.
     */
    public function reviewOnly(): bool
    {
        return $this->input('tab') === 'review';
    }

    /**
     * The trimmed search term, or null when no term was supplied.
     */
    public function search(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search === '' ? null : $search;
    }

    /**
     * The filters echoed back to the page so the controls can re-hydrate.
     *
     * @return array{tab: string, search: string}
     */
    public function echoedFilters(): array
    {
        return [
            'tab' => $this->reviewOnly() ? 'review' : 'all',
            'search' => $this->search() ?? '',
        ];
    }
}
