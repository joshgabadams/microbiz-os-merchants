<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_date' => [
                'nullable',
                'date',
            ],

            'expiry_date' => [
                'nullable',
                'date',
                'after:effective_date',
            ],

            'renewal_due_date' => [
                'nullable',
                'date',
            ],

            'permitted_services' => [
                'nullable',
                'array',
            ],

            'permitted_services.*' => [
                'string',
                'max:100',
            ],

            'commercial_terms' => [
                'nullable',
                'array',
            ],

            'document_path' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
