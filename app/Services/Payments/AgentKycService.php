<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

class AgentKycService
{
    /**
     * @throws Exception
     */
    public function completeKyc(Agent $agent, int $reviewedBy): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_KYC->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending KYC.");
        }

        if ($agent->created_by === $reviewedBy) {
            throw new Exception('The registering officer cannot complete KYC review for their own agent.');
        }

        if ($agent->owners()->count() === 0) {
            throw new Exception('At least one beneficial owner must be recorded before KYC can be completed.');
        }

        if ($agent->documents()->count() === 0) {
            throw new Exception('At least one document must be recorded before KYC can be completed.');
        }

        $agent->update([
            'status' => AgentStatus::PENDING_LOCATION_VERIFICATION->value,
            'kyc_status' => 'COMPLETED',
        ]);

        return $agent->fresh();
    }
}
