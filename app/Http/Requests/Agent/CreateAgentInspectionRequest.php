<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'required',
                'integer',
                'exists:agents,id',
            ],

            'agent_location_id' => [
                'nullable',
                'integer',
                'exists:agent_locations,id',
            ],

            'inspector_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'inspection_type' => [
                'required',
                'string',
                'in:ROUTINE,COMPLIANCE,FOLLOW_UP,INCIDENT_TRIGGERED,PRE_ACTIVATION',
            ],

            'inspection_date' => [
                'required',
                'date',
            ],
        ];
    }
}
