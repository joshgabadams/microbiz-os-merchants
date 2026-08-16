<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentInspection;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentInspectionApiTest extends TestCase
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

    protected function makeAgent(): Agent
    {
        $branch = Branch::create([
            'name' => 'Inspection API Test Branch',
            'code' => 'IATB-'.uniqid(),
            'office_id' => 1,
        ]);

        $creator = User::factory()->create();

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => 'ACTIVE',
            'kyc_status' => 'COMPLETED',
            'created_by' => $creator->id,
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        $agent = $overrides['agent_id'] ?? null
            ? null
            : $this->makeAgent();

        $inspector = User::factory()->create();

        return array_merge([
            'agent_id' => $agent?->id,
            'inspector_id' => $inspector->id,
            'inspection_type' => 'ROUTINE',
            'inspection_date' => now()->toDateString(),
        ], $overrides);
    }

    protected function inspectionOfficer(): User
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'agent-inspection-officer');

        return $user;
    }

    public function test_unauthenticated_user_cannot_access_inspections(): void
    {
        $response = $this->getJson('/api/v1/agent-inspections');

        $response->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_create_inspection(): void
    {
        $user = User::factory()->create();
        $this->actingAsBasicAuth($user);

        $response = $this->postJson(
            '/api/v1/agent-inspections',
            $this->validPayload()
        );

        $response->assertForbidden();
    }

    public function test_inspection_officer_can_create_and_view_inspection(): void
    {
        $officer = $this->inspectionOfficer();
        $this->actingAsBasicAuth($officer);

        $response = $this->postJson(
            '/api/v1/agent-inspections',
            $this->validPayload()
        );

        $response->assertCreated();
        $response->assertJsonPath('status', 'SCHEDULED');
        $response->assertJsonPath('follow_up_status', 'NOT_REQUIRED');

        $inspectionId = $response->json('id');

        $show = $this->getJson(
            "/api/v1/agent-inspections/{$inspectionId}"
        );

        $show->assertOk();
        $show->assertJsonPath('id', $inspectionId);
    }

    public function test_inspection_requires_a_valid_agent(): void
    {
        $officer = $this->inspectionOfficer();
        $this->actingAsBasicAuth($officer);

        $response = $this->postJson(
            '/api/v1/agent-inspections',
            $this->validPayload(['agent_id' => 999999])
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['agent_id']);
    }

    protected function createInspection(): AgentInspection
    {
        $officer = $this->inspectionOfficer();
        $this->actingAsBasicAuth($officer);

        $response = $this->postJson(
            '/api/v1/agent-inspections',
            $this->validPayload()
        );

        $response->assertCreated();

        return AgentInspection::findOrFail($response->json('id'));
    }

    public function test_inspection_can_be_started_and_completed_via_api(): void
    {
        $inspection = $this->createInspection();

        $start = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/start"
        );
        $start->assertOk();
        $start->assertJsonPath('status', 'IN_PROGRESS');

        $complete = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/complete",
            [
                'findings' => 'Agent premises match registered location.',
                'compliance_outcome' => 'COMPLIANT',
            ]
        );

        $complete->assertOk();
        $complete->assertJsonPath('status', 'COMPLETED');
        $complete->assertJsonPath('follow_up_status', 'NOT_REQUIRED');
    }

    public function test_completing_inspection_without_compliance_outcome_is_rejected(): void
    {
        $inspection = $this->createInspection();

        $response = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/complete",
            ['findings' => 'Missing outcome.']
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['compliance_outcome']);
    }

    public function test_corrective_action_requires_a_deadline(): void
    {
        $inspection = $this->createInspection();

        $response = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/complete",
            [
                'findings' => 'Till cash exceeded the agreed limit.',
                'compliance_outcome' => 'MINOR_NON_COMPLIANCE',
                'corrective_action' => 'Reduce and declare excess cash.',
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['corrective_action_deadline']);
    }

    public function test_inspection_can_be_cancelled_via_api(): void
    {
        $inspection = $this->createInspection();

        $response = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/cancel",
            ['reason' => 'Agent location temporarily closed.']
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'CANCELLED');
    }

    public function test_follow_up_can_be_started_and_completed_via_api(): void
    {
        $inspection = $this->createInspection();

        $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/complete",
            [
                'findings' => 'Signage missing at entrance.',
                'compliance_outcome' => 'MINOR_NON_COMPLIANCE',
                'corrective_action' => 'Install required signage.',
                'corrective_action_deadline' => now()->addDays(14)->toDateString(),
            ]
        );

        $start = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/follow-up/start"
        );
        $start->assertOk();
        $start->assertJsonPath('follow_up_status', 'IN_PROGRESS');

        $complete = $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/follow-up/complete",
            ['notes' => 'Signage confirmed installed on re-visit.']
        );
        $complete->assertOk();
        $complete->assertJsonPath('follow_up_status', 'COMPLETED');
    }

    public function test_overdue_filter_returns_only_pending_past_deadline_inspections(): void
    {
        $inspection = $this->createInspection();

        $this->postJson(
            "/api/v1/agent-inspections/{$inspection->id}/complete",
            [
                'findings' => 'Overdue corrective action test.',
                'compliance_outcome' => 'MAJOR_NON_COMPLIANCE',
                'corrective_action' => 'Fix immediately.',
                'corrective_action_deadline' => now()->subDay()->toDateString(),
            ]
        );

        $response = $this->getJson(
            '/api/v1/agent-inspections?overdue=1'
        );

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $inspection->id);
    }
}
