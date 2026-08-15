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
use App\Models\Vault;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentCashOutService;
use App\Services\Payments\AgentReconciliationService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Payments\AgentTransactionReversalService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(
        float $floatAmount = 300000,
        array $servicesToEnable = ['CASH_IN', 'CASH_OUT']
    ): array {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        $registrant = User::factory()->create();

        app(BranchBusinessDayService::class)->open(
            $branch->id,
            now()->toDateString(),
            $registrant->id
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
            'last_heartbeat_at' => now(),
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
        ]);

        $vaultBranch = Branch::create([
            'name' => 'Vault Branch',
            'code' => 'VB-'.uniqid(),
            'office_id' => 1,
        ]);

        $vault = Vault::create([
            'branch_id' => $vaultBranch->id,
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        $vaultFunder = User::factory()->create();
        app(VaultTransactionService::class)->deposit($vault, $floatAmount + 100000, $vaultFunder->id);
        app(AgentFloatService::class)->allocateFloat($vault, $agent, $floatAmount, $vaultFunder->id);

        $serviceEnabler = User::factory()->create();
        foreach ($servicesToEnable as $serviceType) {
            app(AgentServiceConfigurationService::class)->enableService($agent, $serviceType, $serviceEnabler->id);
        }

        return ['agent' => $agent, 'branch' => $branch, 'location' => $location, 'operator' => $operator, 'terminal' => $terminal];
    }

    protected function makeActiveCustomerAccount(float $openingBalance = 0): CustomerAccount
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

    protected function performCashIn(array $context, CustomerAccount $customerAccount, float $amount = 50000)
    {
        $performedBy = User::factory()->create();

        return app(AgentCashInService::class)->cashIn(
            $context['operator'], $context['terminal'], $customerAccount, $amount,
            (string) Str::uuid(), 6.5244000, 3.3792000, $performedBy->id
        );
    }

    protected function performCashOut(array $context, CustomerAccount $customerAccount, float $amount = 20000)
    {
        $performedBy = User::factory()->create();

        return app(AgentCashOutService::class)->cashOut(
            $context['operator'], $context['terminal'], $customerAccount, $amount,
            (string) Str::uuid(), 6.5244000, 3.3792000, $performedBy->id, true
        );
    }

    public function test_agent_can_be_reconciled_when_cash_matches(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        // Real cash-in correctly increases declared_physical_cash to
        // exactly match transaction history -- should reconcile clean.
        $this->performCashIn($context, $customerAccount, 50000);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        $this->assertSame('MATCHED', $reconciliation->status);
        $this->assertEquals(0, (float) $reconciliation->variance);
    }

    public function test_reconciliation_records_shortage_as_mismatch(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        $this->performCashIn($context, $customerAccount, 50000);

        // Simulate a real shortage -- less physical cash on hand than
        // transaction history says there should be.
        AgentBalance::where('agent_id', $context['agent']->id)->update([
            'declared_physical_cash' => 40000,
        ]);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        $this->assertSame('MISMATCHED', $reconciliation->status);
        $this->assertEquals(-10000, (float) $reconciliation->variance);
    }

    public function test_reconciliation_records_overage_as_mismatch(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        $this->performCashIn($context, $customerAccount, 50000);

        AgentBalance::where('agent_id', $context['agent']->id)->update([
            'declared_physical_cash' => 65000,
        ]);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        $this->assertSame('MISMATCHED', $reconciliation->status);
        $this->assertEquals(15000, (float) $reconciliation->variance);
    }

    public function test_reconciliation_correctly_nets_cash_in_and_cash_out(): void
    {
        $context = $this->makeReadyAgentContext();
        $fundingCustomer = $this->makeActiveCustomerAccount();
        $withdrawingCustomer = $this->makeActiveCustomerAccount(80000);

        $this->performCashIn($context, $fundingCustomer, 100000);
        $this->performCashOut($context, $withdrawingCustomer, 30000);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        $this->assertSame('MATCHED', $reconciliation->status);
        $this->assertEquals(70000, (float) $reconciliation->system_expected_physical_cash);
    }

    public function test_reconciliation_excludes_reversed_cash_in_from_expected_cash(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $checker = User::factory()->create();
        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        // A fully reversed cash-in should net to zero expected physical
        // cash -- confirming the reversal's status flip is correctly
        // picked up by the COMPLETED filter, with no double-counting.
        $this->assertEquals(0, (float) $reconciliation->system_expected_physical_cash);
        $this->assertSame('MATCHED', $reconciliation->status);
    }

    public function test_reconciliation_fails_when_agent_has_no_balance_record(): void
    {
        $branch = Branch::create(['name' => 'Empty Branch', 'code' => 'EB-'.uniqid(), 'office_id' => 1]);
        $registrant = User::factory()->create();

        $agent = Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'No Balance Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $registrant->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no float balance to reconcile');

        app(AgentReconciliationService::class)->reconcileAgent($agent);
    }

    public function test_reconciliation_records_the_agents_branch(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        $this->performCashIn($context, $customerAccount, 50000);

        $reconciliation = app(AgentReconciliationService::class)->reconcileAgent(
            $context['agent']->fresh()
        );

        $this->assertEquals($context['branch']->id, $reconciliation->branch_id);
    }

    public function test_agent_can_be_reconciled_multiple_times_reflecting_current_state(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();

        $this->performCashIn($context, $customerAccount, 50000);

        $first = app(AgentReconciliationService::class)->reconcileAgent($context['agent']->fresh());
        $this->assertSame('MATCHED', $first->status);

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 25000);

        $second = app(AgentReconciliationService::class)->reconcileAgent($context['agent']->fresh());
        $this->assertSame('MATCHED', $second->status);
        $this->assertEquals(75000, (float) $second->system_expected_physical_cash);
        $this->assertNotEquals($first->id, $second->id, 'Each reconciliation is a point-in-time record, not a single mutable row.');
    }
}
