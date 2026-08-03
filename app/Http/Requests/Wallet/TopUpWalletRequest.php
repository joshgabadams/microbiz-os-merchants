<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TopUpWalletRequest extends FormRequest
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
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
