<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AgentCashInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator_id' => [
                'required',
                'integer',
                'exists:agent_operators,id',
            ],

            'terminal_id' => [
                'required',
                'integer',
                'exists:agent_terminals,id',
            ],

            'customer_account_id' => [
                'required',
                'integer',
                'exists:customer_accounts,id',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'idempotency_key' => [
                'required',
                'uuid',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'customer_reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'narration' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}