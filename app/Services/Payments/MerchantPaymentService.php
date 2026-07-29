<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use App\Models\MerchantTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Handles merchant QR and POS collection flows.
 *
 * Follows the same shape as TellerTransactionService / VaultTransactionService:
 * create the domain transaction, fire FinancialTransactionCreated, update the
 * merchant's running balance, then post a CashLedger entry through
 * GlPostingService (which fires FinancialTransactionPosted automatically).
 *
 * GL treatment: value collected on the merchant's behalf is debited to
 * SUSPENSE_ASSET (received value pending settlement/reconciliation) and
 * credited to MERCHANT_LIABILITY (what the bank now owes the merchant,
 * pending payout) -- mirroring how WALLET_LIABILITY already models money
 * owed to wallet holders.
 */
class MerchantPaymentService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function collectQrPayment(
        Merchant $merchant,
        float $amount,
        int $branchId,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        return $this->collect(
            $merchant,
            $amount,
            'QR_COLLECTION',
            $branchId,
            $performedBy,
            $reference,
            $narration
        );
    }

    /**
     * @throws Exception
     */
    public function collectPosPayment(
        Merchant $merchant,
        float $amount,
        int $branchId,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        return $this->collect(
            $merchant,
            $amount,
            'POS_COLLECTION',
            $branchId,
            $performedBy,
            $reference,
            $narration
        );
    }

    /**
     * @throws Exception
     */
    protected function collect(
        Merchant $merchant,
        float $amount,
        string $transactionType,
        int $branchId,
        int $performedBy,
        ?string $reference,
        ?string $narration
    ): array {
        if ($amount <= 0) {
            throw new Exception('Collection amount must be greater than zero.');
        }

        if ($merchant->status !== 'ACTIVE') {
            throw new Exception("Merchant {$merchant->merchant_code} is not active.");
        }

        return DB::transaction(function () use (
            $merchant,
            $amount,
            $transactionType,
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

            $narration = $narration ?? 'Merchant '.strtolower(str_replace('_', ' ', $transactionType));

            $merchantTransaction = MerchantTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_no' => $this->transactionNumberService->generate('MCH'),
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ]);

            $merchantTransaction = $merchantTransaction->refresh();

            event(new FinancialTransactionCreated($merchantTransaction));

            $merchantBalance->update([
                'ledger_balance' => $merchantBalance->ledger_balance + $amount,
                'available_balance' => $merchantBalance->available_balance + $amount,
                'last_transaction_id' => $merchantTransaction->id,
            ]);

            $cashLedger = CashLedger::create([
                'reference_no' => $merchantTransaction->transaction_no,
                'branch_id' => $branchId,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => "MERCHANT_{$transactionType}",
                'source_type' => MerchantTransaction::class,
                'source_id' => $merchantTransaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'SUSPENSE_ASSET',
                'account_code' => 'SUSPENSE_ASSET',
                'debit_account_key' => 'SUSPENSE_ASSET',
                'credit_account_key' => 'MERCHANT_LIABILITY',
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
                'transaction' => $merchantTransaction->fresh(),
                'balance' => $merchantBalance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}
