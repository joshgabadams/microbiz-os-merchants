<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentAgreement;
use App\Models\AgentAgreementTemplate;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAgentAgreementPending(int $createdBy): Agent
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::AGREEMENT_PENDING->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $createdBy,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);
    }

    protected function makeApprovedTemplate(?User $creator = null): AgentAgreementTemplate
    {
        $creator ??= User::factory()->create();
        $approver = User::factory()->create();

        return AgentAgreementTemplate::create([
            'name' => 'MicroBiz Standard Agent Banking Agreement',
            'version' => '1.0',
            'status' => 'APPROVED',
            'legal_clauses' => 'Standard MicroBiz Agent Banking Agreement clauses.',
            'default_operator_training_obligations' => 'All operators must complete required training.',
            'default_escalation_contacts' => [
                [
                    'department' => 'Agency Banking Operations',
                    'email' => 'agency-ops@example.com',
                ],
            ],
            'governing_law' => 'Federal Republic of Nigeria',
            'created_by' => $creator->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    protected function agreementData(): array
    {
        return [
            'expiry_date' => now()->addYear()->toDateString(),
            'renewal_due_date' => now()->addMonths(11)->toDateString(),
            'initial_term_months' => 12,
            'agent_termination_notice_days' => 30,
            'microbiz_termination_notice_days' => 30,
            'dispute_resolution_method' => 'ARBITRATION',
            'arbitration_seat' => 'Lagos, Nigeria',
            'governing_law' => 'Federal Republic of Nigeria',
            'special_conditions' => 'Agent must comply with all approved operational controls.',

            'permitted_services' => [
                'CASH_IN',
                'CASH_OUT',
            ],

            'commercial_terms' => [
                'commission_model' => 'STANDARD',
            ],

            'document_path' => 'agent-agreements/test-agreement.pdf',
        ];
    }

    protected function createDraftAgreement(
        AgentAgreementService $service,
        Agent $agent,
        User $creator,
        ?AgentAgreementTemplate $template = null
    ): AgentAgreement {
        $template ??= $this->makeApprovedTemplate($creator);

        return $service->createAgreement(
            $agent,
            $template,
            $this->agreementData(),
            $creator->id
        );
    }

    protected function approveAll(
        AgentAgreementService $service,
        AgentAgreement $agreement
    ): AgentAgreement {
        foreach (AgentAgreementService::APPROVAL_TYPES as $approvalType) {
            $approver = User::factory()->create();

            $agreement = $service->recordApproval(
                $agreement->fresh(),
                $approvalType,
                $approver->id,
                'APPROVED',
                "{$approvalType} review completed."
            );
        }

        return $agreement->fresh();
    }

    protected function prepareForExecution(
        AgentAgreementService $service,
        AgentAgreement $agreement
    ): AgentAgreement {
        $agreement = $service->submitForReview(
            $agreement,
            User::factory()->create()->id
        );

        $agreement = $this->approveAll(
            $service,
            $agreement
        );

        $agreement = $service->sendForSignature(
            $agreement->fresh()
        );

        $microbizRecorder = User::factory()->create();

        $service->recordSignature(
            $agreement->fresh(),
            [
                'party' => 'MICROBIZ',
                'signatory_name' => 'MicroBiz Authorised Signatory',
                'signatory_title' => 'Head of Agency Banking',
                'signature_method' => 'DIGITAL',
                'signature_evidence_path' => 'signatures/microbiz-signature.pdf',
                'provider_reference_id' => 'MB-SIGN-001',
            ],
            $microbizRecorder->id,
            '127.0.0.1'
        );

        $agentRecorder = User::factory()->create();

        $service->recordSignature(
            $agreement->fresh(),
            [
                'party' => 'AGENT',
                'signatory_name' => 'Agent Authorised Signatory',
                'signatory_title' => 'Agent Principal',
                'signature_method' => 'DIGITAL',
                'signature_evidence_path' => 'signatures/agent-signature.pdf',
                'provider_reference_id' => 'AG-SIGN-001',
            ],
            $agentRecorder->id,
            '127.0.0.1'
        );

        return $agreement->fresh();
    }

    public function test_draft_agreement_can_be_created_from_template(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);
        $template = $this->makeApprovedTemplate($creator);

        $agreement = app(AgentAgreementService::class)
            ->createAgreement(
                $agent,
                $template,
                $this->agreementData(),
                $creator->id
            );

        $this->assertEquals($agent->id, $agreement->agent_id);
        $this->assertEquals($template->id, $agreement->agreement_template_id);
        $this->assertEquals(1, $agreement->version);
        $this->assertEquals('DRAFT', $agreement->status);
        $this->assertNotNull($agreement->agreement_number);

        $this->assertDatabaseHas('agent_agreements', [
            'id' => $agreement->id,
            'agent_id' => $agent->id,
            'agreement_template_id' => $template->id,
            'version' => 1,
            'status' => 'DRAFT',
        ]);
    }

    public function test_drafting_agreement_creates_agent_profile_snapshot(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $agreement = $this->createDraftAgreement(
            app(AgentAgreementService::class),
            $agent,
            $creator
        );

        $this->assertDatabaseHas('agent_agreement_snapshots', [
            'agent_agreement_id' => $agreement->id,
            'snapshot_type' => 'AGENT_PROFILE',
            'source_id' => $agent->id,
        ]);

        $this->assertEquals(
            1,
            $agreement->snapshots()
                ->where('snapshot_type', 'AGENT_PROFILE')
                ->count()
        );
    }

    public function test_agreement_cannot_be_created_for_agent_in_wrong_state(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $agent->update([
            'status' => AgentStatus::TRAINING_PENDING->value,
        ]);

        $template = $this->makeApprovedTemplate($creator);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'not pending agreement execution'
        );

        app(AgentAgreementService::class)
            ->createAgreement(
                $agent->fresh(),
                $template,
                $this->agreementData(),
                $creator->id
            );
    }

    public function test_agreement_versions_increment_for_same_agent(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);
        $template = $this->makeApprovedTemplate($creator);

        $service = app(AgentAgreementService::class);

        $versionOne = $service->createAgreement(
            $agent,
            $template,
            $this->agreementData(),
            $creator->id
        );

        $versionTwo = $service->createAgreement(
            $agent->fresh(),
            $template,
            $this->agreementData(),
            $creator->id
        );

        $this->assertEquals(1, $versionOne->version);
        $this->assertEquals(2, $versionTwo->version);

        $this->assertNotEquals(
            $versionOne->agreement_number,
            $versionTwo->agreement_number
        );
    }

    public function test_submitting_draft_creates_four_internal_approval_records(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $result = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $this->assertEquals(
            'PENDING_INTERNAL_REVIEW',
            $result->status
        );

        $this->assertEquals(
            4,
            $result->approvals()->count()
        );

        foreach (AgentAgreementService::APPROVAL_TYPES as $approvalType) {
            $this->assertDatabaseHas('agent_agreement_approvals', [
                'agent_agreement_id' => $agreement->id,
                'approval_type' => $approvalType,
                'status' => 'PENDING',
            ]);
        }
    }

    public function test_agreement_drafter_cannot_approve_their_own_agreement(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'officer who drafted the agreement cannot approve it'
        );

        $service->recordApproval(
            $agreement,
            'RISK',
            $creator->id,
            'APPROVED'
        );
    }

    public function test_rejected_internal_review_rejects_entire_agreement(): void
    {
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $result = $service->recordApproval(
            $agreement,
            'LEGAL',
            $reviewer->id,
            'REJECTED',
            'Legal terms require amendment.'
        );

        $this->assertEquals(
            'REJECTED',
            $result->status
        );

        $this->assertDatabaseHas('agent_agreement_approvals', [
            'agent_agreement_id' => $agreement->id,
            'approval_type' => 'LEGAL',
            'status' => 'REJECTED',
            'approved_by' => $reviewer->id,
        ]);
    }

    public function test_all_four_internal_approvals_advance_agreement_for_execution(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $agreement = $this->approveAll(
            $service,
            $agreement
        );

        $this->assertEquals(
            'APPROVED_FOR_EXECUTION',
            $agreement->status
        );

        $this->assertEquals(
            4,
            $agreement->approvals()
                ->where('status', 'APPROVED')
                ->count()
        );
    }

    public function test_agreement_cannot_be_sent_for_signature_before_internal_approval(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'must be approved for execution'
        );

        $service->sendForSignature($agreement);
    }

    public function test_approved_agreement_can_be_sent_for_signature(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $agreement = $this->approveAll(
            $service,
            $agreement
        );

        $agreement = $service->sendForSignature(
            $agreement
        );

        $this->assertEquals(
            'AWAITING_SIGNATURES',
            $agreement->status
        );
    }

    public function test_both_parties_can_record_signatures_with_evidence(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $agreement = $this->approveAll(
            $service,
            $agreement
        );

        $agreement = $service->sendForSignature(
            $agreement
        );

        $microbizUser = User::factory()->create();

        $service->recordSignature(
            $agreement,
            [
                'party' => 'MICROBIZ',
                'signatory_name' => 'MicroBiz Signatory',
                'signatory_title' => 'Director',
                'signature_method' => 'DIGITAL',
                'signature_evidence_path' => 'signatures/microbiz.pdf',
            ],
            $microbizUser->id,
            '127.0.0.1'
        );

        $agentUser = User::factory()->create();

        $service->recordSignature(
            $agreement->fresh(),
            [
                'party' => 'AGENT',
                'signatory_name' => 'Agent Signatory',
                'signatory_title' => 'Principal',
                'signature_method' => 'DIGITAL',
                'signature_evidence_path' => 'signatures/agent.pdf',
            ],
            $agentUser->id,
            '127.0.0.1'
        );

        $this->assertDatabaseHas('agent_agreement_signatories', [
            'agent_agreement_id' => $agreement->id,
            'party' => 'MICROBIZ',
            'signature_evidence_path' => 'signatures/microbiz.pdf',
        ]);

        $this->assertDatabaseHas('agent_agreement_signatories', [
            'agent_agreement_id' => $agreement->id,
            'party' => 'AGENT',
            'signature_evidence_path' => 'signatures/agent.pdf',
        ]);
    }

    public function test_execution_requires_both_signatures_with_evidence(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $service->submitForReview(
            $agreement,
            $creator->id
        );

        $agreement = $this->approveAll(
            $service,
            $agreement
        );

        $agreement = $service->sendForSignature(
            $agreement
        );

        $microbizUser = User::factory()->create();

        $service->recordSignature(
            $agreement,
            [
                'party' => 'MICROBIZ',
                'signatory_name' => 'MicroBiz Signatory',
                'signatory_title' => 'Director',
                'signature_method' => 'DIGITAL',
                'signature_evidence_path' => 'signatures/microbiz.pdf',
            ],
            $microbizUser->id,
            '127.0.0.1'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'The AGENT signature has not been recorded with evidence attached.'
        );

        $service->executeAgreement(
            $agent->fresh(),
            $agreement->fresh(),
            $executor->id
        );
    }

    public function test_agreement_belonging_to_another_agent_cannot_be_executed(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();

        $agentOne = $this->makeAgentAgreementPending($creator->id);
        $agentTwo = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agentOne,
            $creator
        );

        $agreement = $this->prepareForExecution(
            $service,
            $agreement
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'agreement does not belong to this agent'
        );

        $service->executeAgreement(
            $agentTwo,
            $agreement,
            $executor->id
        );
    }

    public function test_final_execution_executes_agreement_and_moves_agent_to_training_pending(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $this->prepareForExecution(
            $service,
            $agreement
        );

        $result = $service->executeAgreement(
            $agent->fresh(),
            $agreement->fresh(),
            $executor->id
        );

        $this->assertEquals(
            AgentStatus::TRAINING_PENDING->value,
            $result->status
        );

        $this->assertDatabaseHas('agent_agreements', [
            'id' => $agreement->id,
            'agent_id' => $agent->id,
            'version' => 1,
            'status' => 'EXECUTED',
            'executed_by' => $executor->id,
        ]);

        $executed = $agreement->fresh();

        $this->assertNotNull(
            $executed->executed_at
        );

        $this->assertNotNull(
            $executed->effective_date
        );
    }

    public function test_executed_agreement_cannot_be_executed_twice(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();
        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $this->createDraftAgreement(
            $service,
            $agent,
            $creator
        );

        $agreement = $this->prepareForExecution(
            $service,
            $agreement
        );

        $service->executeAgreement(
            $agent,
            $agreement,
            $executor->id
        );

        $this->expectException(\Exception::class);

        $service->executeAgreement(
            $agent->fresh(),
            $agreement->fresh(),
            User::factory()->create()->id
        );
    }
}
