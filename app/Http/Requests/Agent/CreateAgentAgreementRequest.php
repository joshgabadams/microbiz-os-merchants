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
            'agreement_template_id' => ['required', 'integer', 'exists:agent_agreement_templates,id'],

            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'renewal_due_date' => ['nullable', 'date'],

            // Schedule 7 -- commercial and legal variables
            'initial_term_months' => ['nullable', 'integer', 'min:1'],
            'agent_termination_notice_days' => ['nullable', 'integer', 'min:0'],
            'microbiz_termination_notice_days' => ['nullable', 'integer', 'min:0'],
            'dispute_resolution_method' => ['nullable', 'string', 'in:MEDIATION,ARBITRATION,COURTS'],
            'arbitration_seat' => ['nullable', 'string', 'max:255'],
            'governing_law' => ['nullable', 'string', 'max:255'],
            'relationship_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'special_conditions' => ['nullable', 'string'],

            // Schedule 2 -- authorised services and limits
            'permitted_services' => ['nullable', 'array'],
            'permitted_services.*.service' => ['required_with:permitted_services', 'string', 'max:100'],
            'permitted_services.*.enabled' => ['required_with:permitted_services', 'boolean'],
            'permitted_services.*.limit' => ['nullable', 'numeric', 'min:0'],
            'permitted_services.*.notes' => ['nullable', 'string', 'max:500'],

            // Schedule 3 -- fees and commission
            'commercial_terms' => ['nullable', 'array'],
            'commercial_terms.*.service' => ['required_with:commercial_terms', 'string', 'max:100'],
            'commercial_terms.*.customer_fee' => ['nullable', 'string', 'max:255'],
            'commercial_terms.*.agent_commission' => ['nullable', 'string', 'max:255'],
            'commercial_terms.*.settlement_timing' => ['nullable', 'string', 'max:255'],
        ];
    }
}
