<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'tax_identification_number' => ['nullable', 'string', 'max:255'],
            'bvn' => ['nullable', 'string', 'digits:11'],
            'merchant_category_code' => ['nullable', 'string', 'max:4'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'local_government' => ['nullable', 'string', 'max:255'],
            'registered_address' => ['nullable', 'string'],
            'operating_address' => ['nullable', 'string'],
            'settlement_frequency' => ['nullable', 'string', 'in:SAME_DAY,T_PLUS_1,SCHEDULED,MANUAL'],
            'expected_monthly_volume' => ['nullable', 'numeric', 'min:0'],
            'expected_monthly_value' => ['nullable', 'numeric', 'min:0'],
            'risk_rating' => ['nullable', 'string', 'in:LOW,MEDIUM,HIGH'],
            'customer_type' => ['nullable', 'string', 'max:255'],
            'geographic_reach' => ['nullable', 'string', 'max:255'],
            'business_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
