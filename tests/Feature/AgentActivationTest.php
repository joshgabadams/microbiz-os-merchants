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
use App\Models\TrainingDocument;
use App\Models\User;
use App\Services\Payments\AgentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentActivationTest extends TestCase
{
    use RefreshDatabase;

    protected AgentActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgentActivationService::class);
    }

    protected function makeAgent(
        string $status = AgentStatus::TERMINAL_PENDING->value
    ): Agent {
        $creator = User::factory()->create();

        $branch = Branch::create([
            'name' => 'Activation Test Branch',
            'code' => 'ATB-'.uniqid(),
            'office_id' => 1,
        ]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Activation Test Agent',
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
            'status' => 'ACTIVE',
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
        $creator = User::factory()->create();
        $verifier = User::factory()->create();

        return AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Activation Test Street',
            'city' => 'Lagos',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 70,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'verified_by' => $verifier->id,
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

    public function test_approved_agent_cannot_bypass_onboarding_and_activate(): void
    {
        $agent = $this->makeAgent(
            AgentStatus::APPROVED->value
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'must be TERMINAL_PENDING'
        );

        $this->service->activate($agent);
    }

    public function test_training_pending_agent_cannot_activate(): void
    {
        $agent = $this->makeAgent(
            AgentStatus::TRAINING_PENDING->value
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'must be TERMINAL_PENDING'
        );

        $this->service->activate($agent);
    }

    public function test_agent_cannot_activate_without_active_agreement(): void
    {
        $agent = $this->makeAgent();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has no active agreement'
        );

        $this->service->activate($agent);
    }

    public function test_agent_cannot_activate_without_acknowledged_training(): void
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has not completed mandatory training'
        );

        $this->service->activate($agent);
    }

    public function test_operator_training_does_not_satisfy_agent_training_gate(): void
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);

        $location = $this->addActiveVerifiedLocation($agent);
        $operator = $this->addActiveOperator(
            $agent,
            $location
        );

        $user = User::factory()->create();

        $document = TrainingDocument::create([
            'name' => 'Operator Training Guide',
            'version' => '1.0',
            'status' => 'ACTIVE',
            'file_path' => 'training/operator-guide.pdf',
            'created_by' => $user->id,
        ]);

        AgentTrainingRecord::create([
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'training_document_id' => $document->id,
            'training_document_version' => $document->version,
            'downloaded_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'recorded_by' => $user->id,
            'completion_method' => 'document_acknowledgement',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has not completed mandatory training'
        );

        $this->service->activate($agent);
    }

    public function test_agent_cannot_activate_without_active_verified_location(): void
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);
        $this->addAcknowledgedTraining($agent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has no active verified location'
        );

        $this->service->activate($agent);
    }

    public function test_agent_cannot_activate_without_active_operator(): void
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);
        $this->addAcknowledgedTraining($agent);

        $this->addActiveVerifiedLocation($agent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has no active operator'
        );

        $this->service->activate($agent);
    }

    public function test_agent_cannot_activate_without_active_terminal(): void
    {
        $agent = $this->makeAgent();

        $this->addActiveAgreement($agent);
        $this->addAcknowledgedTraining($agent);

        $location = $this->addActiveVerifiedLocation($agent);

        $this->addActiveOperator(
            $agent,
            $location
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'has no active terminal'
        );

        $this->service->activate($agent);
    }

    public function test_fully_ready_agent_can_be_activated(): void
    {
        $agent = $this->makeFullyReadyAgent();

        $result = $this->service->activate($agent);

        $this->assertEquals(
            AgentStatus::ACTIVE->value,
            $result->status
        );

        $this->assertNotNull(
            $result->activated_at
        );

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'status' => AgentStatus::ACTIVE->value,
        ]);
    }

    public function test_active_agent_cannot_be_activated_twice(): void
    {
        $agent = $this->makeFullyReadyAgent();

        $this->service->activate($agent);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'must be TERMINAL_PENDING'
        );

        $this->service->activate(
            $agent->fresh()
        );
    }
}