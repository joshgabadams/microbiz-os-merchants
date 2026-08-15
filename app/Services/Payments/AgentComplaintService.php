<?php

namespace App\Services\Payments;

use App\Models\AgentComplaint;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentComplaintService
{
    public function create(array $data): AgentComplaint
    {
        return DB::transaction(function () use ($data) {
            $now = now();

            $complaint = AgentComplaint::create([
                'complaint_no' => $this->generateComplaintNumber(),

                'agent_id' => $data['agent_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'agent_location_id' => $data['agent_location_id'] ?? null,
                'agent_transaction_id' => $data['agent_transaction_id'] ?? null,

                'complainant_name' => $data['complainant_name'],
                'complainant_phone' => $data['complainant_phone'] ?? null,
                'complainant_email' => $data['complainant_email'] ?? null,

                'channel' => $data['channel'] ?? 'BRANCH',
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],

                'disputed_amount' => $data['disputed_amount'] ?? null,

                'priority' => $data['priority'] ?? 'NORMAL',
                'status' => 'OPEN',

                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $data['created_by'] ?? null,

                'due_at' => $data['due_at']
                    ?? $this->calculateDueAt(
                        $data['priority'] ?? 'NORMAL'
                    ),

                'escalation_level' => 0,

                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $complaint;
        });
    }

    public function acknowledge(
        AgentComplaint $complaint
    ): AgentComplaint {
        $this->ensureStatus(
            $complaint,
            ['OPEN']
        );

        $complaint->update([
            'status' => 'ACKNOWLEDGED',
            'acknowledged_at' => now(),
        ]);

        return $complaint->refresh();
    }

    public function startProgress(
        AgentComplaint $complaint
    ): AgentComplaint {
        $this->ensureStatus(
            $complaint,
            ['ACKNOWLEDGED', 'ESCALATED']
        );

        $complaint->update([
            'status' => 'IN_PROGRESS',
        ]);

        return $complaint->refresh();
    }

    public function assign(
        AgentComplaint $complaint,
        int $userId
    ): AgentComplaint {
        if (in_array(
            $complaint->status,
            ['RESOLVED', 'CLOSED'],
            true
        )) {
            throw new Exception(
                'Resolved or closed complaint cannot be reassigned.'
            );
        }

        $complaint->update([
            'assigned_to' => $userId,
        ]);

        return $complaint->refresh();
    }

    public function resolve(
        AgentComplaint $complaint,
        string $resolutionSummary
    ): AgentComplaint {
        $this->ensureStatus(
            $complaint,
            ['ACKNOWLEDGED', 'IN_PROGRESS', 'ESCALATED']
        );

        $complaint->update([
            'status' => 'RESOLVED',
            'resolution_summary' => $resolutionSummary,
            'resolved_at' => now(),
        ]);

        return $complaint->refresh();
    }

    public function close(
        AgentComplaint $complaint
    ): AgentComplaint {
        $this->ensureStatus(
            $complaint,
            ['RESOLVED']
        );

        $complaint->update([
            'status' => 'CLOSED',
            'closed_at' => now(),
        ]);

        return $complaint->refresh();
    }

    public function escalate(
        AgentComplaint $complaint,
        string $reason
    ): AgentComplaint {
        if (in_array(
            $complaint->status,
            ['RESOLVED', 'CLOSED'],
            true
        )) {
            throw new Exception(
                'Resolved or closed complaint cannot be escalated.'
            );
        }

        $complaint->update([
            'status' => 'ESCALATED',
            'escalation_level' => $complaint->escalation_level + 1,
            'escalation_reason' => $reason,
            'escalated_at' => now(),
        ]);

        return $complaint->refresh();
    }

    protected function generateComplaintNumber(): string
    {
        return 'CMP-'
            .now()->format('YmdHis')
            .'-'
            .strtoupper(substr(uniqid(), -6));
    }

    protected function calculateDueAt(
        string $priority
    ) {
        return match (strtoupper($priority)) {
            'CRITICAL' => now()->addDay(),
            'HIGH' => now()->addDays(3),
            'LOW' => now()->addDays(14),
            default => now()->addDays(7),
        };
    }

    protected function ensureStatus(
        AgentComplaint $complaint,
        array $allowedStatuses
    ): void {
        if (! in_array(
            $complaint->status,
            $allowedStatuses,
            true
        )) {
            throw new Exception(
                "Complaint {$complaint->complaint_no} cannot transition from {$complaint->status}."
            );
        }
    }
}