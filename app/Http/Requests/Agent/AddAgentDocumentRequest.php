<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddAgentDocumentRequest extends FormRequest
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
            'document_type' => ['required', 'string'],
            'document_number' => ['nullable', 'string'],
            'storage_path' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
