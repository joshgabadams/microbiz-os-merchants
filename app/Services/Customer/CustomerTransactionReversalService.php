<?php

namespace App\Services\Customer;

use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Models\CashLedger;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerTransactionReversalService
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
            throw new Exception('Only CASH_DEPOSIT transactions can use this reversal method.');
        }

        if ($tellerTransaction->transaction_type !== 'CUSTOMER_DEPOSIT') {
            throw new Exception('Matching teller transaction must be CUSTOMER_DEPOSIT.');
        }

        if ($customerTransaction->is_reversed || $tellerTransaction->is_reversed) {
            throw new Exception('Transaction has already been reversed.');
        }

        return DB::transaction(function () use (
            $customerTransaction,
            $tellerTransaction,
            $performedBy,
            $reference,
            $narration
        ) {
            $amount = (float) $customerTransaction->amount;

            $customerBalance = CustomerAccountBalance::where(
                'customer_account_id',
                $customerTransaction->customer_account_id
            )->lockForUpdate()->first();

            if (!$customerBalance || $customerBalance->available_balance < $amount) {
                throw new Exception('Insufficient customer balance to reverse deposit.');
            }

            $tellerBalance = TellerBalance::where(
                'teller_id',
                $tellerTransaction->teller_id
            )->lockForUpdate()->first();

            if (!$tellerBalance || $tellerBalance->available_balance < $amount) {
                throw new Exception('Insufficient teller cash to reverse deposit.');
            }

            $reversalCustomerTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $customerTransaction->customer_account_id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $customerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration ?? 'Reversal of customer cash deposit',
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $customerTransaction->id,
            ]);

            $customerBalance->ledger_balance -= $amount;
            $customerBalance->available_balance -= $amount;
            $customerBalance->last_transaction_id = $reversalCustomerTransaction->id;
            $customerBalance->save();

            $reversalTellerTransaction = TellerTransaction::create([
                'teller_id' => $tellerTransaction->teller_id,
                'transaction_no' => $this->transactionNumberService->generate('TLR'),
                'transaction_type' => 'REVERSAL',
                'amount' => $amount,
                'currency' => $tellerTransaction->currency,
                'reference' => $reference,
                'narration' => $narration ?? 'Reversal of customer cash deposit',
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
                'reversal_of_transaction_id' => $tellerTransaction->id,
            ]);

            $tellerBalance->ledger_balance -= $amount;
            $tellerBalance->available_balance -= $amount;
            $tellerBalance->last_transaction_id = $reversalTellerTransaction->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $reversalTellerTransaction->transaction_no,
                'branch_id'          => 1,
                'vault_id'           => null,
                'teller_id'          => $tellerTransaction->teller_id,
                'user_id'            => $performedBy,
                'transaction_type'   => 'CUSTOMER_DEPOSIT_REVERSAL',
                'source_type'        => TellerTransaction::class,
                'source_id'          => $reversalTellerTransaction->id,
                'entry_type'         => 'CREDIT',
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => 'CUSTOMER_DEPOSIT_CONTROL',
                'credit_account_key' => 'TELLER_CASH',
                'debit'              => 0,
                'credit'             => $amount,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration ?? 'Reversal of customer cash deposit',
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
                'customer_reversal_transaction' => $reversalCustomerTransaction->fresh(),
                'teller_reversal_transaction' => $reversalTellerTransaction->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}