<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLocationTest extends TestCase
{
    use RefreshDatabase;

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

    protected function locationData(): array
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

    public function test_location_can_be_created_for_agent_pending_location_verification(): void
    {
        $registrant = User::factory()->create();

        $agent = $this->makeAgentPendingLocation($registrant->id);

        $location = app(AgentLocationService::class)->createLocation(
            $agent,
            $this->locationData(),
            $registrant->id
        );

        $this->assertEquals($agent->id, $location->agent_id);
        $this->assertEquals('PENDING', $location->verification_status);
        $this->assertEquals('PENDING', $location->status);
        $this->assertNotNull($location->location_code);
    }

    public function test_location_cannot_be_created_for_agent_in_wrong_state(): void
    {
        $user = User::factory()->create();

        $agent = $this->makeAgentPendingLocation($user->id);

        $agent->update([
            'status' => AgentStatus::PENDING_COMPLIANCE_REVIEW->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not pending location verification');

        app(AgentLocationService::class)->createLocation(
            $agent->fresh(),
            $this->locationData(),
            $user->id
        );
    }

    public function test_location_creator_cannot_verify_their_own_location(): void
    {
        $creator = User::factory()->create();

        $agent = $this->makeAgentPendingLocation($creator->id);

        $service = app(AgentLocationService::class);

        $location = $service->createLocation(
            $agent,
            $this->locationData(),
            $creator->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'officer who created the location cannot verify it'
        );

        $service->verifyLocation(
            $agent->fresh(),
            $location,
            $creator->id
        );
    }

    public function test_independent_verification_advances_agent_to_compliance_review(): void
    {
        $creator = User::factory()->create();
        $verifier = User::factory()->create();

        $agent = $this->makeAgentPendingLocation($creator->id);

        $service = app(AgentLocationService::class);

        $location = $service->createLocation(
            $agent,
            $this->locationData(),
            $creator->id
        );

        $result = $service->verifyLocation(
            $agent->fresh(),
            $location,
            $verifier->id,
            'Physical premises and GPS confirmed.'
        );

        $this->assertEquals(
            AgentStatus::PENDING_COMPLIANCE_REVIEW->value,
            $result->status
        );

        $this->assertDatabaseHas('agent_locations', [
            'id' => $location->id,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'verified_by' => $verifier->id,
        ]);
    }

    public function test_rejected_location_does_not_advance_agent(): void
    {
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();

        $agent = $this->makeAgentPendingLocation($creator->id);

        $service = app(AgentLocationService::class);

        $location = $service->createLocation(
            $agent,
            $this->locationData(),
            $creator->id
        );

        $result = $service->rejectLocation(
            $agent->fresh(),
            $location,
            $reviewer->id,
            'GPS position does not match physical premises.'
        );

        $this->assertEquals(
            'REJECTED',
            $result->verification_status
        );

        $this->assertEquals(
            AgentStatus::PENDING_LOCATION_VERIFICATION->value,
            $agent->fresh()->status
        );
    }

    public function test_location_belonging_to_another_agent_cannot_be_verified(): void
    {
        $creator = User::factory()->create();
        $verifier = User::factory()->create();

        $agentOne = $this->makeAgentPendingLocation($creator->id);
        $agentTwo = $this->makeAgentPendingLocation($creator->id);

        $service = app(AgentLocationService::class);

        $location = $service->createLocation(
            $agentOne,
            $this->locationData(),
            $creator->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'location does not belong to this agent'
        );

        $service->verifyLocation(
            $agentTwo,
            $location,
            $verifier->id
        );
    }
}
