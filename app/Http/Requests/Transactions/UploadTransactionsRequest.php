<?php

namespace App\Http\Requests\Transactions;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use SplFileObject;

class UploadTransactionsRequest extends FormRequest
{
    /**
     * The transaction fields that must be mapped to a column.
     *
     * @var list<string>
     */
    private const REQUIRED_FIELDS = ['posted_at', 'amount', 'description'];

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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'account_id' => ['required', 'integer'],
            'mapping' => ['required', 'array'],
            'mapping.fields' => ['required', 'array'],
            'mapping.fields.posted_at' => ['required', 'string'],
            'mapping.fields.amount' => ['required', 'string'],
            'mapping.fields.description' => ['required', 'string'],
            'mapping.fields.currency' => ['nullable', 'string'],
            'mapping.fields.category' => ['nullable', 'string'],
            'mapping.amount_sign' => ['required', 'in:as_is,invert'],
            'mapping.date_format' => ['nullable', 'string'],
        ];
    }

    /**
     * Cross-field checks: the account must belong to the user, required fields
     * must map to headers actually present in the file, and a single header may
     * not be assigned to more than one required field.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateAccountOwnership($validator);
                $this->validateMapping($validator);
            },
        ];
    }

    /**
     * Ensure the selected account exists and is owned by the authenticated user.
     */
    private function validateAccountOwnership(Validator $validator): void
    {
        $owned = Account::query()
            ->whereKey($this->integer('account_id'))
            ->where('user_id', $this->user()->id)
            ->exists();

        if (! $owned) {
            $validator->errors()->add('account_id', 'Select one of your accounts.');
        }
    }

    /**
     * Ensure the mapped headers exist in the uploaded file and that no header is
     * assigned to two different required fields.
     */
    private function validateMapping(Validator $validator): void
    {
        /** @var array<string, string|null> $fields */
        $fields = $this->input('mapping.fields', []);
        $headers = $this->fileHeaders();

        $assignments = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            $header = $fields[$field] ?? null;

            if ($header === null || $header === '') {
                $validator->errors()->add("mapping.fields.{$field}", 'This field must be mapped to a column.');

                continue;
            }

            if (! in_array($header, $headers, true)) {
                $validator->errors()->add("mapping.fields.{$field}", "Column [{$header}] is not in the uploaded file.");

                continue;
            }

            if (isset($assignments[$header])) {
                $validator->errors()->add("mapping.fields.{$field}", "Column [{$header}] is already mapped to another field.");

                continue;
            }

            $assignments[$header] = $field;
        }
    }

    /**
     * Read the header row from the uploaded file.
     *
     * @return list<string>
     */
    private function fileHeaders(): array
    {
        $file = $this->file('file');

        if ($file === null) {
            return [];
        }

        $csv = new SplFileObject($file->getRealPath(), 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $csv->rewind();
        $header = $csv->current();

        return is_array($header)
            ? array_map(static fn ($value): string => trim((string) $value), $header)
            : [];
    }
}
