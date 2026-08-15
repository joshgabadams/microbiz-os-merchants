<?php

namespace Tests\Feature;

use App\Models\AgentComplaint;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentComplaintApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Complaint Test Branch',
            'code' => 'CTB-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        $branch = $this->makeBranch();

        return array_merge([
            'branch_id' => $branch->id,
            'complainant_name' => 'Test Customer',
            'complainant_phone' => '08000000000',
            'complainant_email' => 'customer@example.com',
            'channel' => 'BRANCH',
            'category' => 'SERVICE_FAILURE',
            'subject' => 'Unable to complete agency transaction',
            'description' => 'Customer reported that the requested agency transaction could not be completed.',
            'disputed_amount' => 10000,
            'priority' => 'NORMAL',
        ], $overrides);
    }

    protected function createComplaint(User $user): AgentComplaint
    {
        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/agent-complaints',
            $this->validPayload()
        );

        $response->assertCreated();

        return AgentComplaint::findOrFail(
            $response->json('id')
        );
    }

    public function test_unauthenticated_user_cannot_access_complaints(): void
    {
        $response = $this->getJson(
            '/api/v1/agent-complaints'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_complaint(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/agent-complaints',
            $this->validPayload()
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'complainant_name',
                'Test Customer'
            )
            ->assertJsonPath(
                'channel',
                'BRANCH'
            )
            ->assertJsonPath(
                'category',
                'SERVICE_FAILURE'
            )
            ->assertJsonPath(
                'status',
                'OPEN'
            );

        $this->assertNotEmpty(
            $response->json('complaint_no')
        );

        $this->assertNotNull(
            $response->json('due_at')
        );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $response->json('id'),
            'created_by' => $user->id,
            'complainant_name' => 'Test Customer',
            'status' => 'OPEN',
        ]);
    }

    public function test_create_complaint_requires_core_fields(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/agent-complaints',
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'complainant_name',
                'channel',
                'category',
                'subject',
                'description',
            ]);
    }

    public function test_create_complaint_rejects_invalid_channel_and_category(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/agent-complaints',
            $this->validPayload([
                'channel' => 'INVALID_CHANNEL',
                'category' => 'INVALID_CATEGORY',
            ])
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'channel',
                'category',
            ]);
    }

    public function test_authenticated_user_can_list_complaints(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->getJson(
            '/api/v1/agent-complaints'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $complaint->id
            );
    }

    public function test_complaint_list_can_filter_by_status(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->getJson(
            '/api/v1/agent-complaints?status=OPEN'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $complaint->id,
                'status' => 'OPEN',
            ]);
    }

    public function test_authenticated_user_can_show_complaint(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->getJson(
            "/api/v1/agent-complaints/{$complaint->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'id',
                $complaint->id
            )
            ->assertJsonPath(
                'complaint_no',
                $complaint->complaint_no
            );
    }

    public function test_complaint_can_be_acknowledged_through_api(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/acknowledge"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'ACKNOWLEDGED'
            );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $complaint->id,
            'status' => 'ACKNOWLEDGED',
        ]);
    }

    public function test_complaint_can_be_assigned_through_api(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/assign",
            [
                'assigned_to' => $assignee->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'assigned_to.id',
                $assignee->id
            );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $complaint->id,
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_assignment_requires_valid_user(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/assign",
            [
                'assigned_to' => 999999999,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assigned_to',
            ]);
    }

    public function test_acknowledged_complaint_can_start_progress_through_api(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/acknowledge"
        )->assertOk();

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/start-progress"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'IN_PROGRESS'
            );
    }

    public function test_complaint_can_be_escalated_through_api(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/escalate",
            [
                'reason' => 'SLA risk requires management attention.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'escalation_level',
                1
            );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $complaint->id,
            'escalation_level' => 1,
        ]);
    }

    public function test_escalation_requires_reason(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/escalate",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);
    }

    public function test_complaint_can_be_resolved_and_closed_through_api(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/acknowledge"
        )->assertOk();

        $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/start-progress"
        )->assertOk();

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/resolve",
            [
                'resolution_summary' =>
                    'Transaction issue investigated and customer informed.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'RESOLVED'
            );

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/close"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'CLOSED'
            );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $complaint->id,
            'status' => 'CLOSED',
        ]);
    }

    public function test_resolution_requires_summary(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/resolve",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'resolution_summary',
            ]);
    }

    public function test_open_complaint_cannot_be_closed_directly_through_api(): void
    {
        $user = User::factory()->create();

        $complaint = $this->createComplaint($user);

        $response = $this->postJson(
            "/api/v1/agent-complaints/{$complaint->id}/close"
        );

       $response
    ->assertConflict()
    ->assertJsonPath(
        'message',
        "Complaint {$complaint->complaint_no} cannot transition from OPEN."
    );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $complaint->id,
            'status' => 'OPEN',
        ]);
    }
}