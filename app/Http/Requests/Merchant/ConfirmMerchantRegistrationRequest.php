<?php

namespace App\Http\Requests\Merchant;

/**
 * Same fields as OnboardMerchantRequest, plus the Fineract client id
 * resolved during the /reg/merchant/preview step. Extending (not
 * duplicating) OnboardMerchantRequest's rules so the two stay in sync if
 * that request's validation changes later.
 */
class ConfirmMerchantRegistrationRequest extends OnboardMerchantRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'fincore_client_id' => ['required', 'integer'],
        ]);
    }
}
