<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentTransactionRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentTransactionRiskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAgent(
        string $riskRating = 'LOW'
    ): Agent {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        $creator = User::factory()->create();

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'risk_rating' => $riskRating,
            'created_by' => $creator->id,
        ]);
    }

    protected function makeTerminal(
        Agent $agent,
        bool $ipLocationMismatch = false
    ): AgentTerminal {
        $user = User::factory()->create();

        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Test Street',
            'city' => 'Lagos',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $user->id,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        return AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-'.uniqid(),
            'serial_number' => 'SERIAL-'.uniqid(),
            'device_model' => 'Test Terminal',
            'provider' => 'TEST',
            'application_version' => '1.0.0',
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
            'last_heartbeat_at' => now(),
            'geo_fence_compliant' => true,
            'ip_location_mismatch' => $ipLocationMismatch,
            'assigned_by' => $user->id,
        ]);
    }

    protected function makeTransaction(
        Agent $agent,
        AgentTerminal $terminal,
        string $status = 'COMPLETED',
        ?\DateTimeInterface $transactionDate = null
    ): AgentTransaction {
        $operatorUser = User::factory()->create();

        $operator = AgentOperator::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $terminal->agent_location_id,
            'user_id' => $operatorUser->id,
            'role' => 'TILL_OPERATOR',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        return AgentTransaction::create([
            'transaction_no' => 'TXN-'.uniqid(),
            'idempotency_key' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'agent_location_id' => $terminal->agent_location_id,
            'agent_terminal_id' => $terminal->id,
            'agent_operator_id' => $operator->id,
            'transaction_type' => 'CASH_IN',
            'status' => $status,
            'amount' => 1000,
            'fee_amount' => 0,
            'commission_amount' => 0,
            'currency' => 'NGN',
            'customer_account_id' => null,
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'geo_fence_passed' => true,
            'transaction_date' => $transactionDate ?? now(),
            'posted_at' => $status === 'COMPLETED'
                ? ($transactionDate ?? now())
                : null,
            'performed_by' => $operatorUser->id,
        ]);
    }

    public function test_low_risk_transaction_returns_low_risk_assessment(): void
    {
        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal($agent);

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'CASH_IN',
            50000
        );

        $this->assertSame(0, $assessment['score']);
        $this->assertSame('LOW', $assessment['level']);
        $this->assertSame('CASH_IN', $assessment['transaction_type']);
        $this->assertSame(50000.0, $assessment['amount']);
        $this->assertSame('LOW', $assessment['agent_risk_rating']);
    }

    public function test_high_risk_agent_contributes_risk_score(): void
    {
        $agent = $this->makeAgent('HIGH');
        $terminal = $this->makeTerminal($agent);

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'CASH_OUT',
            50000
        );

        $this->assertSame(40, $assessment['score']);
        $this->assertSame('MEDIUM', $assessment['level']);

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'AGENT_RISK_RATING');

        $this->assertTrue($rule['matched']);
        $this->assertSame(40, $rule['score']);
        $this->assertSame('HIGH', $rule['value']);
    }

    public function test_ip_location_mismatch_contributes_risk_score(): void
    {
        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal(
            $agent,
            true
        );

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'TRANSFER',
            50000
        );

        $this->assertSame(35, $assessment['score']);
        $this->assertSame('MEDIUM', $assessment['level']);

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'IP_LOCATION_MISMATCH');

        $this->assertTrue($rule['matched']);
        $this->assertSame(35, $rule['score']);
    }

    public function test_combined_risk_signals_can_produce_high_risk(): void
    {
        $agent = $this->makeAgent('HIGH');

        $terminal = $this->makeTerminal(
            $agent,
            true
        );

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'CASH_OUT',
            50000
        );

        $this->assertSame(75, $assessment['score']);
        $this->assertSame('HIGH', $assessment['level']);
    }

    public function test_completed_transactions_at_velocity_threshold_trigger_rule(): void
    {
        config([
            'agency.risk.velocity_window_minutes' => 10,
            'agency.risk.velocity_transaction_threshold' => 3,
        ]);

        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal($agent);

        for ($i = 0; $i < 3; $i++) {
            $this->makeTransaction(
                $agent,
                $terminal,
                'COMPLETED',
                now()->subMinutes(2)
            );
        }

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'CASH_IN',
            50000
        );

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'TRANSACTION_VELOCITY');

        $this->assertTrue($rule['matched']);
        $this->assertSame(3, $rule['transaction_count']);
        $this->assertSame(30, $rule['score']);
        $this->assertSame(30, $assessment['score']);
        $this->assertSame('MEDIUM', $assessment['level']);
    }

    public function test_completed_transactions_outside_velocity_window_do_not_count(): void
    {
        config([
            'agency.risk.velocity_window_minutes' => 10,
            'agency.risk.velocity_transaction_threshold' => 1,
        ]);

        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal($agent);

        $this->makeTransaction(
            $agent,
            $terminal,
            'COMPLETED',
            now()->subMinutes(11)
        );

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'CASH_IN',
            50000
        );

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'TRANSACTION_VELOCITY');

        $this->assertFalse($rule['matched']);
        $this->assertSame(0, $rule['transaction_count']);
        $this->assertSame(0, $rule['score']);
        $this->assertSame('LOW', $assessment['level']);
    }

    public function test_failed_transactions_do_not_count_toward_velocity(): void
    {
        config([
            'agency.risk.velocity_window_minutes' => 10,
            'agency.risk.velocity_transaction_threshold' => 1,
        ]);

        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal($agent);

        $this->makeTransaction(
            $agent,
            $terminal,
            'FAILED',
            now()->subMinutes(2)
        );

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'INTERBANK_TRANSFER',
            50000
        );

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'TRANSACTION_VELOCITY');

        $this->assertFalse($rule['matched']);
        $this->assertSame(0, $rule['transaction_count']);
        $this->assertSame(0, $rule['score']);
    }

    public function test_initiated_transactions_do_not_count_toward_velocity(): void
    {
        config([
            'agency.risk.velocity_window_minutes' => 10,
            'agency.risk.velocity_transaction_threshold' => 1,
        ]);

        $agent = $this->makeAgent('LOW');
        $terminal = $this->makeTerminal($agent);

        $this->makeTransaction(
            $agent,
            $terminal,
            'INITIATED',
            now()->subMinutes(2)
        );

        $assessment = app(AgentTransactionRiskService::class)->assess(
            $agent,
            $terminal,
            'TRANSFER',
            50000
        );

        $rule = collect($assessment['rules'])
            ->firstWhere('rule', 'TRANSACTION_VELOCITY');

        $this->assertFalse($rule['matched']);
        $this->assertSame(0, $rule['transaction_count']);
        $this->assertSame(0, $rule['score']);
    }
}
