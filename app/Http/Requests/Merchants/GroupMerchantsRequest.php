<?php

namespace App\Http\Requests\Merchants;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GroupMerchantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Every merchant referenced (primary + the full set) must belong to the
     * authenticated user, otherwise the request is rejected outright.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var list<int> $ids */
        $ids = collect($this->input('merchant_ids', []))
            ->push($this->input('primary_merchant_id'))
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return true; // let validation report the missing fields
        }

        $ownedCount = Merchant::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->count();

        return $ownedCount === count($ids);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'primary_merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'merchant_ids' => ['required', 'array', 'min:2'],
            'merchant_ids.*' => ['integer', 'distinct', 'exists:merchants,id'],
            'name' => ['nullable', 'string', 'max:200'],
        ];
    }
}
