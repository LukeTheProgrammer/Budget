<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait TagValidationRules
{
    /**
     * Validation rules for a list of raw tag display values. Each value is
     * trimmed, non-empty, at most 50 characters, and limited to letters,
     * numbers, spaces, and hyphens.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function tagRules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required', 'string', 'max:50', 'regex:/^[\p{L}\p{N} \-]+$/u'],
        ];
    }

    /**
     * Trim each submitted tag value so whitespace-only entries fail the
     * `required` rule and surrounding whitespace is normalized away.
     */
    protected function trimTags(): void
    {
        $tags = $this->input('tags');

        if (is_array($tags)) {
            $this->merge([
                'tags' => array_map(
                    static fn ($value) => is_string($value) ? trim($value) : $value,
                    $tags,
                ),
            ]);
        }
    }
}
