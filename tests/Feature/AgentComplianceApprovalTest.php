<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentComplianceApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAgentPendingCompliance(int $createdBy): Agent
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        $agent = Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::PENDING_COMPLIANCE_REVIEW->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $createdBy,
        ]);

        $agent->locations()->create([
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '12 Market Road',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.6018000,
            'longitude' => 3.3515000,
            'approved_radius_metres' => 10,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $createdBy,
            'verified_by' => User::factory()->create()->id,
            'verified_at' => now(),
        ]);

        return $agent;
    }

    public function test_compliance_review_advances_agent_to_pending_approval(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();

        $agent = $this->makeAgentPendingCompliance($registrant->id);

        $result = app(AgentApprovalService::class)
            ->completeComplianceReview($agent, $reviewer->id);

        $this->assertEquals(
            AgentStatus::PENDING_APPROVAL->value,
            $result->status
        );
    }

    public function test_registering_officer_cannot_complete_own_compliance_review(): void
    {
        $registrant = User::factory()->create();

        $agent = $this->makeAgentPendingCompliance($registrant->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'cannot complete compliance review for their own agent'
        );

        app(AgentApprovalService::class)
            ->completeComplianceReview($agent, $registrant->id);
    }

    public function test_compliance_review_requires_verified_active_location(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();

        $agent = $this->makeAgentPendingCompliance($registrant->id);

        $agent->locations()->update([
            'verification_status' => 'REJECTED',
            'status' => 'INACTIVE',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('active verified location');

        app(AgentApprovalService::class)
            ->completeComplianceReview($agent->fresh(), $reviewer->id);
    }

    public function test_approval_moves_agent_to_agreement_pending(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();

        $agent = $this->makeAgentPendingCompliance($registrant->id);

        $service = app(AgentApprovalService::class);

        $agent = $service->completeComplianceReview(
            $agent,
            $reviewer->id
        );

        $result = $service->approve(
            $agent,
            $approver->id
        );

        $this->assertEquals(
            AgentStatus::AGREEMENT_PENDING->value,
            $result->status
        );

        $this->assertEquals(
            $approver->id,
            $result->approved_by
        );

        $this->assertNotNull($result->approved_at);
    }

    public function test_agent_cannot_be_approved_before_compliance_review(): void
    {
        $registrant = User::factory()->create();
        $approver = User::factory()->create();

        $agent = $this->makeAgentPendingCompliance($registrant->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not pending approval');

        app(AgentApprovalService::class)
            ->approve($agent, $approver->id);
    }
}
