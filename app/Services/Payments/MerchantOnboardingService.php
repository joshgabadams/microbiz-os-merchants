<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantOnboardingService
{
    public function onboard(array $data, int $onboardedBy): Merchant
    {
        return DB::transaction(function () use ($data, $onboardedBy) {

            $merchant = Merchant::create([
                'merchant_code' => $this->generateMerchantCode(),
                // The current onboarding wizard has no separate "Business
                // Name" field -- fall back to trading_name/legal_name so
                // this required column still gets populated.
                'business_name' => $data['business_name'] ?? $data['trading_name'] ?? $data['legal_name'],
                'legal_name' => $data['legal_name'] ?? null,
                'trading_name' => $data['trading_name'] ?? null,
                'business_type' => $data['business_type'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'tax_identification_number' => $data['tax_identification_number'] ?? null,
                'bvn' => $data['bvn'] ?? null,
                'merchant_category_code' => $data['merchant_category_code'] ?? null,
                'industry' => $data['industry'] ?? null,
                'website' => $data['website'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'state' => $data['state'] ?? null,
                'local_government' => $data['local_government'] ?? null,
                'registered_address' => $data['registered_address'] ?? null,
                'operating_address' => $data['operating_address'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'settlement_frequency' => $data['settlement_frequency'] ?? 'T_PLUS_1',
                'expected_monthly_volume' => $data['expected_monthly_volume'] ?? null,
                'expected_monthly_value' => $data['expected_monthly_value'] ?? null,
                'risk_rating' => $data['risk_rating'] ?? 'MEDIUM',
                'customer_type' => $data['customer_type'] ?? null,
                'geographic_reach' => $data['geographic_reach'] ?? null,
                'business_model' => $data['business_model'] ?? null,
                'status' => MerchantStatus::DRAFT->value,
                'kyc_status' => 'PENDING',
                'onboarded_by' => $onboardedBy,
            ]);

            MerchantBalance::create([
                'merchant_id' => $merchant->id,
                'currency' => 'NGN',
            ]);

            return $merchant->fresh(['balance']);
        });
    }

    /**
     * @throws Exception
     */
    public function suspend(Merchant $merchant, string $reason): Merchant
    {
        if ($merchant->status !== MerchantStatus::ACTIVE->value) {
            throw new Exception("Merchant {$merchant->merchant_code} must be ACTIVE to be suspended.");
        }

        $merchant->update([
            'status' => MerchantStatus::SUSPENDED->value,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        return $merchant->fresh();
    }

    /**
     * @throws Exception
     */
    public function reactivate(Merchant $merchant): Merchant
    {
        if ($merchant->status !== MerchantStatus::SUSPENDED->value) {
            throw new Exception("Merchant {$merchant->merchant_code} must be SUSPENDED to be reactivated.");
        }

        $merchant->update([
            'status' => MerchantStatus::ACTIVE->value,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return $merchant->fresh();
    }

    /**
     * @throws Exception
     */
    public function deactivate(Merchant $merchant, string $reason): Merchant
    {
        if (in_array($merchant->status, [MerchantStatus::DEACTIVATED->value, MerchantStatus::CLOSED->value], true)) {
            throw new Exception("Merchant {$merchant->merchant_code} is already {$merchant->status}.");
        }

        $merchant->update([
            'status' => MerchantStatus::DEACTIVATED->value,
            'suspension_reason' => $reason,
        ]);

        return $merchant->fresh();
    }

    protected function generateMerchantCode(): string
    {
        do {
            $code = 'MCH-'.strtoupper(Str::random(8));
        } while (Merchant::where('merchant_code', $code)->exists());

        return $code;
    }
}
