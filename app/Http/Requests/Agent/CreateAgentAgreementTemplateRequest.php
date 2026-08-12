<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentAgreementTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:50'],
            'legal_clauses' => ['nullable', 'string'],
            'default_operator_training_obligations' => ['nullable', 'string'],
            'default_escalation_contacts' => ['nullable', 'array'],
            'governing_law' => ['nullable', 'string', 'max:255'],
        ];
    }
}
