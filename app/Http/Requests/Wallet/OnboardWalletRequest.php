<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OnboardWalletRequest extends FormRequest
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
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:wallets,phone'],
            'bvn' => ['nullable', 'string', 'size:11'],
            'nin' => ['nullable', 'string', 'size:11'],
            'kyc_tier' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }
}
