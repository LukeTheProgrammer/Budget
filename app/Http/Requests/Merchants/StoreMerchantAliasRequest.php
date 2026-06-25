<?php

namespace App\Http\Requests\Merchants;

use App\Models\Merchant;
use App\Models\MerchantAlias;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantAliasRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:200',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $normalized = mb_strtolower(trim((string) $value));

                    $exists = MerchantAlias::query()
                        ->where('user_id', $this->user()?->id)
                        ->where('normalized_name', $normalized)
                        ->exists();

                    if ($exists) {
                        $fail(__('That alias is already used by another merchant.'));
                    }
                },
            ],
        ];
    }
}
