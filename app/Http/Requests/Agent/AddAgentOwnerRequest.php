<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddAgentOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:2'],
            'identification_type' => ['nullable', 'string'],
            'identification_number' => ['nullable', 'string'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_director' => ['nullable', 'boolean'],
            'is_pep' => ['nullable', 'boolean'],
            'sanctions_match' => ['nullable', 'boolean'],
        ];
    }
}
