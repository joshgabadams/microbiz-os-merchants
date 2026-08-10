<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AssignAgentTerminalLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_location_id' => ['required', 'integer', 'exists:agent_locations,id'],
        ];
    }
}
