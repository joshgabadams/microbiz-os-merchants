#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_04_000002_create_fixed_deposits_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposits', function (Blueprint $table) {

            $table->id();

            $table->string('fd_no')->unique();

            $table->foreignId('customer_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->foreignId('settlement_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->decimal('principal_amount', 24, 2);
            $table->string('currency', 3)->default('NGN');

            $table->decimal('interest_rate', 8, 4);

            $table->decimal('pre_liquidation_rate', 8, 4);

            $table->decimal('pre_liquidation_penalty_fee', 24, 2)->default(0);

            $table->unsignedInteger('tenor_days');
            $table->date('start_date');
            $table->date('maturity_date');

            $table->enum('status', [
                'ACTIVE',
                'LIQUIDATED_EARLY',
                'LIQUIDATED_AT_MATURITY',
            ])->default('ACTIVE');

            $table->foreignId('booked_by')->constrained('users');

            $table->foreignId('liquidated_by')->nullable()->constrained('users');
            $table->timestamp('liquidated_at')->nullable();
            $table->decimal('interest_paid', 24, 2)->nullable();

            $table->text('narration')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
MBOS_EOF

cat > config/gl.php << 'MBOS_EOF'
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CASH ASSETS
    |--------------------------------------------------------------------------
    */

    'VAULT_CASH'            => '300110',
    'TELLER_CASH'           => '300120',
    'CASH_IN_TRANSIT'       => '300130',
    'ATM_CASH'              => '300140',
    'TREASURY_VAULT'        => '300150',
    'INTERBRANCH_SETTLEMENT'=> '300160',
    'SUSPENSE_ASSET'        => '300170',

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER LIABILITIES
    |--------------------------------------------------------------------------
    */

    'CUSTOMER_SAVINGS'      => '400100',
    'CUSTOMER_CURRENT'      => '400110',
    'FIXED_DEPOSIT'         => '400120',
    'WALLET_LIABILITY'      => '400130',
    'AGENCY_FLOAT'          => '400140',
    'TELLER_OVER_SHORT'     => '400150',
    'SUSPENSE_LIABILITY'    => '400160',
    'MERCHANT_LIABILITY'    => '400170',
    'CUSTOMER_DEPOSIT_CONTROL'    => '400180',
    'CUSTOMER_WITHDRAWAL_CONTROL' => '400190',

/*
|--------------------------------------------------------------------------
| CONTROL ACCOUNTS
|--------------------------------------------------------------------------
|
| Temporary control accounts used during development.
| These will later be replaced with transaction-specific
| counterpart GL accounts.
|
*/

'CASH_CONTROL' => '300170',




    /*
    |--------------------------------------------------------------------------
    | INCOME
    |--------------------------------------------------------------------------
    */

    'INTEREST_INCOME'       => '100100',
    'TRANSFER_FEE'          => '100110',
    'WITHDRAWAL_FEE'        => '100120',
    'COMMISSION_INCOME'     => '100130',

    /*
    |--------------------------------------------------------------------------
    | EXPENSES
    |--------------------------------------------------------------------------
    */

    'SALARY_EXPENSE'        => '200100',
    'OFFICE_EXPENSE'        => '200110',
    'UTILITY_EXPENSE'       => '200120',
    'CASH_HANDLING_EXPENSE' => '200130',
    'INTEREST_EXPENSE'      => '200140',

    /*
    |--------------------------------------------------------------------------
    | EQUITY
    |--------------------------------------------------------------------------
    */

    'SHARE_CAPITAL'         => '500100',
    'RETAINED_EARNINGS'     => '500110',
    'CURRENT_YEAR_EARNINGS' => '500120',

];
MBOS_EOF

cat > database/seeders/GlAccountSeeder.php << 'MBOS_EOF'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlAccount;

class GlAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [

            [
                'fineract_gl_id' => 100100,
                'gl_code' => '100100',
                'name' => 'Interest Income',
                'type' => 'INCOME',
                'usage' => 'INTEREST_INCOME',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 100110,
                'gl_code' => '100110',
                'name' => 'Transfer Fee Income',
                'type' => 'INCOME',
                'usage' => 'TRANSFER_FEE',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 100120,
                'gl_code' => '100120',
                'name' => 'Withdrawal Fee Income',
                'type' => 'INCOME',
                'usage' => 'WITHDRAWAL_FEE',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 100130,
                'gl_code' => '100130',
                'name' => 'Commission Income',
                'type' => 'INCOME',
                'usage' => 'COMMISSION_INCOME',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],

            [
                'fineract_gl_id' => 200100,
                'gl_code' => '200100',
                'name' => 'Salary Expense',
                'type' => 'EXPENSE',
                'usage' => 'SALARY_EXPENSE',
                'manual_entries_allowed' => true,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 200110,
                'gl_code' => '200110',
                'name' => 'Office Expense',
                'type' => 'EXPENSE',
                'usage' => 'OFFICE_EXPENSE',
                'manual_entries_allowed' => true,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 200120,
                'gl_code' => '200120',
                'name' => 'Utility Expense',
                'type' => 'EXPENSE',
                'usage' => 'UTILITY_EXPENSE',
                'manual_entries_allowed' => true,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 200130,
                'gl_code' => '200130',
                'name' => 'Cash Handling Expense',
                'type' => 'EXPENSE',
                'usage' => 'CASH_HANDLING_EXPENSE',
                'manual_entries_allowed' => true,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 200140,
                'gl_code' => '200140',
                'name' => 'Interest Expense',
                'type' => 'EXPENSE',
                'usage' => 'INTEREST_EXPENSE',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],

            [
                'fineract_gl_id' => 300100,
                'gl_code' => '300100',
                'name' => 'Cash Control',
                'type' => 'ASSET',
                'usage' => 'CASH_CONTROL',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300110,
                'gl_code' => '300110',
                'name' => 'Branch Vault Cash',
                'type' => 'ASSET',
                'usage' => 'VAULT_CASH',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300120,
                'gl_code' => '300120',
                'name' => 'Teller Cash',
                'type' => 'ASSET',
                'usage' => 'TELLER_CASH',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300130,
                'gl_code' => '300130',
                'name' => 'Cash In Transit',
                'type' => 'ASSET',
                'usage' => 'CASH_IN_TRANSIT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300140,
                'gl_code' => '300140',
                'name' => 'ATM Cash',
                'type' => 'ASSET',
                'usage' => 'ATM_CASH',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300150,
                'gl_code' => '300150',
                'name' => 'Treasury Vault',
                'type' => 'ASSET',
                'usage' => 'TREASURY_VAULT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300160,
                'gl_code' => '300160',
                'name' => 'Interbranch Settlement',
                'type' => 'ASSET',
                'usage' => 'INTERBRANCH_SETTLEMENT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 300170,
                'gl_code' => '300170',
                'name' => 'Suspense Asset',
                'type' => 'ASSET',
                'usage' => 'SUSPENSE_ASSET',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],

            [
                'fineract_gl_id' => 400100,
                'gl_code' => '400100',
                'name' => 'Customer Savings',
                'type' => 'LIABILITY',
                'usage' => 'CUSTOMER_SAVINGS',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400110,
                'gl_code' => '400110',
                'name' => 'Customer Current',
                'type' => 'LIABILITY',
                'usage' => 'CUSTOMER_CURRENT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400120,
                'gl_code' => '400120',
                'name' => 'Fixed Deposit',
                'type' => 'LIABILITY',
                'usage' => 'FIXED_DEPOSIT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400130,
                'gl_code' => '400130',
                'name' => 'Wallet Liability',
                'type' => 'LIABILITY',
                'usage' => 'WALLET_LIABILITY',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400140,
                'gl_code' => '400140',
                'name' => 'Agency Float',
                'type' => 'LIABILITY',
                'usage' => 'AGENCY_FLOAT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400150,
                'gl_code' => '400150',
                'name' => 'Teller Over / Short',
                'type' => 'LIABILITY',
                'usage' => 'TELLER_OVER_SHORT',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400160,
                'gl_code' => '400160',
                'name' => 'Suspense Liability',
                'type' => 'LIABILITY',
                'usage' => 'SUSPENSE_LIABILITY',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400170,
                'gl_code' => '400170',
                'name' => 'Merchant Settlement Liability',
                'type' => 'LIABILITY',
                'usage' => 'MERCHANT_LIABILITY',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400180,
                'gl_code' => '400180',
                'name' => 'Customer Deposit Control',
                'type' => 'LIABILITY',
                'usage' => 'CUSTOMER_DEPOSIT_CONTROL',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 400190,
                'gl_code' => '400190',
                'name' => 'Customer Withdrawal Control',
                'type' => 'LIABILITY',
                'usage' => 'CUSTOMER_WITHDRAWAL_CONTROL',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],

            [
                'fineract_gl_id' => 500100,
                'gl_code' => '500100',
                'name' => 'Share Capital',
                'type' => 'EQUITY',
                'usage' => 'SHARE_CAPITAL',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 500110,
                'gl_code' => '500110',
                'name' => 'Retained Earnings',
                'type' => 'EQUITY',
                'usage' => 'RETAINED_EARNINGS',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],
            [
                'fineract_gl_id' => 500120,
                'gl_code' => '500120',
                'name' => 'Current Year Earnings',
                'type' => 'EQUITY',
                'usage' => 'CURRENT_YEAR_EARNINGS',
                'manual_entries_allowed' => false,
                'disabled' => false,
            ],

        ];

        foreach ($accounts as $account) {

            GlAccount::updateOrCreate(
                [
                    'gl_code' => $account['gl_code'],
                ],
                [
                    'fineract_gl_id' => $account['fineract_gl_id'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'usage' => $account['usage'],
                    'manual_entries_allowed' => $account['manual_entries_allowed'],
                    'disabled' => $account['disabled'],
                ]
            );

        }
    }
}
MBOS_EOF

