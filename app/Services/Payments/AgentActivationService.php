<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

/**
 * Post-approval agent lifecycle management.
 *
 * Primary onboarding path:
 *
 * APPROVED
 *   -> AGREEMENT_PENDING
 *   -> TRAINING_PENDING
 *   -> TERMINAL_PENDING
 *   -> ACTIVE
 *
 * Activation is the final onboarding gate. An agent may become ACTIVE only
 * after the agreement, training, location, operator and terminal requirements
 * have all been satisfied.
 */
class AgentActivationService
{
    /**
     * Activate an agent after all onboarding prerequisites have cleared.
     *
     * @throws Exception
     */
    public function activate(Agent $agent): Agent
    {
        if ($agent->status !== AgentStatus::TERMINAL_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} must be TERMINAL_PENDING before it can be activated."
            );
        }

        if (! $agent->agreements()
            ->where('status', 'EXECUTED')
            ->exists()) {
            throw new Exception(
                "Agent {$agent->agent_code} has no active agreement."
            );
        }

        if (! $agent->trainingRecords()
            ->whereNull('operator_id')
            ->whereNotNull('acknowledged_at')
            ->exists()) {
            throw new Exception(
                "Agent {$agent->agent_code} has not completed mandatory training."
            );
        }

        if (! $agent->locations()
            ->where('verification_status', 'VERIFIED')
            ->where('status', 'ACTIVE')
            ->exists()) {
            throw new Exception(
                "Agent {$agent->agent_code} has no active verified location."
            );
        }

        if (! $agent->operators()
            ->where('status', 'ACTIVE')
            ->exists()) {
            throw new Exception(
                "Agent {$agent->agent_code} has no active operator."
            );
        }

        if (! $agent->terminals()
            ->where('status', 'ACTIVE')
            ->exists()) {
            throw new Exception(
                "Agent {$agent->agent_code} has no active terminal."
            );
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
            throw new Exception(
                "Agent {$agent->agent_code} must be ACTIVE to be restricted."
            );
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
        if (! in_array(
            $agent->status,
            [
                AgentStatus::ACTIVE->value,
                AgentStatus::RESTRICTED->value,
            ],
            true
        )) {
            throw new Exception(
                "Agent {$agent->agent_code} must be ACTIVE or RESTRICTED to be suspended."
            );
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
        if (! in_array(
            $agent->status,
            [
                AgentStatus::SUSPENDED->value,
                AgentStatus::RESTRICTED->value,
            ],
            true
        )) {
            throw new Exception(
                "Agent {$agent->agent_code} must be SUSPENDED or RESTRICTED to be reactivated."
            );
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
        if (in_array(
            $agent->status,
            [
                AgentStatus::TERMINATED->value,
                AgentStatus::REJECTED->value,
            ],
            true
        )) {
            throw new Exception(
                "Agent {$agent->agent_code} is already {$agent->status}."
            );
        }

        $agent->update([
            'status' => AgentStatus::TERMINATED->value,
            'suspension_reason' => $reason,
        ]);

        return $agent->fresh();
    }
}
