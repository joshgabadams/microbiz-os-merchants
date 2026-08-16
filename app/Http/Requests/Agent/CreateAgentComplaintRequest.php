<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
            ],

            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
            ],

            'agent_location_id' => [
                'nullable',
                'integer',
                'exists:agent_locations,id',
            ],

            'agent_transaction_id' => [
                'nullable',
                'integer',
                'exists:agent_transactions,id',
            ],

            'complainant_name' => [
                'required',
                'string',
                'max:255',
            ],

            'complainant_phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'complainant_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'channel' => [
                'required',
                'string',
                'in:BRANCH,PHONE,EMAIL,WEB,AGENT,OTHER',
            ],

            'category' => [
                'required',
                'string',
                'in:CASH_IN,CASH_OUT,TRANSFER,FEES,AGENT_CONDUCT,SERVICE_FAILURE,FRAUD_SUSPECTED,OTHER',
            ],

            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'disputed_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'priority' => [
                'nullable',
                'string',
                'in:LOW,NORMAL,HIGH,CRITICAL',
            ],
        ];
    }
}