cat > app/Models/FixedDeposit.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $fillable = [
        'fd_no',
        'customer_account_id',
        'settlement_account_id',
        'principal_amount',
        'currency',
        'interest_rate',
        'pre_liquidation_rate',
        'pre_liquidation_penalty_fee',
        'tenor_days',
        'start_date',
        'maturity_date',
        'status',
        'booked_by',
        'liquidated_by',
        'liquidated_at',
        'interest_paid',
        'narration',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'pre_liquidation_rate' => 'decimal:4',
        'pre_liquidation_penalty_fee' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'liquidated_at' => 'datetime',
    ];

    public function sourceAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function settlementAccount()
    {
        return $this->belongsTo(CustomerAccount::class, 'settlement_account_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function liquidatedBy()
    {
        return $this->belongsTo(User::class, 'liquidated_by');
    }
}
MBOS_EOF

mkdir -p app/Services/Deposits
cat > app/Services/Deposits/FixedDepositService.php << 'MBOS_EOF'
<?php

namespace App\Services\Deposits;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\FixedDeposit;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class FixedDepositService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function book(
        CustomerAccount $sourceAccount,
        CustomerAccount $settlementAccount,
        float $principal,
        float $interestRate,
        float $preLiquidationRate,
        float $penaltyFee,
        int $tenorDays,
        int $bookedBy,
        ?string $narration = null
    ): FixedDeposit {
        if ($principal <= 0) {
            throw new Exception('Principal amount must be greater than zero.');
        }

        if ($interestRate < 0 || $preLiquidationRate < 0) {
            throw new Exception('Interest rates cannot be negative.');
        }

        if ($tenorDays < 1) {
            throw new Exception('Tenor must be at least 1 day.');
        }

        if ($sourceAccount->status !== 'ACTIVE') {
            throw new Exception('Source customer account is not active.');
        }

        if ($settlementAccount->status !== 'ACTIVE') {
            throw new Exception('Settlement customer account is not active.');
        }

        return DB::transaction(function () use (
            $sourceAccount,
            $settlementAccount,
            $principal,
            $interestRate,
            $preLiquidationRate,
            $penaltyFee,
            $tenorDays,
            $bookedBy,
            $narration
        ) {
            $sourceBalance = CustomerAccountBalance::where('customer_account_id', $sourceAccount->id)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance) {
                throw new Exception('Source account balance record not found.');
            }

            if ($sourceBalance->available_balance < $principal) {
                throw new Exception('Insufficient balance to fund this fixed deposit.');
            }

            $narration = $narration ?? 'Fixed deposit booking';
            $startDate = Carbon::today();
            $maturityDate = $startDate->copy()->addDays($tenorDays);

            $fixedDeposit = FixedDeposit::create([
                'fd_no' => $this->generateFdNo(),
                'customer_account_id' => $sourceAccount->id,
                'settlement_account_id' => $settlementAccount->id,
                'principal_amount' => $principal,
                'currency' => 'NGN',
                'interest_rate' => $interestRate,
                'pre_liquidation_rate' => $preLiquidationRate,
                'pre_liquidation_penalty_fee' => $penaltyFee,
                'tenor_days' => $tenorDays,
                'start_date' => $startDate,
                'maturity_date' => $maturityDate,
                'status' => 'ACTIVE',
                'booked_by' => $bookedBy,
                'narration' => $narration,
            ]);

            $debitTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $sourceAccount->id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_OUT',
                'amount' => $principal,
                'currency' => 'NGN',
                'reference' => $fixedDeposit->fd_no,
                'narration' => $narration,
                'performed_by' => $bookedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
            ])->refresh();

            event(new FinancialTransactionCreated($debitTransaction));

            $sourceBalance->ledger_balance -= $principal;
            $sourceBalance->available_balance -= $principal;
            $sourceBalance->last_transaction_id = $debitTransaction->id;
            $sourceBalance->save();

            $sourceGlKey = $sourceAccount->account_type === 'CURRENT'
                ? 'CUSTOMER_CURRENT'
                : 'CUSTOMER_SAVINGS';

            $cashLedger = CashLedger::create([
                'reference_no' => $fixedDeposit->fd_no,
                'branch_id' => null,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $bookedBy,
                'transaction_type' => 'FIXED_DEPOSIT_BOOKING',
                'source_type' => FixedDeposit::class,
                'source_id' => $fixedDeposit->id,
                'entry_type' => 'DEBIT',
                'account_type' => $sourceGlKey,
                'account_code' => $sourceGlKey,
                'debit_account_key' => $sourceGlKey,
                'credit_account_key' => 'FIXED_DEPOSIT',
                'debit' => $principal,
                'credit' => 0,
                'running_balance' => $sourceBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            return $fixedDeposit->fresh(['sourceAccount', 'settlementAccount']);
        });
    }

    public function previewLiquidation(FixedDeposit $fixedDeposit, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        return $this->calculateLiquidation($fixedDeposit, $asOf);
    }

    /**
     * @throws Exception
     */
    public function liquidate(FixedDeposit $fixedDeposit, int $performedBy, ?string $narration = null): array
    {
        return DB::transaction(function () use ($fixedDeposit, $performedBy, $narration) {

            $fixedDeposit = FixedDeposit::query()
                ->lockForUpdate()
                ->findOrFail($fixedDeposit->id);

            if ($fixedDeposit->status !== 'ACTIVE') {
                throw new Exception("Fixed deposit {$fixedDeposit->fd_no} is already {$fixedDeposit->status}.");
            }

            $calc = $this->calculateLiquidation($fixedDeposit, Carbon::today());

            $settlementBalance = CustomerAccountBalance::where('customer_account_id', $fixedDeposit->settlement_account_id)
                ->lockForUpdate()
                ->first();

            if (! $settlementBalance) {
                throw new Exception('Settlement account balance record not found.');
            }

            $narration = $narration ?? ($calc['is_early']
                ? "Early liquidation of fixed deposit {$fixedDeposit->fd_no}"
                : "Maturity liquidation of fixed deposit {$fixedDeposit->fd_no}");

            $totalPayout = $calc['principal'] + $calc['net_interest'];

            $creditTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $fixedDeposit->settlement_account_id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_IN',
                'amount' => $totalPayout,
                'currency' => 'NGN',
                'reference' => $fixedDeposit->fd_no,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
            ])->refresh();

            event(new FinancialTransactionCreated($creditTransaction));

            $settlementBalance->ledger_balance += $totalPayout;
            $settlementBalance->available_balance += $totalPayout;
            $settlementBalance->last_transaction_id = $creditTransaction->id;
            $settlementBalance->save();

            $settlementAccount = CustomerAccount::findOrFail($fixedDeposit->settlement_account_id);
            $settlementGlKey = $settlementAccount->account_type === 'CURRENT'
                ? 'CUSTOMER_CURRENT'
                : 'CUSTOMER_SAVINGS';

            $principalLedger = CashLedger::create([
                'reference_no' => $fixedDeposit->fd_no,
                'branch_id' => null,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_PRINCIPAL',
                'source_type' => FixedDeposit::class,
                'source_id' => $fixedDeposit->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'FIXED_DEPOSIT',
                'account_code' => 'FIXED_DEPOSIT',
                'debit_account_key' => 'FIXED_DEPOSIT',
                'credit_account_key' => $settlementGlKey,
                'debit' => $calc['principal'],
                'credit' => 0,
                'running_balance' => $settlementBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $this->glPostingService->postFromCashLedger($principalLedger);

            if ($calc['net_interest'] > 0) {
                $interestLedger = CashLedger::create([
                    'reference_no' => $fixedDeposit->fd_no,
                    'branch_id' => null,
                    'vault_id' => null,
                    'teller_id' => null,
                    'user_id' => $performedBy,
                    'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_INTEREST',
                    'source_type' => FixedDeposit::class,
                    'source_id' => $fixedDeposit->id,
                    'entry_type' => 'DEBIT',
                    'account_type' => 'INTEREST_EXPENSE',
                    'account_code' => 'INTEREST_EXPENSE',
                    'debit_account_key' => 'INTEREST_EXPENSE',
                    'credit_account_key' => $settlementGlKey,
                    'debit' => $calc['net_interest'],
                    'credit' => 0,
                    'running_balance' => $settlementBalance->ledger_balance,
                    'currency' => 'NGN',
                    'narration' => $narration,
                    'status' => 'PENDING',
                    'approved_by' => null,
                    'transaction_date' => now(),
                ]);

                $this->glPostingService->postFromCashLedger($interestLedger);
            }

            if ($calc['fee_applied'] > 0) {
                $feeLedger = CashLedger::create([
                    'reference_no' => $fixedDeposit->fd_no,
                    'branch_id' => null,
                    'vault_id' => null,
                    'teller_id' => null,
                    'user_id' => $performedBy,
                    'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_FEE',
                    'source_type' => FixedDeposit::class,
                    'source_id' => $fixedDeposit->id,
                    'entry_type' => 'DEBIT',
                    'account_type' => 'INTEREST_EXPENSE',
                    'account_code' => 'INTEREST_EXPENSE',
                    'debit_account_key' => 'INTEREST_EXPENSE',
                    'credit_account_key' => 'COMMISSION_INCOME',
                    'debit' => $calc['fee_applied'],
                    'credit' => 0,
                    'running_balance' => 0,
                    'currency' => 'NGN',
                    'narration' => "Early liquidation penalty fee on {$fixedDeposit->fd_no}",
                    'status' => 'PENDING',
                    'approved_by' => null,
                    'transaction_date' => now(),
                ]);

                $this->glPostingService->postFromCashLedger($feeLedger);
            }

            $fixedDeposit->update([
                'status' => $calc['is_early'] ? 'LIQUIDATED_EARLY' : 'LIQUIDATED_AT_MATURITY',
                'liquidated_by' => $performedBy,
                'liquidated_at' => now(),
                'interest_paid' => $calc['net_interest'],
            ]);

            return [
                'fixed_deposit' => $fixedDeposit->fresh(),
                'settlement_transaction' => $creditTransaction->fresh(),
                'settlement_balance' => $settlementBalance->fresh(),
                'calculation' => $calc,
            ];
        });
    }

    protected function calculateLiquidation(FixedDeposit $fixedDeposit, Carbon $asOf): array
    {
        $principal = (float) $fixedDeposit->principal_amount;
        $startDate = Carbon::parse($fixedDeposit->start_date);
        $maturityDate = Carbon::parse($fixedDeposit->maturity_date);

        $isEarly = $asOf->lt($maturityDate);

        if ($isEarly) {
            $elapsedDays = max(0, $startDate->diffInDays($asOf));
            $rate = (float) $fixedDeposit->pre_liquidation_rate;
            $grossInterest = $principal * ($rate / 100) * ($elapsedDays / 365);

            $fee = min((float) $fixedDeposit->pre_liquidation_penalty_fee, $grossInterest);
            $netInterest = round($grossInterest - $fee, 2);

            return [
                'is_early' => true,
                'elapsed_days' => $elapsedDays,
                'rate_applied' => $rate,
                'principal' => $principal,
                'gross_interest' => round($grossInterest, 2),
                'fee_applied' => round($fee, 2),
                'net_interest' => max(0, $netInterest),
                'total_payout' => $principal + max(0, $netInterest),
            ];
        }

        $elapsedDays = $fixedDeposit->tenor_days;
        $rate = (float) $fixedDeposit->interest_rate;
        $grossInterest = round($principal * ($rate / 100) * ($elapsedDays / 365), 2);

        return [
            'is_early' => false,
            'elapsed_days' => $elapsedDays,
            'rate_applied' => $rate,
            'principal' => $principal,
            'gross_interest' => $grossInterest,
            'fee_applied' => 0,
            'net_interest' => $grossInterest,
            'total_payout' => $principal + $grossInterest,
        ];
    }

    protected function generateFdNo(): string
    {
        do {
            $fdNo = 'FD-'.strtoupper(Str::random(8));
        } while (FixedDeposit::where('fd_no', $fdNo)->exists());

        return $fdNo;
    }
}
MBOS_EOF

