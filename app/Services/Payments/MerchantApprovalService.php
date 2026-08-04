<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\MerchantStatus;
use App\Models\Merchant;
use Exception;

/**
 * Merchant governance lifecycle: DRAFT -> PENDING_REVIEW -> APPROVED / REJECTED.
 * Activation (APPROVED -> ACTIVE) lives in MerchantActivationService, since
 * activation is a separate concern (configuring channels/limits/pricing later)
 * from the approval decision itself.
 */
class MerchantApprovalService
{
    /**
     * @throws Exception
     */
    public function submit(Merchant $merchant, int $submittedBy): Merchant
    {
        if ($merchant->status !== MerchantStatus::DRAFT->value) {
            throw new Exception("Merchant {$merchant->merchant_code} is not in DRAFT status.");
        }

        $merchant->update([
            'status' => MerchantStatus::PENDING_REVIEW->value,
            'submitted_at' => now(),
        ]);

        return $merchant->fresh();
    }

    /**
     * @throws Exception
     */
    public function approve(Merchant $merchant, int $approvedBy): Merchant
    {
        if ($merchant->status !== MerchantStatus::PENDING_REVIEW->value) {
            throw new Exception("Merchant {$merchant->merchant_code} is not pending review.");
        }

        if ($merchant->onboarded_by === $approvedBy) {
            throw new Exception('The onboarding officer cannot approve their own merchant.');
        }

        $merchant->update([
            'status' => MerchantStatus::APPROVED->value,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $merchant->fresh();
    }

    /**
     * @throws Exception
     */
    public function reject(Merchant $merchant, int $rejectedBy, string $reason): Merchant
    {
        if ($merchant->status !== MerchantStatus::PENDING_REVIEW->value) {
            throw new Exception("Merchant {$merchant->merchant_code} is not pending review.");
        }

        if ($merchant->onboarded_by === $rejectedBy) {
            throw new Exception('The onboarding officer cannot reject their own merchant.');
        }

        $merchant->update([
            'status' => MerchantStatus::REJECTED->value,
            'approved_by' => $rejectedBy,
            'rejection_reason' => $reason,
        ]);

        return $merchant->fresh();
    }
}
