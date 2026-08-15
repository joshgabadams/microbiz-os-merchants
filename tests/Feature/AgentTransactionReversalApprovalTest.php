<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\User;
use App\Models\Vault;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Customer\CustomerAccountService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AgentTransactionReversalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(
        float $floatAmount = 200000
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

        $serviceEnabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $agent,
            'CASH_IN',
            $serviceEnabler->id
        );

        return [
            'agent' => $agent,
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

    protected function performCashIn(
        array $context,
        CustomerAccount $customerAccount,
        float $amount = 50000
    ): AgentTransaction {
        $performedBy = User::factory()->create();

        return app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            $amount,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $performedBy->id
        );
    }

    protected function makeReversalApprovalRequest(
        AgentTransaction $transaction,
        User $maker
    ): ApprovalRequest {
        return app(ApprovalRequestService::class)->createRequest(
            'AGENT_TRANSACTION_REVERSAL',
            [
                'agent_transaction_id' => $transaction->id,
                'narration' => 'Reverse agent cash-in',
            ],
            $maker->id,
            (float) $transaction->amount,
            $transaction->currency,
            'Maker requested reversal'
        );
    }

    public function test_maker_can_create_reversal_request_without_immediate_execution(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $transaction = $this->performCashIn($context, $customerAccount);
        $maker = User::factory()->create();

        $approval = $this->makeReversalApprovalRequest(
            $transaction,
            $maker
        );

        $this->assertSame('PENDING', $approval->status);

        $transaction->refresh();

        $this->assertSame('COMPLETED', $transaction->status);
        $this->assertNull($transaction->reversed_at);
    }

    public function test_different_checker_can_approve_and_execute_reversal(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $transaction = $this->performCashIn($context, $customerAccount);

        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $approval = $this->makeReversalApprovalRequest(
            $transaction,
            $maker
        );

        $approved = app(ApprovalRequestService::class)->approve(
            $approval,
            $checker->id,
            'Approved reversal'
        );

        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame($checker->id, $approved->checker_id);
        $this->assertNotNull($approved->approved_at);

        $transaction->refresh();

        $this->assertSame('REVERSED', $transaction->status);
        $this->assertSame($checker->id, $transaction->approved_by);
        $this->assertNotNull($transaction->reversed_at);
    }

    public function test_approval_links_to_reversed_agent_transaction(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $transaction = $this->performCashIn($context, $customerAccount);

        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $approval = $this->makeReversalApprovalRequest(
            $transaction,
            $maker
        );

        $approved = app(ApprovalRequestService::class)->approve(
            $approval,
            $checker->id
        );

        $this->assertSame(
            AgentTransaction::class,
            $approved->executed_transaction_type
        );

        $this->assertSame(
            $transaction->id,
            $approved->executed_transaction_id
        );

        $executed = $approved->executedTransaction;

        $this->assertInstanceOf(
            AgentTransaction::class,
            $executed
        );

        $this->assertSame(
            $transaction->id,
            $executed->id
        );
    }

    public function test_maker_cannot_approve_their_own_reversal_request(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $transaction = $this->performCashIn($context, $customerAccount);

        $maker = User::factory()->create();

        $approval = $this->makeReversalApprovalRequest(
            $transaction,
            $maker
        );

        try {
            app(ApprovalRequestService::class)->approve(
                $approval,
                $maker->id
            );

            $this->fail(
                'Expected maker-checker validation to reject self-approval.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'checker',
                $exception->errors()
            );
        }

        $approval->refresh();
        $transaction->refresh();

        $this->assertSame('PENDING', $approval->status);
        $this->assertSame('COMPLETED', $transaction->status);
        $this->assertNull($transaction->reversed_at);
    }

    public function test_failed_reversal_keeps_approval_pending_and_transaction_completed(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $transaction = $this->performCashIn($context, $customerAccount);

        $spender = User::factory()->create();

        app(CustomerAccountService::class)->withdraw(
            $customerAccount,
            10000,
            $spender->id,
            'TEST-SPEND-'.uniqid(),
            'Customer spent part of cash-in'
        );

        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $approval = $this->makeReversalApprovalRequest(
            $transaction,
            $maker
        );

        try {
            app(ApprovalRequestService::class)->approve(
                $approval,
                $checker->id
            );

            $this->fail(
                'Expected the financial reversal to fail.'
            );
        } catch (\Exception $exception) {
            $this->assertStringContainsString(
                'Insufficient',
                $exception->getMessage()
            );
        }

        $approval->refresh();
        $transaction->refresh();

        $this->assertSame('PENDING', $approval->status);
        $this->assertNull($approval->checker_id);
        $this->assertNull($approval->approved_at);

        $this->assertSame('COMPLETED', $transaction->status);
        $this->assertNull($transaction->reversed_at);
    }
}
