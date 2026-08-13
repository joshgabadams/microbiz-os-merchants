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
use App\Models\Role;
use App\Models\User;
use App\Models\Vault;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GlAccountSeeder::class);
        $this->seed(PaymentsRbacSeeder::class);
    }

    protected function attachRole(
        User $user,
        string $roleName
    ): void {
        $role = Role::where('name', $roleName)
            ->firstOrFail();

        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

    protected function makeReadyAgentContext(
        array $services = ['CASH_IN', 'CASH_OUT', 'TRANSFER'],
        float $floatAmount = 200000
    ): array {
        $branch = Branch::create([
            'name' => 'Agent Transaction API Branch',
            'code' => 'ATAB-'.uniqid(),
            'office_id' => 1,
        ]);

        $registrant = User::factory()->create();

        $agent = Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Agent Transaction API Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $registrant->id,
            'single_transaction_limit' => 1000000,
        ]);

        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'ACTIVE',
            'created_by' => User::factory()->create()->id,
        ]);

        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Agent Transaction Test Street',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => User::factory()->create()->id,
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

        $serviceEnabler = User::factory()->create();

        foreach ($services as $service) {
            app(AgentServiceConfigurationService::class)
                ->enableService(
                    $agent,
                    $service,
                    $serviceEnabler->id
                );
        }

        if ($floatAmount > 0) {
            $vaultBranch = Branch::create([
                'name' => 'Agent Transaction Vault Branch',
                'code' => 'ATVB-'.uniqid(),
                'office_id' => 1,
            ]);

            $vault = Vault::create([
                'branch_id' => $vaultBranch->id,
                'code' => 'VLT-'.uniqid(),
                'name' => 'Agent Transaction API Vault',
                'active' => true,
            ]);

            $vaultFunder = User::factory()->create();

            app(VaultTransactionService::class)->deposit(
                $vault,
                $floatAmount + 100000,
                $vaultFunder->id
            );

            app(AgentFloatService::class)->allocateFloat(
                $vault,
                $agent,
                $floatAmount,
                $vaultFunder->id
            );
        }

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
    }

    protected function makeCustomerAccount(
        float $openingBalance = 0
    ): CustomerAccount {
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

    protected function cashInPayload(
        array $context,
        CustomerAccount $account,
        array $overrides = []
    ): array {
        return array_merge([
            'operator_id' => $context['operator']->id,
            'terminal_id' => $context['terminal']->id,
            'customer_account_id' => $account->id,
            'amount' => 10000,
            'idempotency_key' => (string) Str::uuid(),
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'customer_reference' => 'API-CASH-IN-001',
            'narration' => 'Agent API cash-in test.',
        ], $overrides);
    }

    protected function cashOutPayload(
        array $context,
        CustomerAccount $account,
        array $overrides = []
    ): array {
        return array_merge([
            'operator_id' => $context['operator']->id,
            'terminal_id' => $context['terminal']->id,
            'customer_account_id' => $account->id,
            'amount' => 5000,
            'idempotency_key' => (string) Str::uuid(),
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'customer_authenticated' => true,
            'customer_reference' => 'API-CASH-OUT-001',
            'narration' => 'Agent API cash-out test.',
        ], $overrides);
    }

    protected function transferPayload(
        array $context,
        CustomerAccount $fromAccount,
        CustomerAccount $toAccount,
        array $overrides = []
    ): array {
        return array_merge([
            'operator_id' => $context['operator']->id,
            'terminal_id' => $context['terminal']->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 5000,
            'idempotency_key' => (string) Str::uuid(),
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'narration' => 'Agent API transfer test.',
        ], $overrides);
    }

    protected function fundPhysicalCash(
        array $context,
        float $amount = 50000
    ): void {
        $fundingCustomer = $this->makeCustomerAccount();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $fundingCustomer,
            $amount,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            User::factory()->create()->id
        );
    }

    protected function createCompletedAgentTransaction(
        array $context,
        float $amount,
        string $transactionType = 'CASH_IN'
    ): AgentTransaction {
        return AgentTransaction::create([
            'transaction_no' => 'AGT-'.uniqid(),
            'idempotency_key' => (string) Str::uuid(),
            'agent_id' => $context['agent']->id,
            'agent_location_id' => $context['location']->id,
            'agent_terminal_id' => $context['terminal']->id,
            'agent_operator_id' => $context['operator']->id,
            'transaction_type' => $transactionType,
            'status' => 'COMPLETED',
            'amount' => $amount,
            'currency' => 'NGN',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'geo_fence_passed' => true,
            'transaction_date' => now(),
            'performed_by' => User::factory()->create()->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_cash_in(): void
    {
        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_user_without_cash_in_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-kyc-officer'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_request_validation_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'operator_id',
                'terminal_id',
                'customer_account_id',
                'amount',
                'idempotency_key',
                'latitude',
                'longitude',
            ]);

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_operator_from_another_agent_cannot_transact_on_route_agent(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $routeContext = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $otherContext = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $account = $this->makeCustomerAccount();

        $payload = $this->cashInPayload(
            $otherContext,
            $account
        );

        $response = $this->postJson(
            "/api/v1/agents/{$routeContext['agent']->id}/transactions/cash-in",
            $payload
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_authorized_user_can_perform_cash_in(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload(
                $context,
                $account
            )
        );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'agent_transactions',
            [
                'agent_id' => $context['agent']->id,
                'agent_operator_id' => $context['operator']->id,
                'agent_terminal_id' => $context['terminal']->id,
                'transaction_type' => 'CASH_IN',
                'performed_by' => $user->id,
            ]
        );
    }

    public function test_cash_out_requires_customer_authentication(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN', 'CASH_OUT']
        );

        $account = $this->makeCustomerAccount(
            50000
        );

        $payload = $this->cashOutPayload(
            $context,
            $account
        );

        unset($payload['customer_authenticated']);

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $payload
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_authenticated',
            ]);
    }

    public function test_authorized_user_can_perform_cash_out(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN', 'CASH_OUT']
        );

        $this->fundPhysicalCash(
            $context,
            50000
        );

        $account = $this->makeCustomerAccount(
            50000
        );

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $this->cashOutPayload(
                $context,
                $account
            )
        );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'agent_transactions',
            [
                'agent_id' => $context['agent']->id,
                'agent_operator_id' => $context['operator']->id,
                'agent_terminal_id' => $context['terminal']->id,
                'transaction_type' => 'CASH_OUT',
                'performed_by' => $user->id,
            ]
        );
    }

    public function test_transfer_rejects_same_source_and_destination_account(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['TRANSFER']
        );

        $account = $this->makeCustomerAccount(
            50000
        );

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/transfer",
            $this->transferPayload(
                $context,
                $account,
                $account
            )
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_account_id',
            ]);

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_authorized_user_can_perform_transfer(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['TRANSFER']
        );

        $fromAccount = $this->makeCustomerAccount(
            50000
        );

        $toAccount = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/transfer",
            $this->transferPayload(
                $context,
                $fromAccount,
                $toAccount
            )
        );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'agent_transactions',
            [
                'agent_id' => $context['agent']->id,
                'agent_operator_id' => $context['operator']->id,
                'agent_terminal_id' => $context['terminal']->id,
                'transaction_type' => 'TRANSFER',
                'performed_by' => $user->id,
            ]
        );
    }

        public function test_cash_in_rejects_invalid_idempotency_key_format(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload(
                $context,
                $account,
                [
                    'idempotency_key' => 'not-a-valid-uuid',
                ]
            )
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'idempotency_key',
            ]);

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_inactive_agent(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['agent']->update([
            'status' => AgentStatus::SUSPENDED->value,
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_agent_without_active_agreement(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['agent']
            ->agreements()
            ->update([
                'status' => 'INACTIVE',
            ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_agent_with_incomplete_kyc(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['agent']->update([
            'kyc_status' => 'PENDING',
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_inactive_location(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['location']->update([
            'status' => 'INACTIVE',
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_inactive_operator(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['operator']->update([
            'status' => 'INACTIVE',
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_inactive_terminal(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['terminal']->update([
            'status' => 'INACTIVE',
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_amount_above_single_transaction_limit(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $context['agent']->update([
            'single_transaction_limit' => 5000,
        ]);

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload(
                $context,
                $account,
                [
                    'amount' => 10000,
                ]
            )
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_insufficient_agent_float(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN'],
            5000
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload(
                $context,
                $account,
                [
                    'amount' => 10000,
                ]
            )
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_in_rejects_agent_without_cash_in_service_enabled(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            []
        );

        $account = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
            $this->cashInPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_out_rejects_insufficient_physical_liquidity(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_OUT']
        );

        $account = $this->makeCustomerAccount(
            50000
        );

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $this->cashOutPayload(
                $context,
                $account,
                [
                    'amount' => 5000,
                ]
            )
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }

    public function test_cash_out_rejects_agent_without_cash_out_service_enabled(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN']
        );

        $this->fundPhysicalCash(
            $context,
            50000
        );

        $account = $this->makeCustomerAccount(
            50000
        );

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $this->cashOutPayload($context, $account)
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            1
        );
    }

    public function test_transfer_rejects_agent_without_transfer_service_enabled(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            []
        );

        $fromAccount = $this->makeCustomerAccount(
            50000
        );

        $toAccount = $this->makeCustomerAccount();

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/transfer",
            $this->transferPayload(
                $context,
                $fromAccount,
                $toAccount
            )
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'agent_transactions',
            0
        );
    }
    public function test_cash_in_rejects_when_daily_cumulative_limit_would_be_exceeded(): void
    {
    $user = User::factory()->create();

    $this->attachRole(
        $user,
        'agent-transaction-operator'
    );

    Sanctum::actingAs($user);

    $context = $this->makeReadyAgentContext(
        ['CASH_IN'],
        200000
    );

    $context['agent']->update([
        'daily_transaction_limit' => 100000,
    ]);

    $this->createCompletedAgentTransaction(
        $context,
        80000
    );

    $account = $this->makeCustomerAccount();

    $response = $this->postJson(
        "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
        $this->cashInPayload(
            $context,
            $account,
            ['amount' => 30000]
        )
    );

    $response->assertUnprocessable();

    $this->assertStringContainsString(
        'daily cumulative limit',
        $response->getContent()
    );

    $this->assertDatabaseMissing('agent_transactions', [
        'agent_id' => $context['agent']->id,
        'amount' => 30000,
        'status' => 'COMPLETED',
    ]);
}

    public function test_cash_in_allows_transaction_at_exact_daily_cumulative_limit(): void
    {
    $user = User::factory()->create();

    $this->attachRole(
        $user,
        'agent-transaction-operator'
    );

    Sanctum::actingAs($user);

    $context = $this->makeReadyAgentContext(
        ['CASH_IN'],
        200000
    );

    $context['agent']->update([
        'daily_transaction_limit' => 100000,
    ]);

    $this->createCompletedAgentTransaction(
        $context,
        80000
    );

    $account = $this->makeCustomerAccount();

    $response = $this->postJson(
        "/api/v1/agents/{$context['agent']->id}/transactions/cash-in",
        $this->cashInPayload(
            $context,
            $account,
            ['amount' => 20000]
        )
    );

    $response->assertCreated();

    $this->assertDatabaseHas('agent_transactions', [
        'agent_id' => $context['agent']->id,
        'transaction_type' => 'CASH_IN',
        'status' => 'COMPLETED',
        'amount' => 20000,
    ]);
}

    public function test_transfer_rejects_when_daily_cumulative_limit_would_be_exceeded(): void
    {
    $user = User::factory()->create();

    $this->attachRole(
        $user,
        'agent-transaction-operator'
    );

    Sanctum::actingAs($user);

    $context = $this->makeReadyAgentContext(
        ['TRANSFER']
    );

    $context['agent']->update([
        'daily_transaction_limit' => 100000,
    ]);

    $this->createCompletedAgentTransaction(
        $context,
        80000
    );

    $fromAccount = $this->makeCustomerAccount(100000);
    $toAccount = $this->makeCustomerAccount();

    $response = $this->postJson(
        "/api/v1/agents/{$context['agent']->id}/transactions/transfer",
        $this->transferPayload(
            $context,
            $fromAccount,
            $toAccount,
            ['amount' => 30000]
        )
    );

    $response->assertUnprocessable();

    $this->assertStringContainsString(
        'daily cumulative limit',
        $response->getContent()
    );
}

    public function test_prior_cash_in_does_not_consume_daily_cash_out_limit(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN', 'CASH_OUT'],
            200000
        );

        $context['agent']->update([
            'daily_transaction_limit' => 500000,
            'daily_cash_out_limit' => 50000,
        ]);

        /*
         * This cash-in creates genuine physical liquidity and contributes
         * to the general daily transaction total, but it must not consume
         * the cash-out-specific daily limit.
         */
        $this->fundPhysicalCash(
            $context,
            40000
        );

        $account = $this->makeCustomerAccount(100000);

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $this->cashOutPayload(
                $context,
                $account,
                ['amount' => 20000]
            )
        );

        $response->assertCreated();

        $this->assertDatabaseHas('agent_transactions', [
            'agent_id' => $context['agent']->id,
            'transaction_type' => 'CASH_OUT',
            'status' => 'COMPLETED',
            'amount' => 20000,
        ]);
    }

    public function test_cash_out_rejects_when_daily_cash_out_limit_would_be_exceeded(): void
    {
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-transaction-operator'
        );

        Sanctum::actingAs($user);

        $context = $this->makeReadyAgentContext(
            ['CASH_IN', 'CASH_OUT'],
            200000
        );

        $context['agent']->update([
            'daily_transaction_limit' => 500000,
            'daily_cash_out_limit' => 50000,
        ]);

        /*
         * Build enough real physical liquidity to ensure the rejection
         * comes from the cash-out daily limit rather than liquidity.
         */
        $this->fundPhysicalCash(
            $context,
            100000
        );

        $this->createCompletedAgentTransaction(
            $context,
            40000,
            'CASH_OUT'
        );

        $account = $this->makeCustomerAccount(100000);

        $response = $this->postJson(
            "/api/v1/agents/{$context['agent']->id}/transactions/cash-out",
            $this->cashOutPayload(
                $context,
                $account,
                ['amount' => 20000]
            )
        );

        $response->assertUnprocessable();

        $this->assertStringContainsString(
            'daily cumulative limit',
            $response->getContent()
        );
    }



}