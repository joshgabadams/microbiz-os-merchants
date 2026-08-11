<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OnboardMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * business_name is kept nullable (not dropped) for backward
     * compatibility with any existing caller that still sends it directly;
     * the service falls back to trading_name/legal_name when absent, since
     * the current onboarding wizard's Step 1 doesn't have a separate
     * "Business Name" field at all -- just Legal Name and Trading Name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:255'],
            'tax_identification_number' => ['nullable', 'string', 'max:255'],
            'bvn' => ['nullable', 'string', 'digits:11'],
            'merchant_category_code' => ['nullable', 'string', 'max:4'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],

            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'local_government' => ['nullable', 'string', 'max:255'],
            'registered_address' => ['nullable', 'string'],
            'operating_address' => ['nullable', 'string'],

            'branch_id' => ['required', 'integer', 'exists:branches,id'],
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
