<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Services\Branch\BranchBusinessDayService;
use App\Models\AgentBalance;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\User;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentCashOutService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers AG-08 (Cash-Out) against Blueprint §20's exact automated-test
 * list: valid cash-out succeeds, insufficient customer balance
 * rejected, insufficient agent liquidity rejected, limit breach
 * rejected, invalid customer authentication rejected, customer account
 * debited correctly, agent float updated correctly.
 *
 * Physical cash is funded via a REAL cash-in first (not a shortcut
 * fixture), proving the full real economic cycle: vault funds agent
 * float -> cash-in converts float to physical cash + credits a
 * customer -> cash-out converts physical cash back to float + debits
 * a customer. This also directly proves the retroactive AG-07 fix
 * (declared_physical_cash now actually increases on cash-in).
 */
class AgentCashOutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeActiveCustomerAccount(float $openingBalance = 0): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $account;
    }

    /**
     * Builds a fully active agent context and, unless $physicalCash is
     * 0, funds real declared_physical_cash via an actual cash-in
     * transaction -- not a fixture shortcut.
     */
    protected function makeReadyAgentContext(float $physicalCash = 100000, array $agentOverrides = []): array
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);
        $registrant = User::factory()->create();

        app(BranchBusinessDayService::class)->open(
    $branch->id,
    now()->toDateString(),
    $registrant->id
);

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
            'last_heartbeat_at' => now(),
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
        ]);

        $serviceEnabler = User::factory()->create();
        app(\App\Services\Payments\AgentServiceConfigurationService::class)->enableService(
            $agent, 'CASH_IN', $serviceEnabler->id
        );
        app(\App\Services\Payments\AgentServiceConfigurationService::class)->enableService(
            $agent, 'CASH_OUT', $serviceEnabler->id
        );

        if ($physicalCash > 0) {
            $vaultBranch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
            $vault = \App\Models\Vault::create([
                'branch_id' => $vaultBranch->id,
                'code' => 'VLT-'.uniqid(),
                'name' => 'Test Vault',
                'active' => true,
            ]);

            $vaultFunder = User::factory()->create();
            app(VaultTransactionService::class)->deposit($vault, $physicalCash + 100000, $vaultFunder->id);
            app(AgentFloatService::class)->allocateFloat($vault, $agent, $physicalCash, $vaultFunder->id);

            // Real cash-in to naturally fund declared_physical_cash --
            // proves the AG-07 retroactive fix, not a fixture shortcut.
            $fundingCustomer = $this->makeActiveCustomerAccount();
            $cashInUser = User::factory()->create();
            app(AgentCashInService::class)->cashIn(
                $operator,
                $terminal,
                $fundingCustomer,
                $physicalCash,
                (string) Str::uuid(),
                6.5244000,
                3.3792000,
                $cashInUser->id
            );
        }

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
    }

    public function test_valid_cash_out_succeeds(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();

        $result = app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $this->assertEquals('COMPLETED', $result->status);
        $this->assertEquals('CASH_OUT', $result->transaction_type);
    }

    public function test_cash_out_rejected_for_insufficient_customer_balance(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(10000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient');

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );
    }

    public function test_cash_out_rejected_for_insufficient_agent_physical_liquidity(): void
    {
        $context = $this->makeReadyAgentContext(10000);
        $customerAccount = $this->makeActiveCustomerAccount(200000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('insufficient physical cash liquidity');

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );
    }

    public function test_cash_out_rejected_for_limit_breach(): void
    {
        $context = $this->makeReadyAgentContext(500000);
        $context['agent']->update(['single_transaction_limit' => 20000]);
        $freshOperator = \App\Models\AgentOperator::find($context['operator']->id);
        $customerAccount = $this->makeActiveCustomerAccount(200000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds agent');

        app(AgentCashOutService::class)->cashOut(
            $freshOperator,
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );
    }

    public function test_cash_out_rejected_without_customer_authentication(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('authentication is required');

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            false
        );
    }

    public function test_customer_account_debited_correctly(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $balance = CustomerAccountBalance::where('customer_account_id', $customerAccount->id)->first();
        $this->assertEquals(30000, (float) $balance->available_balance);
    }

    public function test_agent_float_and_physical_cash_updated_correctly(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();

        $balanceBefore = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(100000, (float) $balanceBefore->declared_physical_cash, 'AG-07 fix: cash-in should have already funded physical cash.');

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $balanceAfter = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(50000, (float) $balanceAfter->available_float, 'Cash-out credits agent float per Blueprint §13.3.');
        $this->assertEquals(50000, (float) $balanceAfter->declared_physical_cash, 'Cash-out decreases physical cash -- it leaves the agent\'s hand.');
    }

    public function test_gl_posting_balances(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();

        $result = app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $journalEntries = GlJournal::where('reference', $result->transaction_no)->get();
        $this->assertCount(2, $journalEntries, 'Cash-out should post exactly 2 balanced GlJournal rows.');
    }

    public function test_duplicate_idempotency_key_returns_original_response(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $first = app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $second = app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AgentTransaction::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_reused_idempotency_key_with_different_customer_account_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext(100000);
        $firstAccount = $this->makeActiveCustomerAccount(80000);
        $secondAccount = $this->makeActiveCustomerAccount(80000);
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $firstAccount,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id,
            true
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Idempotency key');

        app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $secondAccount,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id,
            true
        );
    }
}
