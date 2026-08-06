<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

/**
 * Pre-approval workflow: DRAFT -> PENDING_APPROVAL -> APPROVED / REJECTED.
 *
 * The Blueprint's real chain is DRAFT -> PENDING_KYC ->
 * PENDING_LOCATION_VERIFICATION -> PENDING_COMPLIANCE_REVIEW ->
 * PENDING_APPROVAL, each step owned by a module that doesn't exist yet
 * (KYC review is AG-02, location verification is AG-03). Submitting here
 * deliberately skips straight to PENDING_APPROVAL rather than stopping at
 * PENDING_KYC with no way to advance -- a staging-only simplification,
 * same pattern as other "module doesn't exist yet" decisions in MPAY.md.
 * AG-02/AG-03 must insert the real intermediate gates before this reaches
 * production.
 */
class AgentApprovalService
{
    /**
     * @throws Exception
     */
    public function submit(Agent $agent, int $submittedBy): Agent
    {
        if ($agent->status !== AgentStatus::DRAFT->value) {
            throw new Exception("Agent {$agent->agent_code} is not in DRAFT status.");
        }

        $agent->update([
            'status' => AgentStatus::PENDING_APPROVAL->value,
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function approve(Agent $agent, int $approvedBy): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_APPROVAL->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending approval.");
        }

        if ($agent->created_by === $approvedBy) {
            throw new Exception('The registering officer cannot approve their own agent.');
        }

        $agent->update([
            'status' => AgentStatus::APPROVED->value,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function reject(Agent $agent, int $rejectedBy, string $reason): Agent
    {
        if ($agent->status !== AgentStatus::PENDING_APPROVAL->value) {
            throw new Exception("Agent {$agent->agent_code} is not pending approval.");
        }

        if ($agent->created_by === $rejectedBy) {
            throw new Exception('The registering officer cannot reject their own agent.');
        }

        // §8.1 has no dedicated rejection_reason column -- reusing
        // suspension_reason as the general "why this negative status"
        // field rather than adding a column outside the given schema.
        $agent->update([
            'status' => AgentStatus::REJECTED->value,
            'approved_by' => $rejectedBy,
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }
}
