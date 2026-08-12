<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentAgreement;
use App\Models\AgentAgreementApproval;
use App\Models\AgentAgreementSignatory;
use App\Models\AgentAgreementSnapshot;
use App\Models\AgentAgreementTemplate;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Agent Banking Agreement lifecycle, modelled as a real document/contract
 * form rather than a single-step "execute" action:
 *
 * DRAFT -> PENDING_INTERNAL_REVIEW -> APPROVED_FOR_EXECUTION ->
 * AWAITING_SIGNATURES -> EXECUTED
 *
 * The Agent's own lifecycle only advances AGREEMENT_PENDING ->
 * TRAINING_PENDING at the final EXECUTED transition, never earlier.
 */
class AgentAgreementService
{
    public const APPROVAL_TYPES = ['RISK', 'COMPLIANCE', 'LEGAL', 'BUSINESS_OWNER'];

    public const SIGNATORY_PARTIES = ['MICROBIZ', 'AGENT'];

    /**
     * Draft the next version of an agent agreement from a template.
     * Snapshots the agent's current profile, verified locations and
     * terminals (Schedule 1/5) at drafting time -- this is what gets
     * frozen at execution, immune to later live-record changes.
     *
     * @throws Exception
     */
    public function createAgreement(
        Agent $agent,
        AgentAgreementTemplate $template,
        array $data,
        int $createdBy
    ): AgentAgreement {
        if ($agent->status !== AgentStatus::AGREEMENT_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending agreement execution."
            );
        }

