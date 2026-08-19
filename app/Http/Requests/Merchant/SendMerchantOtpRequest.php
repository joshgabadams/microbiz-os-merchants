<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class SendMerchantOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Contract's own example sends this as a bare JSON number
            // (`"fincore_client_id": 2`), not a quoted string -- don't
            // force a type that would reject exactly what the frontend
            // sends.
            'fincore_client_id' => ['required', 'exists:merchant_identities,fincore_client_id'],
            'account_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
