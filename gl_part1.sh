#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_04_000003_add_product_code_to_customer_accounts.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->string('product_code')->nullable()->after('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->dropColumn('product_code');
        });
    }
};
MBOS_EOF

cat > app/Services/Accounting/CustomerAccountGlResolver.php << 'MBOS_EOF'
<?php

namespace App\Services\Accounting;

use App\Models\CustomerAccount;

/**
 * Resolves the correct GL key for a customer account, matching Fineract's
 * real chart of accounts (General_ledger_Listing-2.xlsx), which
 * differentiates by savings/current PRODUCT, not just account_type.
 *
 * Used by every service that posts to a customer's own account:
 * CustomerCashService, MerchantSettlementService, FixedDepositService,
 * TransactionReversalService -- kept in one place so a future product
 * addition or GL remap only needs to change here, not four files.
 */
class CustomerAccountGlResolver
{
    protected array $savingsMap = [
        'REGULAR' => 'CUSTOMER_SAVINGS_REGULAR',
        'KIDS' => 'CUSTOMER_SAVINGS_KIDS',
        'MASTA' => 'CUSTOMER_SAVINGS_MASTA',
        'MYBIZ' => 'CUSTOMER_SAVINGS_MYBIZ',
        'ACTIVE' => 'CUSTOMER_SAVINGS_ACTIVE',
        'EDUCATION' => 'CUSTOMER_SAVINGS_EDUCATION',
        'GROUP' => 'CUSTOMER_SAVINGS_GROUP',
        'SALARY' => 'CUSTOMER_SAVINGS_SALARY',
        'CORPORATE' => 'CUSTOMER_SAVINGS_CORPORATE',
        'MYKONNECT' => 'CUSTOMER_SAVINGS_MYKONNECT',
        'PEAK_DAILY' => 'CUSTOMER_SAVINGS_PEAK_DAILY',
        'PEAK_GROUP' => 'CUSTOMER_SAVINGS_PEAK_GROUP',
        'PEAK_SALARY' => 'CUSTOMER_SAVINGS_PEAK_SALARY',
        'PEAK_TRADERS' => 'CUSTOMER_SAVINGS_PEAK_TRADERS',
        'MICROFLEX' => 'CUSTOMER_SAVINGS_MICROFLEX',
        'YES' => 'CUSTOMER_SAVINGS_YES',
    ];

    protected array $currentMap = [
        'INDIVIDUAL' => 'CUSTOMER_CURRENT_INDIVIDUAL',
        'CORPORATE' => 'CUSTOMER_CURRENT_CORPORATE',
        'SALARY' => 'CUSTOMER_CURRENT_SALARY',
        'STAFF' => 'CUSTOMER_CURRENT_STAFF',
    ];

    public function resolve(CustomerAccount $account): string
    {
        if ($account->account_type === 'CURRENT') {
            return $this->currentMap[$account->product_code]
                ?? 'CUSTOMER_CURRENT_INDIVIDUAL';
        }

        return $this->savingsMap[$account->product_code]
            ?? 'CUSTOMER_SAVINGS_REGULAR';
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
| NOTE: TellerTransactionService (as it exists in the working sandbox
| copy at time of this update) references two keys -- OPENING_CASH_CONTROL
| and CLOSING_CASH_CONTROL -- that do NOT exist in this file as actually
| deployed. Not added here since no real value is known; flagged
| separately as its own finding, not guessed at.
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
    'INTEREST_EXPENSE'      => '2-11-100',  // CONFIRMED: INTEREST EXPENSE (parent) -- generic/non-FD interest expense -- NEW this update, not previously in your file

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

echo "Part 1 of 4 applied (migration, resolver, config/gl.php)."