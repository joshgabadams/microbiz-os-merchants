<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use App\Services\Customer\CustomerCashService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the exact bug found and fixed this session: CustomerCashService
 * used to fake the "posted" state by setting status=APPROVED directly and
 * firing FinancialTransactionPosted by hand, without ever calling
 * GlPostingService -- meaning no GlJournal rows were ever created for
 * customer deposits/withdrawals. This locks in the fix.
 */
class CustomerCashGlPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeTellerWithBalance(float $openingBalance = 0): Teller
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-001',
            'office_id' => 1,
        ]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-TEST-'.uniqid(),
            'display_name' => 'Test Teller',
            'status' => 'OPEN',
            'active' => true,
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $teller;
    }

    protected function makeActiveCustomerAccount(float $openingBalance = 0): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        // CustomerAccountService independently checks the customer's own
        // balance (separate from the teller's physical cash) -- funding
        // only TellerBalance and leaving this at its auto-created zero
        // is exactly the bug that failed this test the first time.
        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $account;
    }

    public function test_deposit_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(0);
        $account = $this->makeActiveCustomerAccount();

        $result = app(CustomerCashService::class)->deposit(
            $teller,
            $account,
            1000,
            $user->id
        );

        $referenceNo = $result['cash_ledger']->reference_no;

        $journalEntries = GlJournal::where('reference', $referenceNo)->get();

        $this->assertCount(
            2,
            $journalEntries,
            'Expected exactly one DEBIT and one CREDIT journal entry for a deposit.'
        );

        $debit = $journalEntries->firstWhere('entry_type', 'DEBIT');
        $credit = $journalEntries->firstWhere('entry_type', 'CREDIT');

        $this->assertNotNull($debit, 'Missing DEBIT journal entry.');
        $this->assertNotNull($credit, 'Missing CREDIT journal entry.');
        $this->assertEquals(1000, (float) $debit->amount);
        $this->assertEquals(1000, (float) $credit->amount);

        $this->assertEquals('APPROVED', $result['cash_ledger']->fresh()->status);
    }

    public function test_withdraw_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(5000);
        $account = $this->makeActiveCustomerAccount(5000);

        $result = app(CustomerCashService::class)->withdraw(
            $teller,
            $account,
            500,
            $user->id
        );

        $referenceNo = $result['cash_ledger']->reference_no;

        $journalEntries = GlJournal::where('reference', $referenceNo)->get();

        $this->assertCount(2, $journalEntries);

        $debit = $journalEntries->firstWhere('entry_type', 'DEBIT');
        $credit = $journalEntries->firstWhere('entry_type', 'CREDIT');

        $this->assertEquals(500, (float) $debit->amount);
        $this->assertEquals(500, (float) $credit->amount);
        $this->assertEquals('APPROVED', $result['cash_ledger']->fresh()->status);
    }

    public function test_withdraw_rejects_when_teller_cash_is_insufficient(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(100);
        $account = $this->makeActiveCustomerAccount();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient teller cash balance.');

        app(CustomerCashService::class)->withdraw(
            $teller,
            $account,
            5000,
            $user->id
        );
    }
}