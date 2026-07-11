<?php

namespace App\Http\Requests\Transactions;

use App\Enums\FlowType;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlowTypeRequest extends FormRequest
{
    /**
     * Only the owner of the transaction's account may reclassify it.
     */
    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $this->user() !== null
            && $transaction->account->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'flow_type' => ['required', Rule::enum(FlowType::class)],
            'apply_to_merchant' => ['boolean'],
        ];
    }

    /**
     * The flow type the user chose.
     */
    public function flowType(): FlowType
    {
        return $this->enum('flow_type', FlowType::class);
    }

    /**
     * Whether to teach the merchant this flow type so future imports of the
     * same merchant classify the same way. Defaults to true: a statement row
     * that recurs monthly should only need correcting once.
     */
    public function appliesToMerchant(): bool
    {
        return $this->boolean('apply_to_merchant', true);
    }
}
