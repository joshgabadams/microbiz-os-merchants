<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentTerminal;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentOperationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentOperationGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAgent(array $overrides = []): Agent
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-' . uniqid(),
            'office_id' => 1,
        ]);

        $user = User::factory()->create();

        return Agent::create(array_merge([
            'agent_code' => 'AGT-' . uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $user->id,
        ], $overrides));
    }

    protected function attachActiveAgreement(Agent $agent): void
    {
        $creator = User::factory()->create();

        $agent->agreements()->create([
            'agreement_number' => 'AGR-' . uniqid(),
            'version' => 1,
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
        ]);
    }

    protected function makeActiveTerminal(
        Agent $agent,
        ?\DateTimeInterface $lastHeartbeatAt = null
    ): AgentTerminal {
        $user = User::factory()->create();

        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-' . uniqid(),
            'address_line_1' => '1 Test Street',
            'address_line_2' => null,
            'landmark' => null,
            'city' => 'Lagos',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'verification_evidence' => null,
            'created_by' => $user->id,
            'verified_by' => $user->id,
            'verified_at' => now(),
            'verification_notes' => 'Test location.',
        ]);

        return AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-' . uniqid(),
            'serial_number' => 'SERIAL-' . uniqid(),
            'device_model' => 'Test Terminal',
            'provider' => 'TEST',
            'application_version' => '1.0.0',
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244,
            'registered_longitude' => 3.3792,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
            'last_heartbeat_at' => $lastHeartbeatAt,
            'geo_fence_compliant' => true,
            'assigned_by' => $user->id,
        ]);
    }

    public function test_agent_active_check_passes_for_active_agent(): void
    {
        $agent = $this->makeAgent();

        app(AgentOperationGuard::class)->checkAgentActive($agent);

        $this->assertTrue(true);
    }

    public function test_agent_active_check_fails_for_draft_agent(): void
    {
        $agent = $this->makeAgent([
            'status' => AgentStatus::DRAFT->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not ACTIVE');

        app(AgentOperationGuard::class)->checkAgentActive($agent);
    }

    public function test_agreement_active_check_fails_with_no_agreement(): void
    {
        $agent = $this->makeAgent();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no active agreement');

        app(AgentOperationGuard::class)->checkAgreementActive($agent);
    }

    public function test_agreement_active_check_passes_with_active_agreement(): void
    {
        $agent = $this->makeAgent();

        $this->attachActiveAgreement($agent);

        app(AgentOperationGuard::class)->checkAgreementActive($agent);

        $this->assertTrue(true);
    }

    public function test_agreement_active_check_fails_with_only_superseded_agreement(): void
    {
        $agent = $this->makeAgent();
        $creator = User::factory()->create();

        $agent->agreements()->create([
            'agreement_number' => 'AGR-' . uniqid(),
            'version' => 1,
            'status' => 'SUPERSEDED',
            'created_by' => $creator->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no active agreement');

        app(AgentOperationGuard::class)->checkAgreementActive($agent);
    }

    public function test_kyc_valid_check_fails_when_not_completed(): void
    {
        $agent = $this->makeAgent([
            'kyc_status' => 'PENDING',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KYC is not completed');

        app(AgentOperationGuard::class)->checkKycValid($agent);
    }

    public function test_suspended_agent_is_rejected(): void
    {
        $agent = $this->makeAgent([
            'status' => AgentStatus::SUSPENDED->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot transact');

        app(AgentOperationGuard::class)
            ->checkNotSuspendedOrRestricted($agent);
    }

    public function test_restricted_agent_is_rejected(): void
    {
        $agent = $this->makeAgent([
            'status' => AgentStatus::RESTRICTED->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot transact');

        app(AgentOperationGuard::class)
            ->checkNotSuspendedOrRestricted($agent);
    }

    public function test_transaction_limit_check_fails_when_amount_exceeds_limit(): void
    {
        $agent = $this->makeAgent([
            'single_transaction_limit' => 100000,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds agent');

        app(AgentOperationGuard::class)
            ->checkTransactionLimit($agent, 150000);
    }

    public function test_transaction_limit_check_passes_when_no_limit_set(): void
    {
        $agent = $this->makeAgent([
            'single_transaction_limit' => null,
        ]);

        app(AgentOperationGuard::class)
            ->checkTransactionLimit($agent, 999999999);

        $this->assertTrue(true);
    }

    public function test_guard_float_operation_passes_when_all_real_preconditions_met(): void
    {
        $agent = $this->makeAgent([
            'single_transaction_limit' => 500000,
        ]);

        $this->attachActiveAgreement($agent);

        app(AgentOperationGuard::class)
            ->guardFloatOperation($agent, 100000);

        $this->assertTrue(true);
    }

    public function test_guard_float_operation_fails_when_agreement_missing_even_if_agent_active(): void
    {
        $agent = $this->makeAgent();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no active agreement');

        app(AgentOperationGuard::class)
            ->guardFloatOperation($agent, 50000);
    }

    public function test_guard_float_operation_fails_for_suspended_agent_even_with_valid_agreement(): void
    {
        $agent = $this->makeAgent([
            'status' => AgentStatus::SUSPENDED->value,
        ]);

        $this->attachActiveAgreement($agent);

        $this->expectException(\Exception::class);

        app(AgentOperationGuard::class)
            ->guardFloatOperation($agent, 50000);
    }

    public function test_terminal_heartbeat_check_fails_when_heartbeat_missing(): void
    {
        $agent = $this->makeAgent();

        $terminal = $this->makeActiveTerminal(
            $agent,
            null
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Terminal {$terminal->terminal_id} has not sent a heartbeat."
        );

        app(AgentOperationGuard::class)
            ->checkTerminalHeartbeatFresh($terminal);
    }

    public function test_terminal_heartbeat_check_fails_when_heartbeat_is_stale(): void
    {
        config([
            'agency.terminal.heartbeat_timeout_seconds' => 300,
        ]);

        $agent = $this->makeAgent();

        $terminal = $this->makeActiveTerminal(
            $agent,
            now()->subSeconds(301)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Terminal {$terminal->terminal_id} heartbeat is stale."
        );

        app(AgentOperationGuard::class)
            ->checkTerminalHeartbeatFresh($terminal);
    }

    public function test_terminal_heartbeat_check_passes_when_heartbeat_is_fresh(): void
    {
        config([
            'agency.terminal.heartbeat_timeout_seconds' => 300,
        ]);

        $agent = $this->makeAgent();

        $terminal = $this->makeActiveTerminal(
            $agent,
            now()->subSeconds(60)
        );

        app(AgentOperationGuard::class)
            ->checkTerminalHeartbeatFresh($terminal);

        $this->assertTrue(true);
    }
}
