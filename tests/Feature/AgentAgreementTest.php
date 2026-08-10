<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
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

    protected function agreementData(): array
    {
        return [
            'expiry_date' => now()->addYear()->toDateString(),
            'renewal_due_date' => now()->addMonths(11)->toDateString(),

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

    public function test_draft_agreement_can_be_created_for_agreement_pending_agent(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $agreement = app(AgentAgreementService::class)
            ->createAgreement(
                $agent,
                $this->agreementData(),
                $creator->id
            );

        $this->assertEquals($agent->id, $agreement->agent_id);
        $this->assertEquals(1, $agreement->version);
        $this->assertEquals('DRAFT', $agreement->status);
        $this->assertNotNull($agreement->agreement_number);
    }

    public function test_agreement_cannot_be_created_for_agent_in_wrong_state(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $agent->update([
            'status' => AgentStatus::TRAINING_PENDING->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'not pending agreement execution'
        );

        app(AgentAgreementService::class)
            ->createAgreement(
                $agent->fresh(),
                $this->agreementData(),
                $creator->id
            );
    }

    public function test_agreement_versions_increment_for_same_agent(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $versionOne = $service->createAgreement(
            $agent,
            $this->agreementData(),
            $creator->id
        );

        $versionTwo = $service->createAgreement(
            $agent->fresh(),
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

    public function test_agreement_creator_cannot_execute_own_agreement(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $service->createAgreement(
            $agent,
            $this->agreementData(),
            $creator->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'officer who created the agreement cannot execute it'
        );

        $service->executeAgreement(
            $agent->fresh(),
            $agreement,
            $creator->id
        );
    }

    public function test_agreement_belonging_to_another_agent_cannot_be_executed(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();

        $agentOne = $this->makeAgentAgreementPending($creator->id);
        $agentTwo = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $service->createAgreement(
            $agentOne,
            $this->agreementData(),
            $creator->id
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

    public function test_independent_execution_activates_agreement_and_moves_agent_to_training_pending(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $service->createAgreement(
            $agent,
            $this->agreementData(),
            $creator->id
        );

        $result = $service->executeAgreement(
            $agent->fresh(),
            $agreement,
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
            'status' => 'ACTIVE',
            'executed_by' => $executor->id,
        ]);

        $this->assertNotNull(
            $agreement->fresh()->executed_at
        );

        $this->assertNotNull(
            $agreement->fresh()->effective_date
        );
    }

    public function test_executed_agreement_cannot_be_executed_twice(): void
    {
        $creator = User::factory()->create();
        $executor = User::factory()->create();

        $agent = $this->makeAgentAgreementPending($creator->id);

        $service = app(AgentAgreementService::class);

        $agreement = $service->createAgreement(
            $agent,
            $this->agreementData(),
            $creator->id
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
