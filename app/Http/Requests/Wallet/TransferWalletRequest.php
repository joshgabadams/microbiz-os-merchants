<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferWalletRequest extends FormRequest
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
            'from_wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'to_wallet_id' => ['required', 'integer', 'exists:wallets,id', 'different:from_wallet_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
