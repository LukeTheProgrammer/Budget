<?php

namespace App\Http\Requests\Budgets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Each row sets the recurring monthly budget for one of the user's own
     * categories; a null amount clears the budget.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'budgets' => ['present', 'array'],
            'budgets.*.category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'budgets.*.amount_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
