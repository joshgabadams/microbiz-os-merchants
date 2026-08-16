<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentsRbacSeeder::class);
    }

    protected function makeAgentPendingLocation(int $createdBy): Agent
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
            'status' => AgentStatus::PENDING_LOCATION_VERIFICATION->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $createdBy,
        ]);
    }

    protected function attachRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

    protected function locationPayload(): array
    {
        return [
            'address_line_1' => '12 Market Road',
            'landmark' => 'Central Market',
            'city' => 'Lagos',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.6018000,
            'longitude' => 3.3515000,
            'approved_radius_metres' => 10,
        ];
    }

    public function test_unauthenticated_user_cannot_create_agent_location(): void
    {
        $creator = User::factory()->create();
        $agent = $this->makeAgentPendingLocation($creator->id);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_location_permission_is_blocked(): void
    {
        $registrant = User::factory()->create();
        $user = User::factory()->create();

        $this->attachRole($user, 'agent-kyc-officer');

        $this->actingAsBasicAuth($user);

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $response->assertForbidden();
    }

    public function test_location_officer_can_create_location(): void
    {
        $registrant = User::factory()->create();
        $locationOfficer = User::factory()->create();

        $this->attachRole(
            $locationOfficer,
            'agent-location-officer'
        );

        $this->actingAsBasicAuth($locationOfficer);

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $response->assertCreated();

        $this->assertDatabaseHas('agent_locations', [
            'agent_id' => $agent->id,
            'created_by' => $locationOfficer->id,
            'verification_status' => 'PENDING',
            'status' => 'PENDING',
        ]);
    }

    public function test_same_location_officer_cannot_verify_location(): void
    {
        $registrant = User::factory()->create();
        $officer = User::factory()->create();

        $this->attachRole($officer, 'agent-location-officer');
        $this->attachRole($officer, 'agent-location-verifier');

        $this->actingAsBasicAuth($officer);

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $locationId = $createResponse->json('data.id');

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations/{$locationId}/verify",
            [
                'notes' => 'Location confirmed.',
            ]
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::PENDING_LOCATION_VERIFICATION->value,
            $agent->fresh()->status
        );
    }

    public function test_independent_verifier_can_verify_location(): void
    {
        $registrant = User::factory()->create();
        $locationOfficer = User::factory()->create();
        $verifier = User::factory()->create();

        $this->attachRole(
            $locationOfficer,
            'agent-location-officer'
        );

        $this->attachRole(
            $verifier,
            'agent-location-verifier'
        );

        $this->actingAsBasicAuth($locationOfficer);

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $locationId = $createResponse->json('data.id');

        $this->actingAsBasicAuth($verifier);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations/{$locationId}/verify",
            [
                'notes' => 'Physical premises and GPS confirmed.',
            ]
        );

        $response->assertOk();

        $this->assertEquals(
            AgentStatus::PENDING_COMPLIANCE_REVIEW->value,
            $agent->fresh()->status
        );
    }

    public function test_compliance_officer_can_complete_compliance_review(): void
    {
        $registrant = User::factory()->create();
        $locationOfficer = User::factory()->create();
        $verifier = User::factory()->create();
        $complianceOfficer = User::factory()->create();

        $this->attachRole(
            $locationOfficer,
            'agent-location-officer'
        );

        $this->attachRole(
            $verifier,
            'agent-location-verifier'
        );

        $this->attachRole(
            $complianceOfficer,
            'agent-compliance-officer'
        );

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $this->actingAsBasicAuth($locationOfficer);

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/locations",
            $this->locationPayload()
        );

        $locationId = $createResponse->json('data.id');

        $this->actingAsBasicAuth($verifier);

        $this->postJson(
            "/api/v1/agents/{$agent->id}/locations/{$locationId}/verify"
        )->assertOk();

        $this->actingAsBasicAuth($complianceOfficer);

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/complete-compliance-review"
        );

        $response->assertOk();

        $this->assertEquals(
            AgentStatus::PENDING_APPROVAL->value,
            $agent->fresh()->status
        );
    }
}