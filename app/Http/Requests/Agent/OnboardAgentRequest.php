<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class OnboardAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_type' => ['required', 'string', 'in:INDIVIDUAL,BUSINESS,CORPORATE'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tax_identification_number' => ['nullable', 'string', 'max:255'],
            'bvn' => ['nullable', 'string', 'digits:11'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'principal_reference' => ['nullable', 'string', 'max:255'],
            'daily_transaction_limit' => ['nullable', 'numeric', 'min:0'],
            'daily_cash_out_limit' => ['nullable', 'numeric', 'min:0'],
            'single_transaction_limit' => ['nullable', 'numeric', 'min:0'],
            'next_review_date' => ['nullable', 'date'],
            'exclusive_relationship' => ['nullable', 'boolean'],
        ];
    }
}
