<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

/**
 * Post-approval lifecycle: activate, restrict, suspend, reactivate, terminate.
 *
 * activate() is a staging-only simplification: the Blueprint's real gate
 * (§6) requires AGREEMENT_PENDING -> TRAINING_PENDING -> TERMINAL_PENDING
 * to all clear first, each owned by a module that doesn't exist yet
 * (agreements/training/terminals are AG-03/AG-04). Going straight from
 * APPROVED to ACTIVE skips those checks -- must not reach production
 * before AG-03/AG-04 land and AgentOperationGuard (§10) is built.
 */
class AgentActivationService
{
    /**
     * @throws Exception
     */
    public function activate(Agent $agent): Agent
    {
        if ($agent->status !== AgentStatus::APPROVED->value) {
            throw new Exception("Agent {$agent->agent_code} must be APPROVED before it can be activated.");
        }

        $agent->update([
            'status' => AgentStatus::ACTIVE->value,
            'activated_at' => now(),
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function restrict(Agent $agent, string $reason): Agent
    {
        if ($agent->status !== AgentStatus::ACTIVE->value) {
            throw new Exception("Agent {$agent->agent_code} must be ACTIVE to be restricted.");
        }

        $agent->update([
            'status' => AgentStatus::RESTRICTED->value,
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function suspend(Agent $agent, string $reason): Agent
    {
        if (! in_array($agent->status, [AgentStatus::ACTIVE->value, AgentStatus::RESTRICTED->value], true)) {
            throw new Exception("Agent {$agent->agent_code} must be ACTIVE or RESTRICTED to be suspended.");
        }

        $agent->update([
            'status' => AgentStatus::SUSPENDED->value,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function reactivate(Agent $agent): Agent
    {
        if (! in_array($agent->status, [AgentStatus::SUSPENDED->value, AgentStatus::RESTRICTED->value], true)) {
            throw new Exception("Agent {$agent->agent_code} must be SUSPENDED or RESTRICTED to be reactivated.");
        }

        $agent->update([
            'status' => AgentStatus::ACTIVE->value,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return $agent->fresh();
    }

    /**
     * @throws Exception
     */
    public function terminate(Agent $agent, string $reason): Agent
    {
        if (in_array($agent->status, [AgentStatus::TERMINATED->value, AgentStatus::REJECTED->value], true)) {
            throw new Exception("Agent {$agent->agent_code} is already {$agent->status}.");
        }

        $agent->update([
            'status' => AgentStatus::TERMINATED->value,
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }
}
