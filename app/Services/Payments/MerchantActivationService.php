<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\MerchantStatus;
use App\Models\Merchant;
use Exception;

class MerchantActivationService
{
    /**
     * @throws Exception
     */
    public function activate(Merchant $merchant): Merchant
    {
        if ($merchant->status !== MerchantStatus::APPROVED->value) {
            throw new Exception("Merchant {$merchant->merchant_code} must be APPROVED before it can be activated.");
        }

        $merchant->update([
            'status' => MerchantStatus::ACTIVE->value,
            'activated_at' => now(),
        ]);

        return $merchant->fresh();
    }
}
