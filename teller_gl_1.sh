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

$cashLedger->update([
    'approved_by' => $performedBy,
]);

// Creates the actual balanced GlJournal debit/credit pair (debit
// TELLER_CASH, credit OPENING_CASH_CONTROL) and fires
// FinancialTransactionPosted internally -- this used to be skipped
// entirely (the CashLedger row was manually flipped to APPROVED
// with no journal entries ever created, a real gap found while
// investigating the OPENING_CASH_CONTROL/CLOSING_CASH_CONTROL keys).
$this->glPostingService->postFromCashLedger($cashLedger);

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

        $cashLedger->update([
            'approved_by' => $performedBy,
        ]);

        // Creates the actual balanced GlJournal debit/credit pair (debit
        // CLOSING_CASH_CONTROL, credit TELLER_CASH) -- same gap as
        // openTeller() above, same fix.
        $this->glPostingService->postFromCashLedger($cashLedger);

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

cat > config/gl.php << 'MBOS_EOF'
<?php

/*
|--------------------------------------------------------------------------
| GL Account Key Mapping -- Real Fineract Codes
|--------------------------------------------------------------------------
|
| Updated to mirror the real Fineract chart of accounts
| (General_ledger_Listing-2.xlsx, 535 accounts, format X-YY-ZZZ).
| Codes are grouped by confidence:
|
|   - CONFIRMED: exact or user-confirmed match to a real Fineract account.
|   - PLACEHOLDER: no real Fineract equivalent was found in the export.
|     Kept on the prior internal synthetic code until Fineract's chart is
|     extended to cover it (e.g. Wallet, Merchant, Agency Banking are new
|     MicroBiz OS products not yet reflected in Fineract's own chart).
|   - AMBIGUOUS: a real account may exist but the match was not confident
|     enough to apply without confirmation. Kept on the prior synthetic
|     code pending a decision.
|
| fineract_gl_id in the seeder is a SYNTHETIC integer derived by stripping
| the dashes from the real code (e.g. "4-00-214" -> 400214), since the
| export only provides the human-readable code, not Fineract's own
| internal numeric ID, and our fineract_gl_id column is a bigint.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | CASH ASSETS -- CONFIRMED
    |--------------------------------------------------------------------------
    */

    'VAULT_CASH'            => '3-13-998',  // Cash At Vault
    'TELLER_CASH'           => '3-13-999',  // Cash At Teller
    'CASH_IN_TRANSIT'       => '3-13-465',  // Transit Account

    /*
    |--------------------------------------------------------------------------
    | CASH ASSETS -- PLACEHOLDER / AMBIGUOUS (no confident real match)
    |--------------------------------------------------------------------------
    */

    'ATM_CASH'              => '300140',  // PLACEHOLDER: no ATM cash-holding asset account found in export
    'TREASURY_VAULT'        => '300150',  // AMBIGUOUS: possibly 3-12-402 "Treasury Notes/Vault" -- not confirmed
    'INTERBRANCH_SETTLEMENT'=> '300160',  // PLACEHOLDER: export's *_SETTLEMENT accounts are external bank relationships, not interbranch
    'SUSPENSE_ASSET'        => '300170',  // AMBIGUOUS: possibly 3-13-453 "Shortage/Overage Suspense" -- overlaps with TELLER_OVER_SHORT, not confirmed

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER SAVINGS -- CONFIRMED, product-specific per real chart
    |--------------------------------------------------------------------------
    | Resolved via App\Services\Accounting\CustomerAccountGlResolver, keyed
    | on customer_accounts.product_code. Falls back to REGULAR when
    | product_code is null (e.g. accounts created before this migration).
    */

    'CUSTOMER_SAVINGS_REGULAR'     => '4-00-147',  // Regular Savings Acct
    'CUSTOMER_SAVINGS_KIDS'        => '4-00-148',  // Kids Savings
    'CUSTOMER_SAVINGS_MASTA'       => '4-00-149',  // MASTA Savings Acct
    'CUSTOMER_SAVINGS_MYBIZ'       => '4-00-150',  // MYBIZ Savings Acct
    'CUSTOMER_SAVINGS_ACTIVE'      => '4-00-151',  // Active Savings Account
    'CUSTOMER_SAVINGS_EDUCATION'   => '4-00-152',  // Education Savings Acct
    'CUSTOMER_SAVINGS_GROUP'       => '4-00-153',  // Group Savings Acct
    'CUSTOMER_SAVINGS_SALARY'      => '4-00-154',  // Salary Savings Acct
    'CUSTOMER_SAVINGS_CORPORATE'   => '4-00-155',  // Savings Corporate Acct
    'CUSTOMER_SAVINGS_MYKONNECT'   => '4-00-156',  // MyKonnect Savings
    'CUSTOMER_SAVINGS_PEAK_DAILY'  => '4-00-157',  // Peak Daily Savings
    'CUSTOMER_SAVINGS_PEAK_GROUP'  => '4-00-158',  // Peak Group Savings
    'CUSTOMER_SAVINGS_PEAK_SALARY' => '4-00-160',  // Peak Salary Saving
    'CUSTOMER_SAVINGS_PEAK_TRADERS'=> '4-00-161',  // Peak Traders Savings
    'CUSTOMER_SAVINGS_MICROFLEX'   => '4-00-163',  // MicroFlex Target Savings Acct
    'CUSTOMER_SAVINGS_YES'         => '4-00-166',  // YES ACCOUNT (Youth Enterprise Savings)

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER CURRENT -- CONFIRMED, product-specific per real chart
    |--------------------------------------------------------------------------
    */

    'CUSTOMER_CURRENT_INDIVIDUAL'  => '4-00-107',  // Individual Current Acct
    'CUSTOMER_CURRENT_CORPORATE'   => '4-00-108',  // Corporate Current
    'CUSTOMER_CURRENT_SALARY'      => '4-00-109',  // Salary Current Acct
    'CUSTOMER_CURRENT_STAFF'       => '4-00-110',  // Staff Current Acct

    /*
    |--------------------------------------------------------------------------
    | FIXED DEPOSIT -- CONFIRMED, tenor-specific per real chart
    |--------------------------------------------------------------------------
    | Resolved via FixedDepositService's tenor-bucketing (nearest of the 5
    | standard tenors below) -- Fineract's chart only has these 5 leaf
    | accounts, so any booked tenor_days maps to whichever is closest.
    */

    'FIXED_DEPOSIT_60'      => '4-00-214',  // Fixed Deposits 60 Days
    'FIXED_DEPOSIT_90'      => '4-00-215',  // Fixed Deposits 90 Days
    'FIXED_DEPOSIT_180'     => '4-00-216',  // Fixed Deposits 180 Days
    'FIXED_DEPOSIT_270'     => '4-00-217',  // Fixed Deposits 270 Days
    'FIXED_DEPOSIT_365'     => '4-00-218',  // Fixed Deposits 365 Days

    /*
    |--------------------------------------------------------------------------
    | OTHER LIABILITIES
    |--------------------------------------------------------------------------
    */

    'WALLET_LIABILITY'      => '400130',  // PLACEHOLDER: Wallets are a new M-PAY product, no Fineract equivalent yet
    'AGENCY_FLOAT'          => '400140',  // PLACEHOLDER: Agency Banking not yet built, no Fineract equivalent yet
    'TELLER_OVER_SHORT'     => '400150',  // Kept as LIABILITY per confirmed decision -- real chart's only close match
                                           // (3-13-453 Shortage/Overage Suspense) is filed as an ASSET, not used here
    'SUSPENSE_LIABILITY'    => '400160',  // AMBIGUOUS: possibly 4-00-100 "VA SUSPENSE ACCOUNT" -- not confirmed
    'MERCHANT_LIABILITY'    => '400170',  // PLACEHOLDER: Merchant Payments is a new M-PAY product, no Fineract equivalent yet
    'CUSTOMER_DEPOSIT_CONTROL'    => '400180',  // PLACEHOLDER: MicroBiz-OS-internal control account, not a Fineract concept
    'CUSTOMER_WITHDRAWAL_CONTROL' => '400190',  // PLACEHOLDER: MicroBiz-OS-internal control account, not a Fineract concept

    /*
    |--------------------------------------------------------------------------
    | CONTROL ACCOUNTS
    |--------------------------------------------------------------------------
    |
    | Temporary control accounts used during development.
    | These will later be replaced with transaction-specific
    | counterpart GL accounts. Live dependency: VaultTransactionService.
    |
    */

    'CASH_CONTROL' => '300170',

    /*
    |--------------------------------------------------------------------------
    | TELLER OPEN/CLOSE CONTROL ACCOUNTS
    |--------------------------------------------------------------------------
    |
    | Found missing entirely while investigating a live gap:
    | TellerTransactionService::openTeller()/closeTeller() referenced these
    | two keys but never actually called GlPostingService::postFromCashLedger(),
    | so no GlJournal entries were ever created for teller open/close --
    | confirmed live via Tinker (GlJournal::count() unchanged after a real
    | openTeller() call). Both fixed now to actually post.
    |
    | PLACEHOLDER, same status as CASH_CONTROL: no confident real Fineract
    | equivalent found. Confirmed design: teller opening is a standalone
    | declaration, NOT a vault-to-teller transfer (that's a separate,
    | already-working flow via VaultTellerFloatService::allocateFloat()) --
    | so the counterparty here is a control account, not VAULT_CASH
    | directly, to avoid double-counting the same cash movement twice.
    |
    */

    'OPENING_CASH_CONTROL' => '300180',
    'CLOSING_CASH_CONTROL' => '300190',

    /*
    |--------------------------------------------------------------------------
    | INCOME
    |--------------------------------------------------------------------------
    */

    'INTEREST_INCOME'       => '100100',  // AMBIGUOUS: real chart splits by source (loans/treasury bills/call deposits/bank savings), no single umbrella found
    'TRANSFER_FEE'          => '1-11-156',  // CONFIRMED: Commission on Electronic Funds Transfer
    'WITHDRAWAL_FEE'        => '100120',  // PLACEHOLDER: no distinct withdrawal fee income line found in export
    'COMMISSION_INCOME'     => '1-11-150',  // CONFIRMED: COMMISSION (parent)

    /*
    |--------------------------------------------------------------------------
    | EXPENSES
    |--------------------------------------------------------------------------
    */

    'SALARY_EXPENSE'        => '2-11-002',  // CONFIRMED: Basic Salaries
    'OFFICE_EXPENSE'        => '2-11-208',  // CONFIRMED: Office & General Expenses
    'UTILITY_EXPENSE'       => '200120',  // AMBIGUOUS: closest match "2-11-023 Utility Allowance" reads as a staff allowance, not an office utility bill -- not confirmed
    'CASH_HANDLING_EXPENSE' => '2-11-291',  // CONFIRMED: Cash Specie Expenses
    'INTEREST_EXPENSE'      => '2-11-100',  // CONFIRMED: INTEREST EXPENSE (parent) -- generic/non-FD interest expense

    /*
    |--------------------------------------------------------------------------
    | INTEREST EXPENSE -- FIXED DEPOSIT, tenor-specific per real chart
    |--------------------------------------------------------------------------
    */

    'INTEREST_EXPENSE_FD_60'   => '2-11-133',  // Int Exp- Fixed Dep(60 Dys)
    'INTEREST_EXPENSE_FD_90'   => '2-11-134',  // Int Exp - Fixed Dep(90 Dys)
    'INTEREST_EXPENSE_FD_180'  => '2-11-135',  // Int Exp- Fixed Dep(180 Dys)
    'INTEREST_EXPENSE_FD_270'  => '2-11-136',  // Int Exp - Fixed Dep(270 Dys)
    'INTEREST_EXPENSE_FD_365'  => '2-11-137',  // Int Exp - Fixed Dep(365 Dys)

    /*
    |--------------------------------------------------------------------------
    | EQUITY
    |--------------------------------------------------------------------------
    */

    'SHARE_CAPITAL'         => '5-00-001',  // CONFIRMED: Paid Up Capital
    'RETAINED_EARNINGS'     => '5-00-045',  // CONFIRMED: Retained Earnings P/L
    'CURRENT_YEAR_EARNINGS' => '500120',  // PLACEHOLDER: not tracked as a distinct line from Retained Earnings in export

];
MBOS_EOF

echo "Teller GL fix Part 1 of 2 applied (service, config)."