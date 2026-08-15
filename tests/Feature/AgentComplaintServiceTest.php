<?php

namespace Tests\Feature;

use App\Models\AgentComplaint;
use App\Models\User;
use App\Services\Payments\AgentComplaintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentComplaintServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AgentComplaintService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgentComplaintService::class);
    }

    protected function makeComplaint(array $overrides = []): AgentComplaint
    {
        $creator = User::factory()->create();

        return $this->service->create(array_merge([
            'complainant_name' => 'Jane Customer',
            'complainant_phone' => '08000000000',
            'channel' => 'BRANCH',
            'category' => 'CASH_OUT',
            'subject' => 'Cash-out dispute',
            'description' => 'Customer reports a disputed cash-out transaction.',
            'priority' => 'NORMAL',
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_complaint_is_created_with_unique_tracking_reference(): void
    {
        $first = $this->makeComplaint();
        $second = $this->makeComplaint();

        $this->assertNotEmpty($first->complaint_no);
        $this->assertStringStartsWith('CMP-', $first->complaint_no);

        $this->assertNotEquals(
            $first->complaint_no,
            $second->complaint_no
        );

        $this->assertDatabaseHas('agent_complaints', [
            'id' => $first->id,
            'complaint_no' => $first->complaint_no,
            'status' => 'OPEN',
        ]);
    }

    public function test_new_complaint_receives_default_sla_due_date(): void
    {
        $before = now()->addDays(7)->subMinute();

        $complaint = $this->makeComplaint([
            'priority' => 'NORMAL',
        ]);

        $after = now()->addDays(7)->addMinute();

        $this->assertNotNull($complaint->due_at);

        $this->assertTrue(
            $complaint->due_at->between($before, $after)
        );
    }

    public function test_complaint_can_be_acknowledged(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->acknowledge($complaint);

        $this->assertEquals(
            'ACKNOWLEDGED',
            $complaint->status
        );

        $this->assertNotNull($complaint->acknowledged_at);
    }

    public function test_complaint_can_be_assigned(): void
    {
        $complaint = $this->makeComplaint();
        $officer = User::factory()->create();

        $complaint = $this->service->assign(
            $complaint,
            $officer->id
        );

        $this->assertEquals(
            $officer->id,
            $complaint->assigned_to
        );
    }

    public function test_acknowledged_complaint_can_move_to_in_progress(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->acknowledge($complaint);
        $complaint = $this->service->startProgress($complaint);

        $this->assertEquals(
            'IN_PROGRESS',
            $complaint->status
        );
    }

    public function test_complaint_can_be_escalated(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->escalate(
            $complaint,
            'Complaint requires management review.'
        );

        $this->assertEquals('ESCALATED', $complaint->status);
        $this->assertEquals(1, $complaint->escalation_level);
        $this->assertNotNull($complaint->escalated_at);

        $this->assertEquals(
            'Complaint requires management review.',
            $complaint->escalation_reason
        );
    }

    public function test_escalating_again_increases_escalation_level(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->escalate(
            $complaint,
            'First escalation.'
        );

        $complaint = $this->service->escalate(
            $complaint,
            'Second escalation.'
        );

        $this->assertEquals(2, $complaint->escalation_level);
        $this->assertEquals('ESCALATED', $complaint->status);
    }

    public function test_complaint_can_be_resolved_and_closed(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->acknowledge($complaint);
        $complaint = $this->service->startProgress($complaint);

        $complaint = $this->service->resolve(
            $complaint,
            'Customer account was corrected and customer notified.'
        );

        $this->assertEquals('RESOLVED', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);

        $this->assertEquals(
            'Customer account was corrected and customer notified.',
            $complaint->resolution_summary
        );

        $complaint = $this->service->close($complaint);

        $this->assertEquals('CLOSED', $complaint->status);
        $this->assertNotNull($complaint->closed_at);
    }

    public function test_open_complaint_cannot_be_closed_directly(): void
    {
        $complaint = $this->makeComplaint();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Complaint {$complaint->complaint_no} cannot transition from OPEN."
        );

        $this->service->close($complaint);
    }

    public function test_closed_complaint_cannot_be_escalated(): void
    {
        $complaint = $this->makeComplaint();

        $complaint = $this->service->acknowledge($complaint);

        $complaint = $this->service->resolve(
            $complaint,
            'Complaint resolved.'
        );

        $complaint = $this->service->close($complaint);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Resolved or closed complaint cannot be escalated.'
        );

        $this->service->escalate(
            $complaint,
            'Invalid escalation.'
        );
    }

    public function test_closed_complaint_cannot_be_reassigned(): void
    {
        $complaint = $this->makeComplaint();
        $officer = User::factory()->create();

        $complaint = $this->service->acknowledge($complaint);

        $complaint = $this->service->resolve(
            $complaint,
            'Complaint resolved.'
        );

        $complaint = $this->service->close($complaint);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Resolved or closed complaint cannot be reassigned.'
        );

        $this->service->assign(
            $complaint,
            $officer->id
        );
    }
}
