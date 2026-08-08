<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class AddMerchantOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:2'],
            'identification_type' => ['nullable', 'string', 'max:255'],
            'identification_number' => ['nullable', 'string', 'max:255'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_director' => ['nullable', 'boolean'],
            'is_pep' => ['nullable', 'boolean'],
        ];
    }
}
