<?php

namespace App\Services\Payments;

use App\Models\AgentInspection;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentInspectionService
{
    public function create(array $data): AgentInspection
    {
        return DB::transaction(function () use ($data) {
            return AgentInspection::create([
                'inspection_no' => $this->generateInspectionNumber(),

                'agent_id' => $data['agent_id'],
                'agent_location_id' => $data['agent_location_id'] ?? null,
                'inspector_id' => $data['inspector_id'],

                'inspection_type' => $data['inspection_type'],
                'inspection_date' => $data['inspection_date'],

                'status' => 'SCHEDULED',

                'follow_up_status' => 'NOT_REQUIRED',

                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    public function start(AgentInspection $inspection): AgentInspection
    {
        $this->ensureStatus($inspection, ['SCHEDULED']);

        $inspection->update([
            'status' => 'IN_PROGRESS',
            'started_at' => now(),
        ]);

        return $inspection->refresh();
    }

    /**
     * The actual site-visit outcome. follow_up_status is derived from
     * whether a corrective action/deadline was recorded -- a completed
     * inspection with no corrective action needs no follow-up; one that
     * has a corrective action starts PENDING until resolved separately.
     */
    public function complete(
        AgentInspection $inspection,
        array $data
    ): AgentInspection {
        $this->ensureStatus($inspection, ['SCHEDULED', 'IN_PROGRESS']);

        $hasCorrectiveAction = ! empty($data['corrective_action']);

        $inspection->update([
            'status' => 'COMPLETED',
            'findings' => $data['findings'],
            'compliance_outcome' => $data['compliance_outcome'],
            'corrective_action' => $data['corrective_action'] ?? null,
            'corrective_action_deadline' => $data['corrective_action_deadline'] ?? null,
            'follow_up_status' => $hasCorrectiveAction ? 'PENDING' : 'NOT_REQUIRED',
            'completed_at' => now(),
        ]);

        return $inspection->refresh();
    }

    public function cancel(
        AgentInspection $inspection,
        string $reason
    ): AgentInspection {
        $this->ensureStatus($inspection, ['SCHEDULED', 'IN_PROGRESS']);

        $inspection->update([
            'status' => 'CANCELLED',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        return $inspection->refresh();
    }

    public function startFollowUp(AgentInspection $inspection): AgentInspection
    {
        $this->ensureFollowUpStatus($inspection, ['PENDING']);

        $inspection->update([
            'follow_up_status' => 'IN_PROGRESS',
        ]);

        return $inspection->refresh();
    }

    public function completeFollowUp(
        AgentInspection $inspection,
        ?string $notes
    ): AgentInspection {
        $this->ensureFollowUpStatus($inspection, ['PENDING', 'IN_PROGRESS']);

        $inspection->update([
            'follow_up_status' => 'COMPLETED',
            'follow_up_notes' => $notes,
        ]);

        return $inspection->refresh();
    }

    protected function generateInspectionNumber(): string
    {
        return 'INSP-'
            .now()->format('YmdHis')
            .'-'
            .strtoupper(substr(uniqid(), -6));
    }

    protected function ensureStatus(
        AgentInspection $inspection,
        array $allowedStatuses
    ): void {
        if (! in_array($inspection->status, $allowedStatuses, true)) {
            throw new Exception(
                "Inspection {$inspection->inspection_no} cannot transition from {$inspection->status}."
            );
        }
    }

    protected function ensureFollowUpStatus(
        AgentInspection $inspection,
        array $allowedStatuses
    ): void {
        if ($inspection->status !== 'COMPLETED') {
            throw new Exception(
                "Inspection {$inspection->inspection_no} must be completed before follow-up can be tracked."
            );
        }

        if (! in_array($inspection->follow_up_status, $allowedStatuses, true)) {
            throw new Exception(
                "Inspection {$inspection->inspection_no}'s follow-up cannot transition from {$inspection->follow_up_status}."
            );
        }
    }
}
