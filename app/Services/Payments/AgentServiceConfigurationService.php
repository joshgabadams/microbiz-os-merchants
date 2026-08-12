<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentService;
use Exception;

/**
 * Blueprint §9/Module 7: per-agent explicit service enablement. An
 * agent must not automatically receive all service permissions merely
 * because the agent has been activated -- this is the service that
 * makes that opt-in, not opt-out.
 */
class AgentServiceConfigurationService
{
    public function enableService(
        Agent $agent,
        string $serviceType,
        int $enabledBy,
        ?float $limitOverride = null,
        ?float $feeAmount = null,
        bool $authenticationRequired = true,
        ?string $effectiveDate = null,
        ?string $expiryDate = null
    ): AgentService {
        return AgentService::updateOrCreate(
            ['agent_id' => $agent->id, 'service_type' => $serviceType],
            [
                'status' => 'ENABLED',
                'limit_override' => $limitOverride,
                'fee_amount' => $feeAmount,
                'authentication_required' => $authenticationRequired,
                'effective_date' => $effectiveDate,
                'expiry_date' => $expiryDate,
                'enabled_by' => $enabledBy,
                'enabled_at' => now(),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function disableService(Agent $agent, string $serviceType): AgentService
    {
        $service = AgentService::where('agent_id', $agent->id)
            ->where('service_type', $serviceType)
            ->first();

        if (! $service) {
            throw new Exception("Agent {$agent->agent_code} does not have {$serviceType} configured.");
        }

        $service->update(['status' => 'DISABLED']);

        return $service->fresh();
    }
}
