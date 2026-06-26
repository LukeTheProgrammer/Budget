<?php

namespace App\Http\Requests\Settings;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(AccountType::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'last_four' => ['nullable', 'digits_between:1,4'],
            'balance' => ['nullable', 'numeric'],
        ];
    }
}
