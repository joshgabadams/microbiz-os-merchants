<?php

namespace App\Http\Requests\FixedDeposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookFixedDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'settlement_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_penalty_fee' => ['nullable', 'numeric', 'min:0'],
            'tenor_days' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