mkdir -p app/Http/Requests/FixedDeposit
cat > app/Http/Requests/FixedDeposit/BookFixedDepositRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\FixedDeposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookFixedDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'settlement_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pre_liquidation_penalty_fee' => ['nullable', 'numeric', 'min:0'],
            'tenor_days' => ['required', 'integer', 'min:1'],
            'narration' => ['nullable', 'string'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Requests/FixedDeposit/LiquidateFixedDepositRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\FixedDeposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LiquidateFixedDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'narration' => ['nullable', 'string'],
        ];
    }
}
MBOS_EOF

cat > app/Http/Controllers/Api/FixedDepositController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedDeposit\BookFixedDepositRequest;
use App\Http\Requests\FixedDeposit\LiquidateFixedDepositRequest;
use App\Models\CustomerAccount;
use App\Models\FixedDeposit;
use App\Services\Deposits\FixedDepositService;
use App\Traits\ApiResponse;
use Exception;

class FixedDepositController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FixedDepositService $fixedDepositService
    ) {
    }

    public function index()
    {
        return $this->success(
            FixedDeposit::with(['sourceAccount', 'settlementAccount'])->latest()->get(),
            'Fixed deposits retrieved successfully.'
        );
    }

    public function show(FixedDeposit $fixedDeposit)
    {
        return $this->success(
            $fixedDeposit->load(['sourceAccount', 'settlementAccount']),
            'Fixed deposit retrieved successfully.'
        );
    }

    public function book(BookFixedDepositRequest $request)
    {
        try {
            $sourceAccount = CustomerAccount::findOrFail($request->customer_account_id);
            $settlementAccount = CustomerAccount::findOrFail($request->settlement_account_id);

            $fixedDeposit = $this->fixedDepositService->book(
                $sourceAccount,
                $settlementAccount,
                (float) $request->principal_amount,
                (float) $request->interest_rate,
                (float) $request->pre_liquidation_rate,
                (float) ($request->pre_liquidation_penalty_fee ?? 0),
                (int) $request->tenor_days,
                $request->user()->id,
                $request->narration
            );

            return $this->success($fixedDeposit, 'Fixed deposit booked successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function previewLiquidation(FixedDeposit $fixedDeposit)
    {
        try {
            $preview = $this->fixedDepositService->previewLiquidation($fixedDeposit);

            return $this->success($preview, 'Liquidation preview calculated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function liquidate(LiquidateFixedDepositRequest $request, FixedDeposit $fixedDeposit)
    {
        try {
            $result = $this->fixedDepositService->liquidate(
                $fixedDeposit,
                $request->user()->id,
                $request->narration
            );

            return $this->success($result, 'Fixed deposit liquidated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
MBOS_EOF

cat > database/seeders/RbacSeeder.php << 'MBOS_EOF'
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vaults.manage' => 'vault',
            'tellers.manage' => 'teller',
            'approvals.create' => 'approval',
            'approvals.approve' => 'approval',
            'approvals.reject' => 'approval',
            'customer_cash.deposit' => 'customer-cash',
            'customer_cash.withdraw' => 'customer-cash',
            'branch_eod.close' => 'branch',
            'gl.sync' => 'gl',
            'offices.sync' => 'sync',
            'fixed_deposits.book' => 'fixed-deposit',
            'fixed_deposits.liquidate' => 'fixed-deposit',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(['name' => $name], ['module' => $module]);
        }

        $roles = [
            'admin' => [
                'label' => 'Platform Administrator',
                'is_system' => true,
                'permissions' => array_keys($permissions),
            ],
            'teller-officer' => [
                'label' => 'Teller Officer',
                'permissions' => ['tellers.manage', 'customer_cash.deposit', 'customer_cash.withdraw'],
            ],
            'deposit-officer' => [
                'label' => 'Deposit Officer',
                'permissions' => ['fixed_deposits.book', 'fixed_deposits.liquidate'],
            ],
            'vault-officer' => [
                'label' => 'Vault Officer',
                'permissions' => ['vaults.manage', 'approvals.create'],
            ],
            'branch-manager' => [
                'label' => 'Branch Manager',
                'permissions' => ['approvals.approve', 'approvals.reject', 'branch_eod.close'],
            ],
            'compliance-officer' => [
                'label' => 'Compliance Officer',
                'permissions' => ['gl.sync', 'offices.sync'],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label'], 'is_system' => $config['is_system'] ?? false],
            );

            $permissionIds = Permission::whereIn('name', $config['permissions'])->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $firstUser = User::first();

        if ($firstUser) {
            $adminRole = Role::where('name', 'admin')->first();
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);

            $this->command->info("Attached 'admin' role to existing user: {$firstUser->email}");
        } else {
            $this->command->warn('No existing user found -- run this after your user seeder, or create a user and re-run.');
        }
    }
}
MBOS_EOF

cat > routes/api.php << 'MBOS_EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfficeSyncController;
use App\Http\Controllers\Api\GlAccountSyncController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\TellerController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\CustomerCashController;
use App\Http\Controllers\Api\BalancingController;
use App\Http\Controllers\Api\BranchEodController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\FixedDepositController;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sync/offices', [OfficeSyncController::class, 'sync'])
        ->middleware('permission:offices.sync');
    Route::get('/sync/glaccounts', [GlAccountSyncController::class, 'sync'])
        ->middleware('permission:gl.sync');

    Route::apiResource('vaults', VaultController::class)->only(['index', 'show']);
    Route::apiResource('vaults', VaultController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:vaults.manage');

    Route::apiResource('tellers', TellerController::class)->only(['index', 'show']);
    Route::apiResource('tellers', TellerController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:tellers.manage');

    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/mfa/setup', [MfaController::class, 'setup']);
        Route::post('/mfa/enable', [MfaController::class, 'enable']);
        Route::post('/mfa/disable', [MfaController::class, 'disable']);

        Route::post('/teller/open', [TellerController::class, 'open'])
            ->middleware('permission:tellers.manage');
        Route::post('/teller/close', [TellerController::class, 'close'])
            ->middleware('permission:tellers.manage');

        Route::post('/float/allocate/request', [ApprovalController::class, 'requestAllocateFloat'])
            ->middleware('permission:approvals.create');
        Route::post('/float/return/request', [ApprovalController::class, 'requestReturnFloat'])
            ->middleware('permission:approvals.create');

        Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
        Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])
            ->middleware('permission:approvals.approve');
        Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])
            ->middleware('permission:approvals.reject');

        Route::post('/customer/deposit', [CustomerCashController::class, 'deposit'])
            ->middleware('permission:customer_cash.deposit');
        Route::post('/customer/withdraw', [CustomerCashController::class, 'withdraw'])
            ->middleware('permission:customer_cash.withdraw');

        Route::post('/teller/balance', [BalancingController::class, 'tellerBalance'])
            ->middleware('permission:tellers.manage');
        Route::post('/vault/balance', [BalancingController::class, 'vaultBalance'])
            ->middleware('permission:vaults.manage');

        Route::post('/branch/eod', [BranchEodController::class, 'close'])
            ->middleware('permission:branch_eod.close');

        Route::get('/merchants', [MerchantController::class, 'index']);
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show']);

        Route::post('/merchants/onboard', [MerchantController::class, 'onboard'])
            ->middleware('permission:merchants.onboard');

        Route::post('/merchants/{merchant}/submit', [MerchantController::class, 'submit'])
            ->middleware('permission:merchants.submit');

        Route::post('/merchants/{merchant}/approve', [MerchantController::class, 'approve'])
            ->middleware('permission:merchants.approve');

        Route::post('/merchants/{merchant}/reject', [MerchantController::class, 'reject'])
            ->middleware('permission:merchants.reject');

        Route::post('/merchants/{merchant}/activate', [MerchantController::class, 'activate'])
            ->middleware('permission:merchants.activate');

        Route::post('/merchants/{merchant}/suspend', [MerchantController::class, 'suspend'])
            ->middleware('permission:merchants.suspend');

        Route::post('/merchants/{merchant}/reactivate', [MerchantController::class, 'reactivate'])
            ->middleware('permission:merchants.reactivate');

        Route::post('/merchants/{merchant}/deactivate', [MerchantController::class, 'deactivate'])
            ->middleware('permission:merchants.deactivate');

        Route::post('/merchants/collect/qr', [MerchantController::class, 'collectQr'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/collect/pos', [MerchantController::class, 'collectPos'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/settle', [MerchantController::class, 'settle'])
            ->middleware('permission:merchants.settle');

        Route::get('/wallets', [WalletController::class, 'index']);
        Route::get('/wallets/{wallet}', [WalletController::class, 'show']);

        Route::post('/wallets/onboard', [WalletController::class, 'onboard'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/topup', [WalletController::class, 'topUp'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/transfer', [WalletController::class, 'transfer'])
            ->middleware('permission:wallets.manage');

        Route::get('/reports/teller-transactions', [ReportController::class, 'tellerTransactions']);
        Route::get('/reports/vault-transactions', [ReportController::class, 'vaultTransactions']);
        Route::get('/reports/teller-ledger', [ReportController::class, 'tellerLedger']);
        Route::get('/reports/vault-ledger', [ReportController::class, 'vaultLedger']);

        Route::get('/fixed-deposits', [FixedDepositController::class, 'index']);
        Route::get('/fixed-deposits/{fixedDeposit}', [FixedDepositController::class, 'show']);
        Route::get('/fixed-deposits/{fixedDeposit}/preview-liquidation', [FixedDepositController::class, 'previewLiquidation']);

        Route::post('/fixed-deposits/book', [FixedDepositController::class, 'book'])
            ->middleware('permission:fixed_deposits.book');

        Route::post('/fixed-deposits/{fixedDeposit}/liquidate', [FixedDepositController::class, 'liquidate'])
            ->middleware('permission:fixed_deposits.liquidate');
    });
});
MBOS_EOF

cat > tests/Feature/FixedDepositTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\User;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeFundedAccount(float $balance): CustomerAccount
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
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_booking_debits_source_account_and_creates_active_fd(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 500, 365, $user->id
        );

        $this->assertEquals('ACTIVE', $fd->status);
        $this->assertEquals(100000, (float) $fd->principal_amount);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(100000, (float) $sourceBalance->available_balance);
    }

    public function test_booking_rejects_insufficient_source_balance(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(50000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 500, 365, $user->id
        );
    }

    public function test_liquidation_at_full_maturity_pays_correct_simple_interest(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-MATURE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals('LIQUIDATED_AT_MATURITY', $result['fixed_deposit']->status);
        $this->assertEquals(10000, (float) $result['calculation']['net_interest']);
        $this->assertEquals(110000, (float) $result['calculation']['total_payout']);
        $this->assertEquals(0, (float) $result['calculation']['fee_applied']);
    }

    public function test_early_liquidation_applies_reduced_rate_and_penalty_fee(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-EARLY',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 50,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
        $this->assertEquals(164.38, (float) $result['calculation']['gross_interest']);
        $this->assertEquals(50, (float) $result['calculation']['fee_applied']);
        $this->assertEquals(114.38, (float) $result['calculation']['net_interest']);
    }

    public function test_early_liquidation_fee_never_makes_interest_negative(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-FEE-EXCEEDS',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals(0, (float) $result['calculation']['net_interest']);
        $this->assertEquals(100000, (float) $result['calculation']['total_payout']);
    }

    public function test_already_liquidated_fd_cannot_be_liquidated_again(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-DOUBLE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 50000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 90,
            'start_date' => now()->subDays(90),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $service = app(FixedDepositService::class);
        $service->liquidate($fd, $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already');

        $service->liquidate($fd->fresh(), $user->id);
    }

    public function test_settlement_account_receives_principal_plus_interest(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);
        $settlement = $this->makeFundedAccount(0);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-SETTLEMENT',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $settlement->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        app(FixedDepositService::class)->liquidate($fd, $user->id);

        $settlementBalance = CustomerAccountBalance::where('customer_account_id', $settlement->id)->first();

        $this->assertEquals(110000, (float) $settlementBalance->available_balance);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(200000, (float) $sourceBalance->available_balance);
    }
}
MBOS_EOF

echo "Fixed Deposit backend applied. Next: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\RbacSeeder\" && php artisan test --filter=FixedDepositTest"
