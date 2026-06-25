<?php

namespace App\Http\Requests;

use App\Support\SessionPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SessionPeriodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request. Preset types ignore
     * the date fields; custom ranges require an inclusive start/end pair.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([...SessionPeriod::PRESETS, 'custom'])],
            'start' => ['required_if:type,custom', 'nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end.after_or_equal' => 'The end date must be on or after the start date.',
            'start.required_if' => 'Please provide a start date for the custom range.',
        ];
    }

    /**
     * The validated selection in the shape stored in the session.
     *
     * @return array{type: string, start?: string, end?: string}
     */
    public function selection(): array
    {
        if ($this->input('type') === 'custom') {
            return [
                'type' => 'custom',
                'start' => $this->date('start')->toDateString(),
                'end' => ($this->date('end') ?? now())->toDateString(),
            ];
        }

        return ['type' => $this->string('type')->value()];
    }
}
