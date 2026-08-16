<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\User;
use App\Models\Vault;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentOperationGuard;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers two real production-readiness gaps closed ahead of the AG-10
 * commission sprint, given the deadline: Blueprint Module 7's explicit
 * per-agent service enablement requirement ("an agent must not
 * automatically receive all service permissions merely because the
 * agent has been activated"), and daily cumulative limit enforcement
 * (deferred since AG-07 pending real agent_transactions volume, which
 * now genuinely exists).
 */
class AgentServiceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeActiveAgent(array $overrides = []): Agent
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);
        $registrant = User::factory()->create();

        $agent = Agent::create(array_merge([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $registrant->id,
        ], $overrides));

        $creator = User::factory()->create();
        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'EXECUTED',
            'created_by' => $creator->id,
        ]);

        return $agent;
    }

    public function test_check_service_enabled_fails_when_never_configured(): void
    {
        $agent = $this->makeActiveAgent();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not have CASH_IN enabled');

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_IN');
    }

    public function test_check_service_enabled_passes_once_enabled(): void
    {
        $agent = $this->makeActiveAgent();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService($agent, 'CASH_IN', $enabler->id);

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_IN');

        $this->assertTrue(true);
    }

    public function test_check_service_enabled_fails_once_disabled(): void
    {
        $agent = $this->makeActiveAgent();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService($agent, 'CASH_IN', $enabler->id);
        app(AgentServiceConfigurationService::class)->disableService($agent, 'CASH_IN');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not have CASH_IN enabled');

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_IN');
    }

    public function test_check_service_enabled_fails_before_effective_date(): void
    {
        $agent = $this->makeActiveAgent();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $agent, 'CASH_IN', $enabler->id, null, null, true, now()->addDays(3)->toDateString()
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not yet effective');

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_IN');
    }

    public function test_check_service_enabled_fails_after_expiry_date(): void
    {
        $agent = $this->makeActiveAgent();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $agent, 'CASH_IN', $enabler->id, null, null, true, null, now()->subDay()->toDateString()
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has expired');

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_IN');
    }

    public function test_enabling_one_service_does_not_enable_another(): void
    {
        $agent = $this->makeActiveAgent();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService($agent, 'CASH_IN', $enabler->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not have CASH_OUT enabled');

        app(AgentOperationGuard::class)->checkServiceEnabled($agent, 'CASH_OUT');
    }

    public function test_daily_cumulative_limit_passes_with_no_limit_configured(): void
    {
        $agent = $this->makeActiveAgent(['daily_transaction_limit' => null]);

        app(AgentOperationGuard::class)->checkDailyCumulativeLimit($agent, 999999999);

        $this->assertTrue(true);
    }

    public function test_daily_cumulative_limit_passes_when_within_limit(): void
    {
        $agent = $this->makeActiveAgent(['daily_transaction_limit' => 100000]);

        app(AgentOperationGuard::class)->checkDailyCumulativeLimit($agent, 50000);

        $this->assertTrue(true);
    }

    protected function makeMinimalTransactionContext(Agent $agent): array
    {
        $locationCreator = User::factory()->create();
        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Test Street',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $locationCreator->id,
        ]);

        $operatorUser = User::factory()->create();
        $operator = AgentOperator::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'user_id' => $operatorUser->id,
            'role' => 'TILL_OPERATOR',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        $terminal = AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-'.uniqid(),
            'serial_number' => 'SN-'.uniqid(),
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        return ['location' => $location, 'operator' => $operator, 'terminal' => $terminal];
    }

    public function test_daily_cumulative_limit_fails_when_todays_total_would_be_exceeded(): void
    {
        $agent = $this->makeActiveAgent(['daily_transaction_limit' => 100000]);
        $context = $this->makeMinimalTransactionContext($agent);
        $performer = User::factory()->create();

        AgentTransaction::create([
            'transaction_no' => 'AGT-'.uniqid(),
            'idempotency_key' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'agent_location_id' => $context['location']->id,
            'agent_terminal_id' => $context['terminal']->id,
            'agent_operator_id' => $context['operator']->id,
            'transaction_type' => 'CASH_IN',
            'status' => 'COMPLETED',
            'amount' => 80000,
            'currency' => 'NGN',
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'geo_fence_passed' => true,
            'transaction_date' => now(),
            'performed_by' => $performer->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('daily cumulative limit');

        app(AgentOperationGuard::class)->checkDailyCumulativeLimit($agent, 30000);
    }

    public function test_daily_cumulative_limit_ignores_non_completed_transactions(): void
    {
        $agent = $this->makeActiveAgent(['daily_transaction_limit' => 100000]);
        $context = $this->makeMinimalTransactionContext($agent);
        $performer = User::factory()->create();

        AgentTransaction::create([
            'transaction_no' => 'AGT-'.uniqid(),
            'idempotency_key' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'agent_location_id' => $context['location']->id,
            'agent_terminal_id' => $context['terminal']->id,
            'agent_operator_id' => $context['operator']->id,
            'transaction_type' => 'INTERBANK_TRANSFER',
            'status' => 'FAILED',
            'amount' => 90000,
            'currency' => 'NGN',
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'geo_fence_passed' => true,
            'transaction_date' => now(),
            'performed_by' => $performer->id,
        ]);

        app(AgentOperationGuard::class)->checkDailyCumulativeLimit($agent, 50000);

        $this->assertTrue(true);
    }

    /**
     * Real end-to-end integration test: confirms a genuinely active
     * agent -- fully KYC'd, agreement executed, location/operator/
     * terminal all active, float funded -- is still correctly blocked
     * from cash-in until CASH_IN is explicitly enabled. Before today,
     * this agent would have been able to transact freely.
     */

public function test_cash_in_is_blocked_end_to_end_without_service_enabled(): void
{
    $branch = Branch::create([
        'name' => 'Test Branch',
        'code' => 'TB-'.uniqid(),
        'office_id' => 1,
    ]);

    $registrant = User::factory()->create();

    app(BranchBusinessDayService::class)->open(
        $branch->id,
        now()->toDateString(),
        $registrant->id,
        'Opened for agent service configuration test.'
    );

    $agent = Agent::create([
        'agent_code' => 'AGT-'.uniqid(),
        'agent_type' => 'INDIVIDUAL',
        'legal_name' => 'Test Agent',
        'phone' => '08000000000',
        'branch_id' => $branch->id,
        'status' => AgentStatus::ACTIVE->value,
        'kyc_status' => 'COMPLETED',
        'created_by' => $registrant->id,
        'single_transaction_limit' => 1000000,
    ]);
        $agreementCreator = User::factory()->create();
        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'EXECUTED',
            'created_by' => $agreementCreator->id,
        ]);

        $locationCreator = User::factory()->create();
        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Test Street',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $locationCreator->id,
        ]);

        $operatorUser = User::factory()->create();
        $operator = AgentOperator::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'user_id' => $operatorUser->id,
            'role' => 'TILL_OPERATOR',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        $terminal = AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-'.uniqid(),
            'serial_number' => 'SN-'.uniqid(),
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        $vaultBranch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
        $vault = Vault::create([
            'branch_id' => $vaultBranch->id,
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        $vaultFunder = User::factory()->create();
        app(VaultTransactionService::class)->deposit($vault, 500000, $vaultFunder->id);
        app(AgentFloatService::class)->allocateFloat($vault, $agent, 200000, $vaultFunder->id);

        // Deliberately NOT enabling CASH_IN here.

        $customer = Customer::create(['customer_no' => 'CUS-'.uniqid(), 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $customerAccount = CustomerAccount::create(['customer_id' => $customer->id, 'account_no' => 'ACC-'.uniqid(), 'status' => 'ACTIVE']);
        CustomerAccountBalance::create(['customer_account_id' => $customerAccount->id, 'currency' => 'NGN', 'ledger_balance' => 0, 'available_balance' => 0]);

        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not have CASH_IN enabled');

        app(AgentCashInService::class)->cashIn(
            $operator,
            $terminal,
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }
}
