<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantOnboardingService
{
    public function onboard(array $data, int $onboardedBy): Merchant
    {
        return DB::transaction(function () use ($data, $onboardedBy) {

            $merchant = Merchant::create([
                'merchant_code' => $this->generateMerchantCode(),
                'business_name' => $data['business_name'],
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => MerchantStatus::DRAFT->value,
                'onboarded_by' => $onboardedBy,
            ]);

            MerchantBalance::create([
                'merchant_id' => $merchant->id,
                'currency' => 'NGN',
            ]);

            return $merchant->fresh(['balance']);
        });
    }

    public function suspend(Merchant $merchant): Merchant
    {
        $merchant->update(['status' => MerchantStatus::SUSPENDED->value]);

        return $merchant->fresh();
    }

    public function reactivate(Merchant $merchant): Merchant
    {
        $merchant->update(['status' => MerchantStatus::ACTIVE->value]);

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
