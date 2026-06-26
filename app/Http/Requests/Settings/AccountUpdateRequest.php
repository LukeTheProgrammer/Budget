<?php

namespace App\Http\Requests\Settings;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    /**
     * Authorize the request against the route-bound account.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Linked accounts may only have their display name changed; institution-
     * derived fields are not editable. Manual accounts accept the full set.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
        ];

        $account = $this->route('account');

        if ($account instanceof Account && $account->isLinked()) {
            return $rules;
        }

        return array_merge($rules, [
            'type' => ['nullable', Rule::enum(AccountType::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'last_four' => ['nullable', 'digits_between:1,4'],
            'balance' => ['nullable', 'numeric'],
        ]);
    }
}
