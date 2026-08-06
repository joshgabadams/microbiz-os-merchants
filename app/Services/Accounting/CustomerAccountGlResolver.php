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
