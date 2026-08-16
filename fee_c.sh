#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > tests/Feature/AgentFeeCalculationTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\User;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentFeeCalculationService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Payments\AgentTransferService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers Blueprint Module 14's fee disclosure requirement and AG-10's
 * commission crediting -- deliberately scoped to calculation,
 * disclosure, and commission crediting only. Fee collection from the
 * customer is NOT covered here; see AgentFeeCalculationService's
 * class-level documentation for why.
 */
class AgentFeeCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(array $agentOverrides = []): array
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
            'single_transaction_limit' => 1000000,
        ], $agentOverrides));

        $agreementCreator = User::factory()->create();
        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'ACTIVE',
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
        ]);

        return ['agent' => $agent, 'location' => $location, 'operator' => $operator, 'terminal' => $terminal];
    }

    protected function makeCustomerAccount(float $openingBalance = 0): CustomerAccount
    {
        $customer = Customer::create(['customer_no' => 'CUS-'.uniqid(), 'first_name' => 'Jane', 'last_name' => 'Doe']);

        $account = CustomerAccount::create(['customer_id' => $customer->id, 'account_no' => 'ACC-'.uniqid(), 'status' => 'ACTIVE']);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $account;
    }

    public function test_calculate_fee_returns_zero_when_not_configured(): void
    {
        $context = $this->makeReadyAgentContext();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService($context['agent'], 'CASH_IN', $enabler->id);

        $fee = app(AgentFeeCalculationService::class)->calculateFee($context['agent'], 'CASH_IN');

        $this->assertEquals(0.0, $fee);
    }

    public function test_calculate_fee_and_commission_return_configured_values(): void
    {
        $context = $this->makeReadyAgentContext();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'CASH_IN', $enabler->id, null, 100, true, null, null, 40
        );

        $preview = app(AgentFeeCalculationService::class)->previewFee($context['agent'], 'CASH_IN');

        $this->assertEquals(100.0, $preview['fee_amount']);
        $this->assertEquals(40.0, $preview['commission_amount']);
    }

    public function test_cash_in_credits_pending_commission_when_configured(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeCustomerAccount();
        $user = User::factory()->create();
        $enabler = User::factory()->create();

        $vault = \App\Models\Vault::create([
            'branch_id' => Branch::create(['name' => 'VB', 'code' => 'VB-'.uniqid(), 'office_id' => 1])->id,
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        app(VaultTransactionService::class)->deposit($vault, 300000, $user->id);
        app(AgentFloatService::class)->allocateFloat($vault, $context['agent'], 200000, $user->id);

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'CASH_IN', $enabler->id, null, 100, true, null, null, 30
        );

        $result = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals(100.0, (float) $result->fee_amount);
        $this->assertEquals(30.0, (float) $result->commission_amount);

        $balance = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(30.0, (float) $balance->pending_commission);
    }

    public function test_transfer_creates_agent_balance_and_credits_commission_even_without_prior_float_activity(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'TRANSFER', $enabler->id, null, 50, true, null, null, 15
        );

        $this->assertEquals(0, AgentBalance::where('agent_id', $context['agent']->id)->count());

        $result = app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals(50.0, (float) $result->fee_amount);
        $this->assertEquals(15.0, (float) $result->commission_amount);

        $balance = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertNotNull($balance, 'A transfer with configured commission should create an AgentBalance row even for an agent who never did cash-in/float allocation.');
        $this->assertEquals(15.0, (float) $balance->pending_commission);
    }

    public function test_transfer_does_not_create_agent_balance_when_no_commission_configured(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService($context['agent'], 'TRANSFER', $enabler->id);

        app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals(0, AgentBalance::where('agent_id', $context['agent']->id)->count(), 'No AgentBalance row should be created for a transfer with zero commission -- avoids unnecessary rows for agents who only ever transfer.');
    }
}
MBOS_EOF

echo "Fee Part C applied (tests). Next: php artisan migrate --path=database/migrations/2026_08_12_000003_add_commission_amount_to_agent_services_table.php && php artisan test"
