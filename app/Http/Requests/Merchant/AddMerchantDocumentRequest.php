<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class AddMerchantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
