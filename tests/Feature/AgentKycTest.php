<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentApprovalService;
use App\Services\Payments\AgentKycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentKycTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDraftAgent(int $createdBy): Agent
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::DRAFT->value,
            'created_by' => $createdBy,
        ]);
    }

    public function test_submit_moves_draft_agent_to_pending_kyc_not_pending_approval(): void
    {
        $user = User::factory()->create();
        $agent = $this->makeDraftAgent($user->id);

        $result = app(AgentApprovalService::class)->submit($agent, $user->id);

        $this->assertEquals(AgentStatus::PENDING_KYC->value, $result->status);
    }

    public function test_kyc_cannot_be_completed_without_any_owners_or_documents(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('beneficial owner');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);
    }

    public function test_kyc_cannot_be_completed_with_owner_but_no_document(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create([
            'full_name' => 'Jane Owner',
            'ownership_percentage' => 100,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('document');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);
    }

    public function test_registering_officer_cannot_complete_their_own_agents_kyc(): void
    {
        $registrant = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot complete KYC review for their own agent');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $registrant->id);
    }

    public function test_kyc_completes_successfully_with_owner_and_document_present(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $result = app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);

        $this->assertEquals(AgentStatus::PENDING_LOCATION_VERIFICATION->value, $result->status);
        $this->assertEquals('COMPLETED', $result->kyc_status);
    }

    public function test_kyc_cannot_be_completed_twice(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);
        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $kycService = app(AgentKycService::class);
        $kycService->completeKyc($agent->fresh(), $reviewer->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not pending KYC');

        $kycService->completeKyc($agent->fresh(), $reviewer->id);
    }
}
