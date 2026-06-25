<?php

namespace App\Http\Requests\Merchants;

use App\Enums\MerchantRuleType;
use App\Models\Merchant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantRuleRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'match_type' => ['required', Rule::enum(MerchantRuleType::class)],
            'pattern' => [
                'required',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('match_type') !== MerchantRuleType::Regex->value) {
                        return;
                    }

                    if (@preg_match((string) $value, '') === false) {
                        $fail(__('That regular expression is not valid.'));
                    }
                },
            ],
        ];
    }
}
