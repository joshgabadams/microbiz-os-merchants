#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Teller/TellerTransactionService.php << 'MBOS_EOF'
<?php

namespace App\Services\Teller;

use App\Events\FinancialTransactionCreated;
use App\Events\FinancialTransactionPosted;
use App\Models\CashLedger;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class TellerTransactionService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * Receive float from a vault.
     *
     * @throws Exception
     */
    public function receiveFloat(
        Teller $teller,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): TellerTransaction {

        if ($amount <= 0) {
            throw new Exception(
                'Float amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $teller,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {

            $transactionDate = now();

            if (!$teller->active) {
                throw new Exception('Teller is inactive.');
            }

            $balance = $this->getOrCreateBalance($teller);

            $transaction = $this->createTransaction(
                teller: $teller,
                transactionType: 'RECEIVE_FLOAT',
                amount: $amount,
                performedBy: $performedBy,
                reference: $reference,
                narration: $narration,
                transactionDate: $transactionDate,
            );

            $balance->ledger_balance += $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_id = $transaction->id;
            $balance->save();

            $cashLedger = CashLedger::create([

                'reference_no'       => $transaction->transaction_no,

                'branch_id'          => $teller->branch_id,

                'vault_id'           => $teller->vault_id,

                'teller_id'          => $teller->id,

                'user_id'            => $performedBy,

                'transaction_type'   => 'FLOAT_RECEIPT',

                'source_type'        => TellerTransaction::class,

                'source_id'          => $transaction->id,

                'entry_type'         => 'DEBIT',

                'account_type'       => 'TELLER_CASH',

                'account_code'       => 'TELLER_CASH',

                'debit_account_key'  => 'TELLER_CASH',

                'credit_account_key' => 'VAULT_CASH',

                'debit'              => $amount,

                'credit'             => 0,

                'running_balance'    => $balance->ledger_balance,

                'currency'           => $balance->currency,

                'narration'          => $narration,

                'status'             => 'PENDING',

                'approved_by'        => null,

                'transaction_date'   => $transactionDate,

            ]);

            $this->postTransaction(
                $transaction,
                $cashLedger
            );

            return $transaction->fresh();

        });
    }
/**
 * Return float to vault.
 *
 * @throws Exception
 */
public function returnFloat(
    Teller $teller,
    float $amount,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    if ($amount <= 0) {
        throw new Exception('Float amount must be greater than zero.');
    }

    return DB::transaction(function () use (
        $teller,
        $amount,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        $balance = $this->getOrCreateBalance($teller);

        if ($balance->available_balance < $amount) {
            throw new Exception('Insufficient teller balance.');
        }

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'RETURN_FLOAT',
            amount: $amount,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration,
            transactionDate: $transactionDate,
        );

        $balance->ledger_balance -= $amount;
        $balance->available_balance -= $amount;
        $balance->last_transaction_id = $transaction->id;
        $balance->save();

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'FLOAT_RETURN',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'CREDIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'VAULT_CASH',
            'credit_account_key' => 'TELLER_CASH',
            'debit'              => 0,
            'credit'             => $amount,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration,
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $this->postTransaction(
            $transaction,
            $cashLedger
        );

        return $transaction->fresh();
    });
}
    /**
 * Open teller for the day/session.
 *
 * @throws Exception
 */
public function openTeller(
    Teller $teller,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    return DB::transaction(function () use (
        $teller,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        if ($teller->status === 'OPEN') {
            throw new Exception('Teller is already open.');
        }

        $balance = $this->getOrCreateBalance($teller);

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'OPENING_CASH',
            amount: $balance->available_balance,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration ?? 'Teller opening cash',
            transactionDate: $transactionDate,
        );

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'TELLER_OPENING',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'DEBIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'TELLER_CASH',
            'credit_account_key' => 'OPENING_CASH_CONTROL',
            'debit'              => $balance->available_balance,
            'credit'             => 0,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration ?? 'Teller opening cash',
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $transaction->update([
    'posted' => true,
]);

if ($balance->available_balance > 0) {
    $cashLedger->update([
        'approved_by' => $performedBy,
    ]);

    // Creates the actual balanced GlJournal debit/credit pair (debit
    // TELLER_CASH, credit OPENING_CASH_CONTROL) and fires
    // FinancialTransactionPosted internally -- this used to be skipped
    // entirely (the CashLedger row was manually flipped to APPROVED
    // with no journal entries ever created, a real gap found while
    // investigating the OPENING_CASH_CONTROL/CLOSING_CASH_CONTROL keys).
    // Skipped entirely for a zero balance -- postFromCashLedger()
    // correctly rejects zero-value ledger entries (a $0 journal entry
    // is meaningless), and a teller can legitimately open with nothing.
    $this->glPostingService->postFromCashLedger($cashLedger);
} else {
    $cashLedger->update([
        'status' => 'APPROVED',
        'approved_by' => $performedBy,
    ]);
}

        $teller->update([
            'status' => 'OPEN',
        ]);

        return $transaction->fresh();
    });
}

    /**
 * Close teller for the day/session.
 *
 * @throws Exception
 */