        return DB::transaction(function () use ($agent, $template, $data, $createdBy) {
            $nextVersion = ((int) $agent->agreements()->max('version')) + 1;

            $agreement = $agent->agreements()->create([
                ...$data,
                'agreement_template_id' => $template->id,
                'agreement_number' => $data['agreement_number']
                    ?? 'AGR-'.$agent->agent_code.'-V'.$nextVersion,
                'version' => $nextVersion,
                'status' => 'DRAFT',
                'governing_law' => $data['governing_law'] ?? $template->governing_law,
                'created_by' => $createdBy,
            ]);

            $this->snapshotAgentProfile($agreement, $agent);
            $this->snapshotLocations($agreement, $agent);
            $this->snapshotTerminals($agreement, $agent);

            return $agreement->fresh();
        });
    }

    private function snapshotAgentProfile(AgentAgreement $agreement, Agent $agent): void
    {
        AgentAgreementSnapshot::create([
            'agent_agreement_id' => $agreement->id,
            'snapshot_type' => 'AGENT_PROFILE',
            'source_id' => $agent->id,
            'snapshot_data' => [
                'agent_code' => $agent->agent_code,
                'agent_type' => $agent->agent_type,
                'legal_name' => $agent->legal_name,
                'trading_name' => $agent->trading_name,
                'registration_number' => $agent->registration_number,
                'bvn' => $agent->bvn,
                'phone' => $agent->phone,
                'email' => $agent->email,
                'principal_reference' => $agent->principal_reference,
            ],
            'snapshotted_at' => now(),
        ]);
    }

    private function snapshotLocations(AgentAgreement $agreement, Agent $agent): void
    {
        foreach ($agent->verifiedLocations as $location) {
            AgentAgreementSnapshot::create([
                'agent_agreement_id' => $agreement->id,
                'snapshot_type' => 'AGENT_LOCATION',
                'source_id' => $location->id,
                'snapshot_data' => [
                    'location_code' => $location->location_code,
                    'address_line_1' => $location->address_line_1,
                    'address_line_2' => $location->address_line_2,
                    'landmark' => $location->landmark,
                    'city' => $location->city,
                    'local_government' => $location->local_government,
                    'state' => $location->state,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'approved_radius_metres' => $location->approved_radius_metres,
                    'verified_by' => $location->verified_by,
                    'verified_at' => (string) $location->verified_at,
                ],
                'snapshotted_at' => now(),
            ]);
        }
    }

    private function snapshotTerminals(AgentAgreement $agreement, Agent $agent): void
    {
        foreach ($agent->terminals as $terminal) {
            AgentAgreementSnapshot::create([
                'agent_agreement_id' => $agreement->id,
                'snapshot_type' => 'AGENT_TERMINAL',
                'source_id' => $terminal->id,
                'snapshot_data' => [
                    'terminal_id' => $terminal->terminal_id,
                    'serial_number' => $terminal->serial_number,
                    'device_model' => $terminal->device_model,
                    'provider' => $terminal->provider,
                    'application_version' => $terminal->application_version,
                    'agent_location_id' => $terminal->agent_location_id,
                    'registered_latitude' => $terminal->registered_latitude,
                    'registered_longitude' => $terminal->registered_longitude,
                    'geo_fence_radius_metres' => $terminal->geo_fence_radius_metres,
                    'activated_at' => (string) $terminal->activated_at,
                ],
                'snapshotted_at' => now(),
            ]);
        }
    }

    /**
     * Submit a draft agreement for internal Risk/Compliance/Legal/
     * Business Owner review -- creates the four pending approval rows.
     *
     * @throws Exception
     */
    public function submitForReview(AgentAgreement $agreement, int $submittedBy): AgentAgreement
    {
        if ($agreement->status !== 'DRAFT') {
            throw new Exception('Only a draft agreement can be submitted for review.');
        }

        return DB::transaction(function () use ($agreement, $submittedBy) {
            foreach (self::APPROVAL_TYPES as $type) {
                AgentAgreementApproval::firstOrCreate([
                    'agent_agreement_id' => $agreement->id,
                    'approval_type' => $type,
                ], [
                    'status' => 'PENDING',
                ]);
            }

            $agreement->update(['status' => 'PENDING_INTERNAL_REVIEW']);

            return $agreement->fresh();
        });
    }

    /**
     * Record one internal approval decision. A REJECTED decision
     * immediately rejects the whole agreement; once all four are
     * APPROVED, the agreement advances to APPROVED_FOR_EXECUTION.
     *
     * @throws Exception
     */
    public function recordApproval(
        AgentAgreement $agreement,
        string $approvalType,
        int $approvedBy,
        string $decision,
        ?string $notes = null
    ): AgentAgreement {
        if ($agreement->status !== 'PENDING_INTERNAL_REVIEW') {
            throw new Exception('Agreement is not pending internal review.');
        }

        if (! in_array($approvalType, self::APPROVAL_TYPES, true)) {
            throw new Exception("Unknown approval type: {$approvalType}.");
        }

        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw new Exception('Decision must be APPROVED or REJECTED.');
        }

        $approval = $agreement->approvals()->where('approval_type', $approvalType)->first();

        if (! $approval) {
            throw new Exception('This approval record does not exist for this agreement.');
        }

        if ($approval->status !== 'PENDING') {
            throw new Exception("The {$approvalType} decision has already been recorded.");
        }

        if ((int) $agreement->created_by === $approvedBy) {
            throw new Exception('The officer who drafted the agreement cannot approve it.');
        }

        return DB::transaction(function () use ($agreement, $approval, $approvedBy, $decision, $notes) {
            $approval->update([
                'status' => $decision,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            if ($decision === 'REJECTED') {
                $agreement->update(['status' => 'REJECTED']);

                return $agreement->fresh();
            }

            $allApproved = $agreement->approvals()
                ->where('status', '!=', 'APPROVED')
                ->doesntExist();

            if ($allApproved) {
                $agreement->update(['status' => 'APPROVED_FOR_EXECUTION']);
            }

            return $agreement->fresh();
        });
    }

    /**
     * @throws Exception
     */
    public function sendForSignature(AgentAgreement $agreement): AgentAgreement
    {
        if ($agreement->status !== 'APPROVED_FOR_EXECUTION') {
            throw new Exception('Agreement must be approved for execution before it can be sent for signature.');
        }

        $agreement->update(['status' => 'AWAITING_SIGNATURES']);

        return $agreement->fresh();
    }

    /**
     * Record one party's signature (upsert by party). Both MICROBIZ and
     * AGENT signatures must be recorded before executeAgreement() will
     * succeed.
     *
     * @throws Exception
     */
    public function recordSignature(
        AgentAgreement $agreement,
        array $data,
        int $recordedBy,
        ?string $ipAddress
    ): AgentAgreementSignatory {
        if ($agreement->status !== 'AWAITING_SIGNATURES') {
            throw new Exception('Agreement is not awaiting signatures.');
        }

        if (! in_array($data['party'], self::SIGNATORY_PARTIES, true)) {
            throw new Exception("Unknown signatory party: {$data['party']}.");
        }

        return AgentAgreementSignatory::updateOrCreate(
            [
                'agent_agreement_id' => $agreement->id,
                'party' => $data['party'],
            ],
            [
                'signatory_name' => $data['signatory_name'],
                'signatory_title' => $data['signatory_title'] ?? null,
                'signature_method' => $data['signature_method'],
                'signature_evidence_path' => $data['signature_evidence_path'] ?? null,
                'provider_reference_id' => $data['provider_reference_id'] ?? null,
                'signed_at' => now(),
                'ip_address' => $ipAddress,
                'recorded_by' => $recordedBy,
            ]
        );
    }

    /**
     * Final execution gate: requires all four approvals APPROVED and
     * both signatories recorded with real evidence. Freezes the
     * agreement as EXECUTED and is the only point that advances the
     * Agent's own lifecycle AGREEMENT_PENDING -> TRAINING_PENDING.
     *
     * @throws Exception
     */
    public function executeAgreement(Agent $agent, AgentAgreement $agreement, int $executedBy): Agent
    {
        if ($agent->status !== AgentStatus::AGREEMENT_PENDING->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not pending agreement execution."
            );
        }

        if ($agreement->agent_id !== $agent->id) {
            throw new Exception('The agreement does not belong to this agent.');
        }

        if ($agreement->status !== 'AWAITING_SIGNATURES') {
            throw new Exception('Agreement is not awaiting execution.');
        }

        $signatoryParties = $agreement->signatories()
            ->whereNotNull('signature_evidence_path')
            ->pluck('party')
            ->all();

        foreach (self::SIGNATORY_PARTIES as $party) {
            if (! in_array($party, $signatoryParties, true)) {
                throw new Exception("The {$party} signature has not been recorded with evidence attached.");
            }
        }

        DB::transaction(function () use ($agent, $agreement, $executedBy) {
            $agent->agreements()
                ->where('id', '!=', $agreement->id)
                ->where('status', 'EXECUTED')
                ->update(['status' => 'SUPERSEDED']);

            $agreement->update([
                'status' => 'EXECUTED',
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
