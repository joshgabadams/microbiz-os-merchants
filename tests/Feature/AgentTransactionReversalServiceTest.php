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
use App\Models\CashLedger;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\User;
use App\Models\Vault;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Customer\CustomerAccountService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentCashOutService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Payments\AgentTransactionReversalService;
use App\Services\Payments\AgentTransferService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentTransactionReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(
        float $floatAmount = 200000,
        array $agentOverrides = [],
        array $servicesToEnable = ['CASH_IN']
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

        foreach ($servicesToEnable as $serviceType) {
            app(AgentServiceConfigurationService::class)->enableService(
                $agent,
                $serviceType,
                $serviceEnabler->id
            );
        }

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
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

    protected function performCashOut(
        array $context,
        CustomerAccount $customerAccount,
        float $amount = 30000
    ): AgentTransaction {
        $performedBy = User::factory()->create();

        return app(AgentCashOutService::class)->cashOut(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            $amount,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $performedBy->id,
            true
        );
    }

    protected function performTransfer(
        array $context,
        CustomerAccount $from,
        CustomerAccount $to,
        float $amount = 40000
    ): AgentTransaction {
        $performedBy = User::factory()->create();

        return app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            $amount,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $performedBy->id
        );
    }

    public function test_completed_cash_in_can_be_reversed(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn(
            $context,
            $customerAccount,
            50000
        );

        $checker = User::factory()->create();

        $reversed = app(AgentTransactionReversalService::class)->reverse(
            $transaction,
            $checker->id,
            'Approved cash-in reversal'
        );

        $this->assertSame('REVERSED', $reversed->status);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertSame($checker->id, $reversed->approved_by);
    }

    public function test_cash_in_reversal_restores_customer_and_agent_balances(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn(
            $context,
            $customerAccount,
            50000
        );

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse(
            $transaction,
            $checker->id
        );

        $agentBalanceAfterReversal = AgentBalance::where(
            'agent_id',
            $context['agent']->id
        )->firstOrFail();

        $customerBalanceAfterReversal = CustomerAccountBalance::where(
            'customer_account_id',
            $customerAccount->id
        )->firstOrFail();

        $this->assertEquals(200000, (float) $agentBalanceAfterReversal->ledger_float);
        $this->assertEquals(200000, (float) $agentBalanceAfterReversal->available_float);
        $this->assertEquals(0, (float) $agentBalanceAfterReversal->declared_physical_cash);
        $this->assertEquals(0, (float) $customerBalanceAfterReversal->ledger_balance);
        $this->assertEquals(0, (float) $customerBalanceAfterReversal->available_balance);
    }

    public function test_cash_in_reversal_creates_compensating_cash_ledger_and_gl_entries(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn(
            $context,
            $customerAccount,
            50000
        );

        $originalJournalCount = GlJournal::count();
        $originalLedgerCount = CashLedger::count();

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse(
            $transaction,
            $checker->id,
            'Reverse erroneous cash-in'
        );

        $this->assertSame($originalLedgerCount + 1, CashLedger::count());
        $this->assertSame($originalJournalCount + 2, GlJournal::count());

        $reversalLedger = CashLedger::where('transaction_type', 'AGENT_CASH_IN_REVERSAL')
            ->where('source_type', AgentTransaction::class)
            ->where('source_id', $transaction->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('CUSTOMER_DEPOSIT_CONTROL', $reversalLedger->debit_account_key);
        $this->assertSame('AGENCY_FLOAT', $reversalLedger->credit_account_key);
        $this->assertEquals(50000, (float) $reversalLedger->credit);
        $this->assertSame('APPROVED', $reversalLedger->status);
    }

    public function test_transaction_cannot_be_reversed_twice(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $checker = User::factory()->create();
        $service = app(AgentTransactionReversalService::class);

        $service->reverse($transaction, $checker->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Agent transaction has already been reversed.');

        $service->reverse($transaction, $checker->id);
    }

    public function test_non_completed_transaction_cannot_be_reversed(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $transaction->update(['status' => 'FAILED']);

        $checker = User::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only completed agent transactions can be reversed.');

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
    }

    /**
     * Replaces the old test_non_cash_in_transaction_cannot_be_reversed --
     * CASH_OUT is now a genuinely valid reversal type, so that premise no
     * longer holds. INTERBANK_TRANSFER remains genuinely unreversible
     * (it never actually reaches COMPLETED today).
     */
    public function test_interbank_transfer_transaction_cannot_be_reversed(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $transaction->update(['transaction_type' => 'INTERBANK_TRANSFER']);

        $checker = User::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only CASH_IN, CASH_OUT and TRANSFER agent transactions can be reversed.');

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
    }

    public function test_cash_in_cannot_be_reversed_if_customer_spent_the_funds(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $spender = User::factory()->create();

        app(CustomerAccountService::class)->withdraw(
            $customerAccount,
            10000,
            $spender->id,
            'TEST-SPEND-'.uniqid(),
            'Customer spent part of cash-in'
        );

        $checker = User::factory()->create();

        try {
            app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
            $this->fail('Expected reversal to fail because the customer no longer has sufficient balance.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('Insufficient', $exception->getMessage());
        }

        $transaction->refresh();
        $this->assertSame('COMPLETED', $transaction->status);
        $this->assertNull($transaction->reversed_at);
    }

    public function test_cash_in_cannot_be_reversed_with_insufficient_declared_physical_cash(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        AgentBalance::where('agent_id', $context['agent']->id)->update([
            'declared_physical_cash' => 10000,
        ]);

        $checker = User::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Agent has insufficient declared physical cash for reversal.');

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
    }

    // ---- CASH_OUT reversal ----

    public function test_completed_cash_out_can_be_reversed(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'CASH_OUT']);
        $customerAccount = $this->makeActiveCustomerAccount(80000);

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 100000);

        $transaction = $this->performCashOut($context, $customerAccount, 30000);

        $checker = User::factory()->create();

        $reversed = app(AgentTransactionReversalService::class)->reverse(
            $transaction,
            $checker->id,
            'Dispensed incorrect amount'
        );

        $this->assertSame('REVERSED', $reversed->status);
        $this->assertNotNull($reversed->reversed_at);
    }

    public function test_cash_out_reversal_restores_customer_and_agent_balances(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'CASH_OUT']);
        $customerAccount = $this->makeActiveCustomerAccount(80000);

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 100000);

        $balanceBeforeCashOut = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();

        $transaction = $this->performCashOut($context, $customerAccount, 30000);

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $agentBalanceAfterReversal = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $customerBalanceAfterReversal = CustomerAccountBalance::where('customer_account_id', $customerAccount->id)->firstOrFail();

        $this->assertEquals((float) $balanceBeforeCashOut->available_float, (float) $agentBalanceAfterReversal->available_float);
        $this->assertEquals((float) $balanceBeforeCashOut->declared_physical_cash, (float) $agentBalanceAfterReversal->declared_physical_cash);
        $this->assertEquals(80000, (float) $customerBalanceAfterReversal->available_balance);
    }

    public function test_cash_out_reversal_creates_compensating_cash_ledger_and_gl_entries(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'CASH_OUT']);
        $customerAccount = $this->makeActiveCustomerAccount(80000);

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 100000);

        $transaction = $this->performCashOut($context, $customerAccount, 30000);

        $originalJournalCount = GlJournal::count();
        $originalLedgerCount = CashLedger::count();

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $this->assertSame($originalLedgerCount + 1, CashLedger::count());
        $this->assertSame($originalJournalCount + 2, GlJournal::count());

        $reversalLedger = CashLedger::where('transaction_type', 'AGENT_CASH_OUT_REVERSAL')
            ->where('source_type', AgentTransaction::class)
            ->where('source_id', $transaction->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('AGENCY_FLOAT', $reversalLedger->debit_account_key);
        $this->assertSame('CUSTOMER_DEPOSIT_CONTROL', $reversalLedger->credit_account_key);
        $this->assertEquals(30000, (float) $reversalLedger->debit);
        $this->assertSame('APPROVED', $reversalLedger->status);
    }

    public function test_cash_out_cannot_be_reversed_with_insufficient_available_float(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'CASH_OUT']);
        $customerAccount = $this->makeActiveCustomerAccount(80000);

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 100000);

        $transaction = $this->performCashOut($context, $customerAccount, 30000);

        AgentBalance::where('agent_id', $context['agent']->id)->update([
            'available_float' => 5000,
        ]);

        $checker = User::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Agent has insufficient available float for reversal.');

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
    }

    // ---- TRANSFER reversal ----

    public function test_completed_transfer_can_be_reversed(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'TRANSFER']);
        $from = $this->makeActiveCustomerAccount(100000);
        $to = $this->makeActiveCustomerAccount(0);

        $transaction = $this->performTransfer($context, $from, $to, 40000);

        $checker = User::factory()->create();

        $reversed = app(AgentTransactionReversalService::class)->reverse(
            $transaction,
            $checker->id,
            'Sent to wrong recipient'
        );

        $this->assertSame('REVERSED', $reversed->status);
        $this->assertNotNull($reversed->reversed_at);
    }

    public function test_transfer_reversal_moves_money_back_to_original_source(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'TRANSFER']);
        $from = $this->makeActiveCustomerAccount(100000);
        $to = $this->makeActiveCustomerAccount(0);

        $transaction = $this->performTransfer($context, $from, $to, 40000);

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $fromBalance = CustomerAccountBalance::where('customer_account_id', $from->id)->firstOrFail();
        $toBalance = CustomerAccountBalance::where('customer_account_id', $to->id)->firstOrFail();

        $this->assertEquals(100000, (float) $fromBalance->available_balance);
        $this->assertEquals(0, (float) $toBalance->available_balance);
    }

    public function test_transfer_reversal_posts_no_gl_entries(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'TRANSFER']);
        $from = $this->makeActiveCustomerAccount(100000);
        $to = $this->makeActiveCustomerAccount(0);

        $transaction = $this->performTransfer($context, $from, $to, 40000);

        $originalJournalCount = GlJournal::count();

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $this->assertSame($originalJournalCount, GlJournal::count(), 'Transfer reversal should have zero net GL effect, matching the original transfer.');
    }

    public function test_transfer_cannot_be_reversed_if_recipient_spent_the_funds(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'TRANSFER']);
        $from = $this->makeActiveCustomerAccount(100000);
        $to = $this->makeActiveCustomerAccount(0);

        $transaction = $this->performTransfer($context, $from, $to, 40000);

        $spender = User::factory()->create();

        app(CustomerAccountService::class)->withdraw(
            $to,
            30000,
            $spender->id,
            'TEST-SPEND-'.uniqid(),
            'Recipient spent part of the transfer'
        );

        $checker = User::factory()->create();

        try {
            app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);
            $this->fail('Expected reversal to fail because the recipient no longer has sufficient balance.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('Insufficient', $exception->getMessage());
        }

        $transaction->refresh();
        $this->assertSame('COMPLETED', $transaction->status);
    }

    // ---- Commission clawback (Blueprint Module 12) ----

    public function test_cash_in_reversal_claws_back_commission(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'CASH_IN', $enabler->id, null, 100, true, null, null, 30
        );

        $transaction = $this->performCashIn($context, $customerAccount, 50000);

        $balanceAfterCashIn = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(30, (float) $balanceAfterCashIn->pending_commission);

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $balanceAfterReversal = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(0, (float) $balanceAfterReversal->pending_commission);
    }

    public function test_cash_out_reversal_claws_back_commission(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'CASH_OUT']);
        $customerAccount = $this->makeActiveCustomerAccount(80000);
        $enabler = User::factory()->create();

        $this->performCashIn($context, $this->makeActiveCustomerAccount(), 100000);

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'CASH_OUT', $enabler->id, null, 100, true, null, null, 20
        );

        $transaction = $this->performCashOut($context, $customerAccount, 30000);

        $balanceAfterCashOut = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(20, (float) $balanceAfterCashOut->pending_commission);

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $balanceAfterReversal = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(0, (float) $balanceAfterReversal->pending_commission);
    }

    public function test_transfer_reversal_claws_back_commission(): void
    {
        $context = $this->makeReadyAgentContext(200000, [], ['CASH_IN', 'TRANSFER']);
        $from = $this->makeActiveCustomerAccount(100000);
        $to = $this->makeActiveCustomerAccount(0);
        $enabler = User::factory()->create();

        app(AgentServiceConfigurationService::class)->enableService(
            $context['agent'], 'TRANSFER', $enabler->id, null, 50, true, null, null, 15
        );

        $transaction = $this->performTransfer($context, $from, $to, 40000);

        $balanceAfterTransfer = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(15, (float) $balanceAfterTransfer->pending_commission);

        $checker = User::factory()->create();

        app(AgentTransactionReversalService::class)->reverse($transaction, $checker->id);

        $balanceAfterReversal = AgentBalance::where('agent_id', $context['agent']->id)->firstOrFail();
        $this->assertEquals(0, (float) $balanceAfterReversal->pending_commission);
    }
}
