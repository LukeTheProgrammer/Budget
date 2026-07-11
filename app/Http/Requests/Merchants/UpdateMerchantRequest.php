<?php

namespace App\Http\Requests\Merchants;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant instanceof Merchant && $merchant->user_id === $this->user()?->id;
    }

    /**
     * The category select submits an empty string when "Uncategorized" is
     * chosen; treat that as an explicit null rather than a missing field.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('category_id') === '') {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
