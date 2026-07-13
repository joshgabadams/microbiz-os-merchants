<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlAccount;
use Illuminate\Support\Facades\DB;

class GlAccountSeeder extends Seeder
{
    public function run(): void
    {
          DB::statement('SET FOREIGN_KEY_CHECKS=0');

    GlAccount::truncate();

    DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $accounts = [

            /*
            |--------------------------------------------------------------------------
            | INCOME (1xxxx)
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | EXPENSES (2xxxx)
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | ASSETS (3xxxx)
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | LIABILITIES (4xxxx)
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | EQUITY (5xxxx)
            |--------------------------------------------------------------------------
            */

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