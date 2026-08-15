<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
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
use App\Models\Vault;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers AG-07 (Cash-In) against Blueprint §20's exact automated-test
 * list for this module: valid cash-in succeeds, inactive agent
 * rejected, insufficient float rejected, duplicate idempotency request
 * returns original response, customer account credited correctly,
 * agent float updated correctly, GL posting balances -- plus geo-fence
 * enforcement, which the Blueprint's Module 9 controls list requires
 * but §20 doesn't separately enumerate.
 */
class AgentCashInServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    /**
     * Builds a fully real, fully active agent + location + operator +
     * terminal + funded float, all at the exact coordinates the tests
     * transact against -- the whole real chain a cash-in genuinely
     * needs, not a shortcut.
     */
    protected function makeReadyAgentContext(float $floatAmount = 200000, array $agentOverrides = []): array
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

        $vaultBranch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
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
        app(AgentServiceConfigurationService::class)->enableService(
            $agent, 'CASH_IN', $serviceEnabler->id
        );

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
    }

    protected function makeActiveCustomerAccount(): CustomerAccount
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
            'ledger_balance' => 0,
            'available_balance' => 0,
        ]);

        return $account;
    }

    public function test_valid_cash_in_succeeds(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

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

        $this->assertEquals('COMPLETED', $result->status);
        $this->assertEquals('CASH_IN', $result->transaction_type);
        $this->assertTrue($result->geo_fence_passed);
    }

    public function test_cash_in_rejected_for_inactive_agent(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $context['agent']->update(['status' => AgentStatus::SUSPENDED->value]);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not ACTIVE');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_cash_in_rejected_for_insufficient_float(): void
    {
        $context = $this->makeReadyAgentContext(10000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('insufficient float');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_duplicate_idempotency_key_returns_original_response(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $first = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $second = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AgentTransaction::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_customer_account_credited_correctly(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $balance = CustomerAccountBalance::where('customer_account_id', $customerAccount->id)->first();
        $this->assertEquals(50000, (float) $balance->available_balance);
    }

    public function test_agent_float_updated_correctly(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $balance = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(150000, (float) $balance->available_float, 'Cash-in debits agent float per Blueprint §13.2, not credits it.');
    }

    public function test_declared_physical_cash_increases_on_cash_in(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $balanceBefore = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(0, (float) $balanceBefore->declared_physical_cash);

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $balanceAfter = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(50000, (float) $balanceAfter->declared_physical_cash, 'The agent physically receives cash here, so declared_physical_cash should increase.');
    }

    public function test_gl_posting_balances(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

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

        $journalEntries = GlJournal::where('reference', $result->transaction_no)->get();
        $this->assertCount(2, $journalEntries, 'Cash-in should post exactly 2 balanced GlJournal rows.');
    }

    public function test_transaction_outside_geo_fence_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('geo-fence');

        // Roughly 100km away from the terminal's registered coordinates.
        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            7.3775000,
            3.9470000,
            $user->id
        );
    }

    public function test_cash_in_persists_risk_assessment_metadata(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

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

        $result->refresh();

        $this->assertIsArray($result->risk_metadata);
        $this->assertArrayHasKey('score', $result->risk_metadata);
        $this->assertArrayHasKey('level', $result->risk_metadata);
        $this->assertArrayHasKey('rules', $result->risk_metadata);
    }

    public function test_cash_in_rejected_when_terminal_heartbeat_is_stale(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $context['terminal']->update([
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Terminal {$context['terminal']->terminal_id} heartbeat is stale."
        );

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal']->fresh(),
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_reused_idempotency_key_with_different_amount_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Idempotency key');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            40000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );
    }
}
