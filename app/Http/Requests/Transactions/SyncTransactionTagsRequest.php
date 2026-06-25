<?php

namespace App\Http\Requests\Transactions;

use App\Concerns\TagValidationRules;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncTransactionTagsRequest extends FormRequest
{
    use TagValidationRules;

    /**
     * Authorize only when the bound transaction belongs to one of the
     * authenticated user's accounts.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            && $transaction->account()->where('user_id', $this->user()?->id)->exists();
    }

    /**
     * Trim submitted tag values before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->trimTags();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->tagRules();
    }
}
