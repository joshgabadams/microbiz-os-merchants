<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettleMerchantRequest extends FormRequest
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
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
