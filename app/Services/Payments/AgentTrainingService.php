<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentTrainingRecord;
use App\Models\TrainingDocument;
use Exception;

/**
 * Two-step onboarding training gate:
 *
 * TRAINING_PENDING -> recordDownload() -> acknowledgeTrainingGuide() ->
 * TERMINAL_PENDING
 *
 * A download alone never satisfies the gate -- explicit acknowledgement
 * is the actual completion event, matching Schedule 4's requirement
 * that training be *completed*, not just delivered. This is
 * deliberately scoped to "guide delivered + acknowledged", not a full
 * LMS/quiz/certification system -- Schedule 4 doesn't require that, and
 * MicroBiz may layer on competency assessments/retraining later without
 * this table needing to change shape.
 */
class AgentTrainingService
{
    /**
     * @throws Exception
     */
    public function recordDownload(
        Agent $agent,
        TrainingDocument $document,
        ?int $operatorId,
        int $recordedBy,
        ?string $ipAddress
    ): AgentTrainingRecord {
        if ($operatorId === null && $agent->status !== AgentStatus::TRAINING_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending training."
            );
        }

        if ($document->status !== 'ACTIVE') {
            throw new Exception('Only an active training document can be issued.');
        }

        return AgentTrainingRecord::create([
            'agent_id' => $agent->id,
            'operator_id' => $operatorId,
            'training_document_id' => $document->id,
            'training_document_version' => $document->version,
            'downloaded_at' => now(),
            'recorded_by' => $recordedBy,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * The actual gating event. Requires the guide to have been
     * downloaded first, and not already acknowledged.
     *
     * @throws Exception
     */
    public function acknowledgeTrainingGuide(AgentTrainingRecord $record, int $recordedBy): AgentTrainingRecord
    {
        if ($record->downloaded_at === null) {
            throw new Exception('The training guide has not been downloaded yet.');
        }

        if ($record->acknowledged_at !== null) {
            throw new Exception('This training record has already been acknowledged.');
        }

        $record->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $recordedBy,
            'completion_method' => 'document_acknowledgement',
        ]);

        if ($record->operator_id === null) {
            $agent = $record->agent;

            if ($agent->status === AgentStatus::TRAINING_PENDING->value) {
                $agent->update(['status' => AgentStatus::TERMINAL_PENDING->value]);
            }
        }

        return $record->fresh();
    }
}
