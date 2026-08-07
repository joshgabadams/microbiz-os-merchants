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
use App\Services\Accounting\CustomerAccountGlResolver;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class MerchantSettlementService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService,
        protected CustomerAccountGlResolver $glResolver
    ) {
    }

    /**
     * @throws Exception
     */
    public function settle(
        Merchant $merchant,
        float $amount,
        string $idempotencyKey,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Settlement amount must be greater than zero.');
        }

        if ($merchant->status !== 'ACTIVE') {
            throw new Exception("Merchant {$merchant->merchant_code} is not active.");
        }

        if (! $merchant->customer_account_id) {
            throw new Exception("Merchant {$merchant->merchant_code} has no linked customer account for settlement.");
        }

        if (! $merchant->branch_id) {
            throw new Exception("Merchant {$merchant->merchant_code} has no assigned branch; cannot process settlement.");
        }

        $existing = MerchantTransaction::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return [
                'merchant_transaction' => $existing,
                'customer_account_transaction' => null,
                'merchant_balance' => MerchantBalance::where('merchant_id', $merchant->id)
                    ->where('currency', 'NGN')
                    ->first(),
                'customer_account_balance' => null,
                'cash_ledger' => CashLedger::where('source_type', MerchantTransaction::class)
                    ->where('source_id', $existing->id)
                    ->first(),
            ];
        }

        return DB::transaction(function () use (
            $merchant,
            $amount,
            $idempotencyKey,
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

            // Settlement draws from locked_balance (collected, pending
            // settlement) -- not available_balance, which stays untouched
            // until the real hold/reserve system (Settlement module) exists.
            if ($merchantBalance->locked_balance < $amount) {
                throw new Exception('Insufficient merchant balance for settlement.');
            }

            $account = $merchant->customerAccount()->lockForUpdate()->first();

            if (! $account) {
                throw new Exception('Linked customer account not found.');
            }

            $narration = $narration ?? "Settlement payout to merchant {$merchant->merchant_code}";

            $merchantTransaction = MerchantTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_no' => $this->transactionNumberService->generate('MST'),
                'idempotency_key' => $idempotencyKey,
                'transaction_type' => 'SETTLEMENT',
                'status' => 'INITIATED',
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
            $merchantBalance->locked_balance -= $amount;
            $merchantBalance->last_transaction_id = $merchantTransaction->id;
            $merchantBalance->save();

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

            $creditKey = $this->glResolver->resolve($account);

            $cashLedger = CashLedger::create([
                'reference_no' => $merchantTransaction->transaction_no,
                'branch_id' => $merchant->branch_id,
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

            $merchantTransaction->update(['posted' => true, 'status' => 'SUCCESSFUL']);

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
