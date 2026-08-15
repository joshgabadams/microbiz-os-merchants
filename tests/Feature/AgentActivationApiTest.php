<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentAgreement;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTrainingRecord;
use App\Models\Branch;
use App\Models\Role;
use App\Models\TrainingDocument;
use App\Models\User;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentActivationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentsRbacSeeder::class);
    }

    protected function attachRole(
        User $user,
        string $roleName
    ): void {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

    protected function attachActivationRole(User $user): void
    {
        $this->attachRole(
            $user,
            'agent-approval-officer'
        );
    }

    protected function makeAgent(
        string $status = AgentStatus::TERMINAL_PENDING->value
    ): Agent {
        $creator = User::factory()->create();

        $branch = Branch::create([
            'name' => 'Activation API Test Branch',
            'code' => 'AATB-'.uniqid(),
            'office_id' => 1,
        ]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Activation API Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => $status,
            'kyc_status' => 'COMPLETED',
            'created_by' => $creator->id,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);
    }

    protected function addActiveAgreement(
        Agent $agent
    ): AgentAgreement {
        return AgentAgreement::create([
            'agent_id' => $agent->id,
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'EXECUTED',
            'effective_date' => now()->toDateString(),
            'created_by' => User::factory()->create()->id,
            'executed_by' => User::factory()->create()->id,
            'executed_at' => now(),
        ]);
    }

    protected function addAcknowledgedTraining(
        Agent $agent
    ): AgentTrainingRecord {
        $user = User::factory()->create();

        $document = TrainingDocument::create([
            'name' => 'Agent Operations Training Guide',
            'version' => '1.0',
            'status' => 'ACTIVE',
            'file_path' => 'training/agent-guide.pdf',
            'created_by' => $user->id,
        ]);

        return AgentTrainingRecord::create([
            'agent_id' => $agent->id,
            'operator_id' => null,
            'training_document_id' => $document->id,
            'training_document_version' => $document->version,
            'downloaded_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'recorded_by' => $user->id,
            'completion_method' => 'document_acknowledgement',
        ]);
    }

    protected function addActiveVerifiedLocation(
        Agent $agent
    ): AgentLocation {
        return AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Activation API Test Street',
            'city' => 'Lagos',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 70,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => User::factory()->create()->id,
            'verified_by' => User::factory()->create()->id,
            'verified_at' => now(),
        ]);
    }

    protected function addActiveOperator(
        Agent $agent,
        AgentLocation $location
    ): AgentOperator {
        return AgentOperator::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OPERATOR',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);
    }

    protected function addActiveTerminal(
        Agent $agent,
        AgentLocation $location
    ): AgentTerminal {
        return AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-'.uniqid(),
            'serial_number' => 'SN-'.uniqid(),
            'device_model' => 'TEST-POS',
            'provider' => 'TEST',
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 70,
            'activated_at' => now(),
            'assigned_by' => User::factory()->create()->id,
        ]);
    }

    protected function makeFullyReadyAgent(): Agent
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);
        $this->addAcknowledgedTraining($agent);

        $location = $this->addActiveVerifiedLocation($agent);

        $this->addActiveOperator(
            $agent,
            $location
        );

        $this->addActiveTerminal(
            $agent,
            $location
        );

        return $agent->fresh();
    }

    public function test_unauthenticated_user_cannot_activate_agent(): void
    {
        $agent = $this->makeAgent();

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $response->assertUnauthorized();

        $this->assertEquals(
            AgentStatus::TERMINAL_PENDING->value,
            $agent->fresh()->status
        );
    }

    public function test_user_without_activate_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-kyc-officer'
        );

        Sanctum::actingAs($user);

        $agent = $this->makeAgent();

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $response->assertForbidden();

        $this->assertEquals(
            AgentStatus::TERMINAL_PENDING->value,
            $agent->fresh()->status
        );
    }

    public function test_authorized_user_cannot_activate_approved_agent_directly(): void
    {
        $user = User::factory()->create();

        $this->attachActivationRole($user);

        Sanctum::actingAs($user);

        $agent = $this->makeAgent(
            AgentStatus::APPROVED->value
        );

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::APPROVED->value,
            $agent->fresh()->status
        );
    }

    public function test_authorized_user_cannot_activate_incomplete_terminal_pending_agent(): void
    {
        $user = User::factory()->create();

        $this->attachActivationRole($user);

        Sanctum::actingAs($user);

        $agent = $this->makeAgent();

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::TERMINAL_PENDING->value,
            $agent->fresh()->status
        );
    }

    public function test_authorized_user_can_activate_fully_ready_agent(): void
    {
        $user = User::factory()->create();

        $this->attachActivationRole($user);

        Sanctum::actingAs($user);

        $agent = $this->makeFullyReadyAgent();

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $response->assertOk();

        $agent->refresh();

        $this->assertEquals(
            AgentStatus::ACTIVE->value,
            $agent->status
        );

        $this->assertNotNull(
            $agent->activated_at
        );

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'status' => AgentStatus::ACTIVE->value,
        ]);
    }

    public function test_active_agent_cannot_be_activated_twice_through_api(): void
    {
        $user = User::factory()->create();

        $this->attachActivationRole($user);

        Sanctum::actingAs($user);

        $agent = $this->makeFullyReadyAgent();

        $firstResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $firstResponse->assertOk();

        $secondResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/activate"
        );

        $secondResponse->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::ACTIVE->value,
            $agent->fresh()->status
        );
    }
}