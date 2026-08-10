<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgentTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'agent_location_id' => ['required', 'integer', 'exists:agent_locations,id'],
            'terminal_id' => ['required', 'string', 'max:255', 'unique:agent_terminals,terminal_id'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:agent_terminals,serial_number'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'application_version' => ['nullable', 'string', 'max:255'],
            'registered_latitude' => ['required', 'numeric', 'between:-90,90'],
            'registered_longitude' => ['required', 'numeric', 'between:-180,180'],
            'geo_fence_radius_metres' => ['required', 'integer', 'min:1'],
        ];
    }
}
