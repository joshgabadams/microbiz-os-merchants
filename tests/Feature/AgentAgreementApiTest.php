<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentAgreementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentsRbacSeeder::class);
    }

    protected function attachRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

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
            'legal_name' => 'Agreement Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::AGREEMENT_PENDING->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $createdBy,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);
    }

    protected function agreementPayload(): array
    {
        return [
            'expiry_date' => now()->addYear()->toDateString(),
            'renewal_due_date' => now()
                ->addMonths(11)
                ->toDateString(),

            'permitted_services' => [
                'CASH_IN',
                'CASH_OUT',
            ],

            'commercial_terms' => [
                'commission_model' => 'STANDARD',
            ],

            'document_path' =>
                'agent-agreements/test-agreement.pdf',
        ];
    }

    public function test_user_without_agreement_permission_is_blocked(): void
    {
        $registrant = User::factory()->create();
        $user = User::factory()->create();

        $this->attachRole($user, 'agent-kyc-officer');

        Sanctum::actingAs($user);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload()
        );

        $response->assertForbidden();
    }

    public function test_agreement_officer_can_create_agreement(): void
    {
        $registrant = User::factory()->create();
        $agreementOfficer = User::factory()->create();

        $this->attachRole(
            $agreementOfficer,
            'agent-agreement-officer'
        );

        Sanctum::actingAs($agreementOfficer);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload()
        );

        $response->assertCreated();

        $this->assertDatabaseHas('agent_agreements', [
            'agent_id' => $agent->id,
            'version' => 1,
            'status' => 'DRAFT',
            'created_by' => $agreementOfficer->id,
        ]);
    }

    public function test_same_officer_cannot_create_and_execute_agreement(): void
    {
        $registrant = User::factory()->create();
        $officer = User::factory()->create();

        $this->attachRole(
            $officer,
            'agent-agreement-officer'
        );

        $this->attachRole(
            $officer,
            'agent-agreement-executor'
        );

        Sanctum::actingAs($officer);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload()
        );

        $agreementId = $createResponse->json('data.id');

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements/{$agreementId}/execute"
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::AGREEMENT_PENDING->value,
            $agent->fresh()->status
        );
    }

    public function test_independent_executor_executes_agreement_and_moves_agent_to_training(): void
    {
        $registrant = User::factory()->create();
        $agreementOfficer = User::factory()->create();
        $executor = User::factory()->create();

        $this->attachRole(
            $agreementOfficer,
            'agent-agreement-officer'
        );

        $this->attachRole(
            $executor,
            'agent-agreement-executor'
        );

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        Sanctum::actingAs($agreementOfficer);

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload()
        );

        $agreementId = $createResponse->json('data.id');

        Sanctum::actingAs($executor);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements/{$agreementId}/execute"
        );

        $response->assertOk();

        $this->assertEquals(
            AgentStatus::TRAINING_PENDING->value,
            $agent->fresh()->status
        );

        $this->assertDatabaseHas('agent_agreements', [
            'id' => $agreementId,
            'agent_id' => $agent->id,
            'status' => 'ACTIVE',
            'executed_by' => $executor->id,
        ]);
    }
}