public function closeTeller(
    Teller $teller,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    return DB::transaction(function () use (
        $teller,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        if ($teller->status === 'CLOSED') {
            throw new Exception('Teller is already closed.');
        }

        $balance = $this->getOrCreateBalance($teller);

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'CLOSING_CASH',
            amount: $balance->available_balance,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration ?? 'Teller closing cash',
            transactionDate: $transactionDate,
        );

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'TELLER_CLOSING',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'CREDIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'CLOSING_CASH_CONTROL',
            'credit_account_key' => 'TELLER_CASH',
            'debit'              => 0,
            'credit'             => $balance->available_balance,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration ?? 'Teller closing cash',
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $transaction->update([
            'posted' => true,
        ]);

        if ($balance->available_balance > 0) {
            $cashLedger->update([
                'approved_by' => $performedBy,
            ]);

            // Creates the actual balanced GlJournal debit/credit pair (debit
            // CLOSING_CASH_CONTROL, credit TELLER_CASH) -- same gap as
            // openTeller() above, same fix. Skipped for a zero balance for
            // the same reason: postFromCashLedger() correctly rejects
            // zero-value ledger entries.
            $this->glPostingService->postFromCashLedger($cashLedger);
        } else {
            $cashLedger->update([
                'status' => 'APPROVED',
                'approved_by' => $performedBy,
            ]);
        }

        $teller->update([
            'status' => 'CLOSED',
        ]);

        return $transaction->fresh();
    });
}


    /**
     * Get or create teller balance.
     */
    protected function getOrCreateBalance(
        Teller $teller
    ): TellerBalance {

        $balance = TellerBalance::where(
            'teller_id',
            $teller->id
        )
        ->lockForUpdate()
        ->first();

        if (!$balance) {

            $balance = TellerBalance::create([

                'teller_id'           => $teller->id,

                'currency'            => 'NGN',

                'ledger_balance'      => 0,

                'available_balance'   => 0,

                'locked_balance'      => 0,

                'last_transaction_id' => null,

            ]);

            $balance->refresh();
        }

        return $balance;
    }

    /**
     * Create teller transaction.
     */
    protected function createTransaction(
        Teller $teller,
        string $transactionType,
        float $amount,
        int $performedBy,
        ?string $reference,
        ?string $narration,
        $transactionDate
    ): TellerTransaction {

        $transaction = TellerTransaction::create([

            'teller_id'         => $teller->id,

            'transaction_no'    => $this->transactionNumberService
                                        ->generate('TLR'),

            'transaction_type'  => $transactionType,

            'amount'            => $amount,

            'currency'          => 'NGN',

            'reference'         => $reference,

            'narration'         => $narration,

            'performed_by'      => $performedBy,

            'approved_by'       => null,

            'transaction_date'  => $transactionDate,

            'posted'            => false,

        ]);

        $transaction = $transaction->refresh();

        event(new FinancialTransactionCreated($transaction));

        return $transaction;
    }

    /**
     * Post transaction to the General Ledger.
     */
    protected function postTransaction(
        TellerTransaction $transaction,
        CashLedger $cashLedger
    ): void {

        $this->glPostingService
            ->postFromCashLedger($cashLedger);

        $transaction->update([
            'posted' => true,
        ]);
    }
}
MBOS_EOF

cat > tests/Feature/GlChartMirrorTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\User;
use App\Services\Accounting\CustomerAccountGlResolver;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlChartMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    public function test_seeder_creates_all_59_accounts_with_no_duplicate_fineract_gl_id(): void
    {
        $this->assertEquals(59, GlAccount::count());
        $this->assertEquals(59, GlAccount::distinct('fineract_gl_id')->count('fineract_gl_id'));
    }

    protected function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function makeAccount(string $accountType, ?string $productCode): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        return CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'account_type' => $accountType,
            'product_code' => $productCode,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_resolver_defaults_savings_with_no_product_code_to_regular(): void
    {
        $account = $this->makeAccount('SAVINGS', null);

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_SAVINGS_REGULAR', $key);
    }

    public function test_resolver_resolves_kids_savings_product_correctly(): void
    {
        $account = $this->makeAccount('SAVINGS', 'KIDS');

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_SAVINGS_KIDS', $key);
    }

    public function test_resolver_defaults_current_with_no_product_code_to_individual(): void
    {
        $account = $this->makeAccount('CURRENT', null);

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_CURRENT_INDIVIDUAL', $key);
    }

    public function test_resolver_resolves_corporate_current_product_correctly(): void
    {
        $account = $this->makeAccount('CURRENT', 'CORPORATE');

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_CURRENT_CORPORATE', $key);
    }

    protected function makeFundedAccount(float $balance, string $accountType = 'SAVINGS', ?string $productCode = null): CustomerAccount
    {
        $account = $this->makeAccount($accountType, $productCode);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_fd_booking_at_standard_365_tenor_posts_to_that_exact_gl_account(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_365')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a journal entry against FIXED_DEPOSIT_365 for a 365-day booking.');
    }

    public function test_fd_booking_at_non_standard_45_day_tenor_buckets_to_nearest_standard_60(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 45, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_60')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a 45-day booking to bucket to the nearest standard tenor, FIXED_DEPOSIT_60.');
    }

    public function test_fd_booking_at_non_standard_400_day_tenor_buckets_to_nearest_standard_365(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 400, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_365')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a 400-day booking to bucket to the nearest standard tenor, FIXED_DEPOSIT_365.');
    }

    public function test_fd_booking_from_current_account_posts_to_current_gl_not_savings(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000, 'CURRENT', 'CORPORATE');

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 90, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'CUSTOMER_CURRENT_CORPORATE')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected booking from a CORPORATE current account to post against CUSTOMER_CURRENT_CORPORATE.');
    }
}
MBOS_EOF

echo "Zero-balance guard + stale test count fix applied. Next: php artisan test"