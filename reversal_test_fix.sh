#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > tests/Feature/TransactionReversalTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Models\User;
use App\Services\CashManagement\TransactionReversalService;
use App\Services\Customer\CustomerCashService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeTellerWithBalance(float $balance): Teller
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-'.uniqid(),
            'display_name' => 'Test Teller',
            'active' => true,
            'status' => 'OPEN',
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $teller;
    }

    protected function makeCustomerAccount(): CustomerAccount
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

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => 0,
            'available_balance' => 0,
        ]);

        return $account;
    }

    public function test_deposit_populates_the_link_between_customer_and_teller_transaction(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->first();

        $this->assertNotNull($tellerTransaction, 'The teller transaction should be linked to the customer transaction via the new FK.');
        $this->assertEquals($result['teller_transaction']->id, $tellerTransaction->id);
    }

    public function test_reversal_finds_the_correct_pair_even_with_two_identical_amount_deposits(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $first = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);
        $second = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTxForFirst = TellerTransaction::where(
            'customer_account_transaction_id',
            $first['customer_transaction']->id
        )->firstOrFail();

        $tellerTxForSecond = TellerTransaction::where(
            'customer_account_transaction_id',
            $second['customer_transaction']->id
        )->firstOrFail();

        $this->assertNotEquals($tellerTxForFirst->id, $tellerTxForSecond->id, 'Each deposit must resolve to its own distinct teller transaction, not either one.');

        app(TransactionReversalService::class)->reverseCustomerDeposit(
            $first['customer_transaction']->fresh(),
            $tellerTxForFirst->fresh(),
            $user->id
        );

        $this->assertTrue($first['customer_transaction']->fresh()->is_reversed, 'The first deposit should be reversed.');
        $this->assertFalse($second['customer_transaction']->fresh()->is_reversed, 'The second deposit must remain untouched.');
    }

    public function test_reversal_endpoint_correctly_reverses_a_deposit_and_posts_real_gl_entries(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->firstOrFail();

        $reversalResult = app(TransactionReversalService::class)->reverseCustomerDeposit(
            $result['customer_transaction']->fresh(),
            $tellerTransaction->fresh(),
            $user->id
        );

        $this->assertTrue($result['customer_transaction']->fresh()->is_reversed);
        $this->assertTrue($tellerTransaction->fresh()->is_reversed);

        $journalEntries = GlJournal::where('reference', $reversalResult['teller_reversal_transaction']->transaction_no)->get();
        $this->assertCount(2, $journalEntries, 'The reversal should create exactly 2 balanced GlJournal rows.');

        $accountBalance = CustomerAccountBalance::where('customer_account_id', $account->id)->first();
        $this->assertEquals(0, (float) $accountBalance->available_balance);
    }

    public function test_reversing_an_already_reversed_transaction_is_rejected(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->firstOrFail();

        $service = app(TransactionReversalService::class);
        $service->reverseCustomerDeposit($result['customer_transaction']->fresh(), $tellerTransaction->fresh(), $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already been reversed');

        $service->reverseCustomerDeposit(
            $result['customer_transaction']->fresh(),
            $tellerTransaction->fresh(),
            $user->id
        );
    }
}
MBOS_EOF

echo "GlAccountSeeder fix applied to TransactionReversalTest. Next: php artisan test --filter=TransactionReversalTest"