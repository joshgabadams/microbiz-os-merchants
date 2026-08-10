<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentAgreement;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentAgreementService
{
    /**
     * Create the next version of an agent agreement.
     *
     * @throws Exception
     */
    public function createAgreement(
        Agent $agent,
        array $data,
        int $createdBy
    ): AgentAgreement {
        if ($agent->status !== AgentStatus::AGREEMENT_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending agreement execution."
            );
        }

        return DB::transaction(function () use ($agent, $data, $createdBy) {
            $nextVersion = ((int) $agent->agreements()->max('version')) + 1;

            return $agent->agreements()->create([
                ...$data,
                'agreement_number' => $data['agreement_number']
                    ?? 'AGR-'.$agent->agent_code.'-V'.$nextVersion,
                'version' => $nextVersion,
                'status' => 'DRAFT',
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Execute a draft agreement.
     *
     * Agreement execution is the AG-03 exit gate:
     * AGREEMENT_PENDING -> TRAINING_PENDING.
     *
     * @throws Exception
     */
    public function executeAgreement(
        Agent $agent,
        AgentAgreement $agreement,
        int $executedBy
    ): Agent {
        if ($agent->status !== AgentStatus::AGREEMENT_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending agreement execution."
            );
        }

        if ($agreement->agent_id !== $agent->id) {
            throw new Exception(
                'The agreement does not belong to this agent.'
            );
        }

        if ($agreement->status !== 'DRAFT') {
            throw new Exception(
                'Only a draft agreement can be executed.'
            );
        }

        if ($agreement->created_by === $executedBy) {
            throw new Exception(
                'The officer who created the agreement cannot execute it.'
            );
        }

        DB::transaction(function () use (
            $agent,
            $agreement,
            $executedBy
        ) {
            $agent->agreements()
                ->where('id', '!=', $agreement->id)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'SUPERSEDED',
                ]);

            $agreement->update([
                'status' => 'ACTIVE',
                'executed_by' => $executedBy,
                'executed_at' => now(),
                'effective_date' => $agreement->effective_date
                    ?? now()->toDateString(),
            ]);

            $agent->update([
                'status' => AgentStatus::TRAINING_PENDING->value,
            ]);
        });

        return $agent->fresh();
    }
}
