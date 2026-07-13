<?php

namespace App\Services\CashManagement;

use App\Models\CashLedger;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionReversalService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService
    ) {
    }

    public function reverseCustomerDeposit(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($customerTransaction->transaction_type !== 'CASH_DEPOSIT') {
            throw new Exception('Only CASH_DEPOSIT transactions can be reversed by this method.');
        }

        if ($tellerTransaction->transaction_type !== 'CUSTOMER_DEPOSIT') {
            throw new Exception('Matching teller transaction must be CUSTOMER_DEPOSIT.');
        }

        return $this->reverseCustomerCashTransaction(
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration ?? 'Reverse customer cash deposit',
            customerBalanceDirection: 'DECREASE',
            tellerBalanceDirection: 'DECREASE',
            ledgerType: 'CUSTOMER_DEPOSIT_REVERSAL',
            ledgerEntryType: 'CREDIT',
            debitAccountKey: 'CUSTOMER_DEPOSIT_CONTROL',
            creditAccountKey: 'TELLER_CASH'
        );
    }

    public function reverseCustomerWithdrawal(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($customerTransaction->transaction_type !== 'CASH_WITHDRAWAL') {
            throw new Exception('Only CASH_WITHDRAWAL transactions can be reversed by this method.');
        }

        if ($tellerTransaction->transaction_type !== 'CUSTOMER_WITHDRAWAL') {
            throw new Exception('Matching teller transaction must be CUSTOMER_WITHDRAWAL.');
        }

        return $this->reverseCustomerCashTransaction(
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration ?? 'Reverse customer cash withdrawal',
            customerBalanceDirection: 'INCREASE',
            tellerBalanceDirection: 'INCREASE',
            ledgerType: 'CUSTOMER_WITHDRAWAL_REVERSAL',
            ledgerEntryType: 'DEBIT',
            debitAccountKey: 'TELLER_CASH',
            creditAccountKey: 'CUSTOMER_WITHDRAWAL_CONTROL'
        );
    }

    protected function reverseCustomerCashTransaction(
        CustomerAccountTransaction $customerTransaction,
        TellerTransaction $tellerTransaction,
        int $performedBy,
        ?string $reference,
        string $narration,
        string $customerBalanceDirection,
        string $tellerBalanceDirection,
        string $ledgerType,
        string $ledgerEntryType,
        string $debitAccountKey,
        string $creditAccountKey
    ): array {
        if ($customerTransaction->is_reversed || $tellerTransaction->is_reversed) {
            throw new Exception('Transaction has already been reversed.');
        }

        return DB::transaction(function () use (
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration,
            $customerBalanceDirection,
            $tellerBalanceDirection,
            $ledgerType,
            $ledgerEntryType,
            $debitAccountKey,
            $creditAccountKey
        ) {
            $amount = (float) $customerTransaction->amount;

            $customerBalance = CustomerAccountBalance::where(
                'customer_account_id',
                $customerTransaction->customer_account_id
            )->lockForUpdate()->first();

            if (!$customerBalance) {
                throw new Exception('Customer balance not found.');
            }

            $tellerBalance = TellerBalance::where(
                'teller_id',
                $tellerTransaction->teller_id
            )->lockForUpdate()->first();

            if (!$tellerBalance) {
                throw new Exception('Teller balance not found.');
            }

            if ($customerBalanceDirection === 'DECREASE' && $customerBalance->available_balance < $amount) {
                throw new Exception('Insufficient customer balance for reversal.');
            }

            if ($tellerBalanceDirection === 'DECREASE' && $tellerBalance->available_balance < $amount) {
                throw new Exception('Insufficient teller cash for reversal.');
            }

            $customerReversal = CustomerAccountTransaction::create([
                'customer_account_id' => $customerTransaction->customer_account_id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $customerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $customerTransaction->id,
            ]);

            $tellerReversal = TellerTransaction::create([
                'teller_id' => $tellerTransaction->teller_id,
                'transaction_no' => $this->transactionNumberService->generate('TLR'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $tellerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $tellerTransaction->id,
            ]);

            if ($customerBalanceDirection === 'INCREASE') {
                $customerBalance->ledger_balance += $amount;
                $customerBalance->available_balance += $amount;
            } else {
                $customerBalance->ledger_balance -= $amount;
                $customerBalance->available_balance -= $amount;
            }

            $customerBalance->last_transaction_id = $customerReversal->id;
            $customerBalance->save();

            if ($tellerBalanceDirection === 'INCREASE') {
                $tellerBalance->ledger_balance += $amount;
                $tellerBalance->available_balance += $amount;
            } else {
                $tellerBalance->ledger_balance -= $amount;
                $tellerBalance->available_balance -= $amount;
            }

            $tellerBalance->last_transaction_id = $tellerReversal->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $tellerReversal->transaction_no,
                'branch_id'          => 1,
                'vault_id'           => null,
                'teller_id'          => $tellerTransaction->teller_id,
                'user_id'            => $performedBy,
                'transaction_type'   => $ledgerType,
                'source_type'        => TellerTransaction::class,
                'source_id'          => $tellerReversal->id,
                'entry_type'         => $ledgerEntryType,
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => $debitAccountKey,
                'credit_account_key' => $creditAccountKey,
                'debit'              => $ledgerEntryType === 'DEBIT' ? $amount : 0,
                'credit'             => $ledgerEntryType === 'CREDIT' ? $amount : 0,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration,
                'status'             => 'APPROVED',
                'approved_by'        => $performedBy,
                'transaction_date'   => now(),
            ]);

            $customerTransaction->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $performedBy,
            ]);

            $tellerTransaction->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $performedBy,
            ]);

            return [
                'customer_reversal_transaction' => $customerReversal->fresh(),
                'teller_reversal_transaction' => $tellerReversal->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}