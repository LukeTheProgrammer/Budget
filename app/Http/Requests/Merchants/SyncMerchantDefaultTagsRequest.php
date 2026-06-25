<?php

namespace App\Http\Requests\Merchants;

use App\Concerns\TagValidationRules;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncMerchantDefaultTagsRequest extends FormRequest
{
    use TagValidationRules;

    /**
     * Authorize only when the bound merchant belongs to the authenticated user.
     */
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant instanceof Merchant && $merchant->user_id === $this->user()?->id;
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
