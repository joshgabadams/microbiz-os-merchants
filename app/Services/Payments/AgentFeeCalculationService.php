<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentService;

/**
 * Blueprint Module 14: "the frontend must therefore show fees before
 * confirmation." Fee and commission are configured per agent per
 * service via agent_services (flat amounts, mirroring how limits are
 * already configured -- no percentage/split rule invented here since
 * none is confirmed anywhere in the Blueprint).
 *
 * Scope is deliberately limited to calculation, disclosure, and
 * commission crediting -- NOT fee collection. How a fee is actually
 * charged (deducted from the transaction amount, a separate debit, or
 * absorbed from commission) is a real business decision that isn't
 * specified anywhere in the Blueprint text available, and guessing at
 * it risks moving real money the wrong way. previewFee() exists so a
 * real frontend confirmation step can disclose the fee before the
 * transaction executes; the transaction services populate and store
 * fee_amount/commission_amount for audit purposes and credit the
 * agent's pending_commission, but never touch the customer's balance
 * for the fee itself.
 */
class AgentFeeCalculationService
{
    public function calculateFee(Agent $agent, string $serviceType): float
    {
        $service = $this->findEnabledService($agent, $serviceType);

        return $service && $service->fee_amount !== null ? (float) $service->fee_amount : 0.0;
    }

    public function calculateCommission(Agent $agent, string $serviceType): float
    {
        $service = $this->findEnabledService($agent, $serviceType);

        return $service && $service->commission_amount !== null ? (float) $service->commission_amount : 0.0;
    }

    /**
     * Real disclosure primitive for Module 14 -- a frontend calls this
     * to show the fee before the customer confirms, without executing
     * anything.
     */
    public function previewFee(Agent $agent, string $serviceType): array
    {
        return [
            'fee_amount' => $this->calculateFee($agent, $serviceType),
            'commission_amount' => $this->calculateCommission($agent, $serviceType),
        ];
    }

    protected function findEnabledService(Agent $agent, string $serviceType): ?AgentService
    {
        return AgentService::where('agent_id', $agent->id)
            ->where('service_type', $serviceType)
            ->where('status', 'ENABLED')
            ->first();
    }
}
