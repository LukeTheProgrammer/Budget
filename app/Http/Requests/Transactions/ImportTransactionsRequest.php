<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required_without:all', 'string'],
            'all' => ['boolean'],
        ];
    }

    /**
     * Ensure the request targets either a single file or the whole folder.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('all') && $this->input('file') === null) {
                    $validator->errors()->add('file', 'Provide a file or set all to true.');
                }
            },
        ];
    }
}
