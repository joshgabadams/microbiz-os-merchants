<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentLocationService
{
    /**
     * Register a proposed operating location for an agent.
     *
     * @throws Exception
     */
    public function createLocation(
        Agent $agent,
        array $data,
        int $createdBy
    ): AgentLocation {
        if ($agent->status !== AgentStatus::PENDING_LOCATION_VERIFICATION->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending location verification."
            );
        }

        return DB::transaction(function () use ($agent, $data, $createdBy) {
            return $agent->locations()->create([
                ...$data,
                'location_code' => $data['location_code']
                    ?? 'LOC-'.strtoupper(uniqid()),
                'verification_status' => 'PENDING',
                'status' => 'PENDING',
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Independently verify an agent operating location.
     *
     * Successful verification advances the agent only to
     * PENDING_COMPLIANCE_REVIEW.
     *
     * @throws Exception
     */
    public function verifyLocation(
        Agent $agent,
        AgentLocation $location,
        int $verifiedBy,
        ?string $notes = null
    ): Agent {
        if ($agent->status !== AgentStatus::PENDING_LOCATION_VERIFICATION->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending location verification."
            );
        }

        if ($location->agent_id !== $agent->id) {
            throw new Exception(
                'The location does not belong to this agent.'
            );
        }

        if ($location->verification_status !== 'PENDING') {
            throw new Exception(
                'This location has already been reviewed.'
            );
        }

        if ($location->created_by === $verifiedBy) {
            throw new Exception(
                'The officer who created the location cannot verify it.'
            );
        }

        DB::transaction(function () use (
            $agent,
            $location,
            $verifiedBy,
            $notes
        ) {
            $location->update([
                'verification_status' => 'VERIFIED',
                'status' => 'ACTIVE',
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ]);

            $agent->update([
                'status' => AgentStatus::PENDING_COMPLIANCE_REVIEW->value,
            ]);
        });

        return $agent->fresh();
    }

    /**
     * Reject a proposed location.
     *
     * The agent remains in PENDING_LOCATION_VERIFICATION so another
     * corrected location can be submitted.
     *
     * @throws Exception
     */
    public function rejectLocation(
        Agent $agent,
        AgentLocation $location,
        int $rejectedBy,
        string $reason
    ): AgentLocation {
        if ($agent->status !== AgentStatus::PENDING_LOCATION_VERIFICATION->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending location verification."
            );
        }

        if ($location->agent_id !== $agent->id) {
            throw new Exception(
                'The location does not belong to this agent.'
            );
        }

        if ($location->verification_status !== 'PENDING') {
            throw new Exception(
                'This location has already been reviewed.'
            );
        }

        if ($location->created_by === $rejectedBy) {
            throw new Exception(
                'The officer who created the location cannot review it.'
            );
        }

        $location->update([
            'verification_status' => 'REJECTED',
            'status' => 'INACTIVE',
            'verified_by' => $rejectedBy,
            'verified_at' => now(),
            'verification_notes' => $reason,
        ]);

        return $location->fresh();
    }
}
