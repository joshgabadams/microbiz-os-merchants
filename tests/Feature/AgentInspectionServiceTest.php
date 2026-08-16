<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentInspection;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AgentInspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgentInspectionService::class);
    }

    protected function makeAgent(): Agent
    {
        $branch = Branch::create([
            'name' => 'Inspection Test Branch',
            'code' => 'ITB-'.uniqid(),
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

    protected function makeInspection(array $overrides = []): AgentInspection
    {
        $agent = $overrides['agent_id'] ?? null
            ? Agent::find($overrides['agent_id'])
            : $this->makeAgent();

        $inspector = User::factory()->create();
        $creator = User::factory()->create();

        return $this->service->create(array_merge([
            'agent_id' => $agent->id,
            'inspector_id' => $inspector->id,
            'inspection_type' => 'ROUTINE',
            'inspection_date' => now()->toDateString(),
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_inspection_is_created_with_unique_tracking_reference(): void
    {
        $first = $this->makeInspection();
        $second = $this->makeInspection();

        $this->assertNotEmpty($first->inspection_no);
        $this->assertStringStartsWith('INSP-', $first->inspection_no);

        $this->assertNotEquals(
            $first->inspection_no,
            $second->inspection_no
        );

        $this->assertDatabaseHas('agent_inspections', [
            'id' => $first->id,
            'inspection_no' => $first->inspection_no,
            'status' => 'SCHEDULED',
            'follow_up_status' => 'NOT_REQUIRED',
        ]);
    }

    public function test_scheduled_inspection_can_be_started(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->start($inspection);

        $this->assertEquals('IN_PROGRESS', $inspection->status);
        $this->assertNotNull($inspection->started_at);
    }

    public function test_inspection_can_be_completed_without_corrective_action(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'Premises match registered location, all controls in place.',
            'compliance_outcome' => 'COMPLIANT',
        ]);

        $this->assertEquals('COMPLETED', $inspection->status);
        $this->assertEquals('COMPLIANT', $inspection->compliance_outcome);
        $this->assertEquals('NOT_REQUIRED', $inspection->follow_up_status);
        $this->assertNotNull($inspection->completed_at);
    }

    public function test_inspection_completed_with_corrective_action_needs_follow_up(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'Till cash exceeded the agreed daily limit.',
            'compliance_outcome' => 'MINOR_NON_COMPLIANCE',
            'corrective_action' => 'Agent to reduce and declare excess cash within 7 days.',
            'corrective_action_deadline' => now()->addDays(7)->toDateString(),
        ]);

        $this->assertEquals('COMPLETED', $inspection->status);
        $this->assertEquals('PENDING', $inspection->follow_up_status);
        $this->assertNotNull($inspection->corrective_action_deadline);
    }

    public function test_scheduled_inspection_cannot_be_completed_twice(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'All good.',
            'compliance_outcome' => 'COMPLIANT',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Inspection {$inspection->inspection_no} cannot transition from COMPLETED."
        );

        $this->service->complete($inspection, [
            'findings' => 'Second pass.',
            'compliance_outcome' => 'COMPLIANT',
        ]);
    }

    public function test_inspection_can_be_cancelled(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->cancel(
            $inspection,
            'Agent location temporarily closed.'
        );

        $this->assertEquals('CANCELLED', $inspection->status);
        $this->assertNotNull($inspection->cancelled_at);
        $this->assertEquals(
            'Agent location temporarily closed.',
            $inspection->cancellation_reason
        );
    }

    public function test_completed_inspection_cannot_be_cancelled(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'All good.',
            'compliance_outcome' => 'COMPLIANT',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Inspection {$inspection->inspection_no} cannot transition from COMPLETED."
        );

        $this->service->cancel($inspection, 'Too late.');
    }

    public function test_follow_up_lifecycle_can_be_started_and_completed(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'Signage missing at entrance.',
            'compliance_outcome' => 'MINOR_NON_COMPLIANCE',
            'corrective_action' => 'Agent to install required signage.',
            'corrective_action_deadline' => now()->addDays(14)->toDateString(),
        ]);

        $inspection = $this->service->startFollowUp($inspection);
        $this->assertEquals('IN_PROGRESS', $inspection->follow_up_status);

        $inspection = $this->service->completeFollowUp(
            $inspection,
            'Signage confirmed installed on re-visit.'
        );

        $this->assertEquals('COMPLETED', $inspection->follow_up_status);
        $this->assertEquals(
            'Signage confirmed installed on re-visit.',
            $inspection->follow_up_notes
        );
    }

    public function test_follow_up_cannot_be_tracked_before_inspection_is_completed(): void
    {
        $inspection = $this->makeInspection();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Inspection {$inspection->inspection_no} must be completed before follow-up can be tracked."
        );

        $this->service->startFollowUp($inspection);
    }

    public function test_follow_up_not_required_cannot_be_started(): void
    {
        $inspection = $this->makeInspection();

        $inspection = $this->service->complete($inspection, [
            'findings' => 'All good, no issues found.',
            'compliance_outcome' => 'COMPLIANT',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Inspection {$inspection->inspection_no}'s follow-up cannot transition from NOT_REQUIRED."
        );

        $this->service->startFollowUp($inspection);
    }
}
