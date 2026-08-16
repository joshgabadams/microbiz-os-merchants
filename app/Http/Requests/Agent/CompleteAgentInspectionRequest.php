<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAgentInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'findings' => [
                'required',
                'string',
            ],

            'compliance_outcome' => [
                'required',
                'string',
                'in:COMPLIANT,MINOR_NON_COMPLIANCE,MAJOR_NON_COMPLIANCE,CRITICAL_NON_COMPLIANCE',
            ],

            'corrective_action' => [
                'nullable',
                'string',
            ],

            'corrective_action_deadline' => [
                'nullable',
                'date',
                'required_with:corrective_action',
            ],
        ];
    }
}
