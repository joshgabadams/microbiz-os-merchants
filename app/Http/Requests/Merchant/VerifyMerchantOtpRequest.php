<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMerchantOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'exists:merchant_otps,challenge_id'],
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }
}
