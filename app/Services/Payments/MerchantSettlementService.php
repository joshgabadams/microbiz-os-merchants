<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use App\Models\MerchantTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Settles a merchant's collected balance by crediting their own
 * customer_account within the system (payout rails/scheduling are a
 * separate, not-yet-decided concern -- this is the manual/on-demand
 * core movement that any batching would eventually call).
 *
 * GL treatment: debits MERCHANT_LIABILITY (reducing what the bank owes
 * the merchant) and credits CUSTOMER_SAVINGS or CUSTOMER_CURRENT
 * (increasing what the bank owes the merchant's own account instead) --
 * an internal transfer between two liability accounts, not new cash
 * entering or leaving the bank.
 */
class MerchantSettlementService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function settle(
        Merchant $merchant,
        float $amount,
        int $branchId,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Settlement amount must be greater than zero.');
        }

        if (! $merchant->customer_account_id) {
            throw new Exception("Merchant {$merchant->merchant_code} has no linked customer account for settlement.");
        }

        return DB::transaction(function () use (
            $merchant,
            $amount,
            $branchId,
            $performedBy,
            $reference,
            $narration
        ) {
            $merchantBalance = MerchantBalance::where('merchant_id', $merchant->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->first();

            if (! $merchantBalance) {
                throw new Exception("Balance record not found for merchant {$merchant->merchant_code}.");
            }

            if ($merchantBalance->available_balance < $amount) {
                throw new Exception('Insufficient merchant balance for settlement.');
            }

            $account = $merchant->customerAccount()->lockForUpdate()->first();

            if (! $account) {
                throw new Exception('Linked customer account not found.');
            }

            $narration = $narration ?? "Settlement payout to merchant {$merchant->merchant_code}";

            // --- Merchant side: reduce balance, record the transaction ---

            $merchantTransaction = MerchantTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_no' => $this->transactionNumberService->generate('MST'),
                'transaction_type' => 'SETTLEMENT',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($merchantTransaction));

            $merchantBalance->ledger_balance -= $amount;
            $merchantBalance->available_balance -= $amount;
            $merchantBalance->last_transaction_id = $merchantTransaction->id;
            $merchantBalance->save();

            // --- Customer account side: credit the merchant's own account ---
            //
            // Deliberately not using CustomerAccountService::deposit() here --
            // it hardcodes transaction_type to CASH_DEPOSIT, which would
            // misrepresent this as new cash entering the bank rather than an
            // internal transfer from the merchant liability pool.

            $accountBalance = CustomerAccountBalance::where('customer_account_id', $account->id)
                ->lockForUpdate()
                ->first();

            if (! $accountBalance) {
                $accountBalance = CustomerAccountBalance::create([
                    'customer_account_id' => $account->id,
                    'currency' => $account->currency ?? 'NGN',
                    'ledger_balance' => 0,
                    'available_balance' => 0,
                    'locked_balance' => 0,
                ])->refresh();
            }

            $customerAccountTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $account->id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_IN',
                'amount' => $amount,
                'currency' => $account->currency ?? 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($customerAccountTransaction));

            $accountBalance->ledger_balance += $amount;
            $accountBalance->available_balance += $amount;
            $accountBalance->last_transaction_id = $customerAccountTransaction->id;
            $accountBalance->save();

            $customerAccountTransaction->update(['posted' => true]);

            // --- GL posting: internal transfer between two liability accounts ---

            $creditKey = $account->account_type === 'CURRENT'
                ? 'CUSTOMER_CURRENT'
                : 'CUSTOMER_SAVINGS';

            $cashLedger = CashLedger::create([
                'reference_no' => $merchantTransaction->transaction_no,
                'branch_id' => $branchId,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'MERCHANT_SETTLEMENT',
                'source_type' => MerchantTransaction::class,
                'source_id' => $merchantTransaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'MERCHANT_LIABILITY',
                'account_code' => 'MERCHANT_LIABILITY',
                'debit_account_key' => 'MERCHANT_LIABILITY',
                'credit_account_key' => $creditKey,
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $merchantBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $merchantTransaction->update(['posted' => true]);

            // Fires FinancialTransactionPosted internally once the GL entry balances.
            $this->glPostingService->postFromCashLedger($cashLedger);

            return [
                'merchant_transaction' => $merchantTransaction->fresh(),
                'customer_account_transaction' => $customerAccountTransaction->fresh(),
                'merchant_balance' => $merchantBalance->fresh(),
                'customer_account_balance' => $accountBalance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}
