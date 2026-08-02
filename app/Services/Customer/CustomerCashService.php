<?php

namespace App\Services\Customer;

use App\Events\FinancialTransactionCreated;
use App\Events\FinancialTransactionPosted;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Accounting\GlPostingService;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerCashService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService,
        protected GlPostingService $glPostingService
    ) {
    }

    public function deposit(
        Teller $teller,
        CustomerAccount $account,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $teller,
            $account,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            if (!$teller->active) {
                throw new Exception('Teller is inactive.');
            }

            if ($teller->status !== 'OPEN') {
                throw new Exception('Teller must be open to process customer deposits.');
            }

            if ($account->status !== 'ACTIVE') {
                throw new Exception('Customer account is not active.');
            }

            $tellerBalance = TellerBalance::where('teller_id', $teller->id)
                ->lockForUpdate()
                ->first();

            if (!$tellerBalance) {
                throw new Exception('Teller balance not found.');
            }

            $customerTransaction = $this->customerAccountService->deposit(
                $account,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Customer cash deposit'
            );

            $tellerTransaction = TellerTransaction::create([
                'teller_id' => $teller->id,
                'transaction_no' => app(\App\Services\Common\TransactionNumberService::class)->generate('TLR'),
                'transaction_type' => 'CUSTOMER_DEPOSIT',
                'amount' => $amount,
                'currency' => $tellerBalance->currency,
                'reference' => $reference,
                'narration' => $narration ?? 'Customer cash deposit',
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ]);

            event(new FinancialTransactionCreated($tellerTransaction));

            $tellerBalance->ledger_balance += $amount;
            $tellerBalance->available_balance += $amount;
            $tellerBalance->last_transaction_id = $tellerTransaction->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $tellerTransaction->transaction_no,
                'branch_id'          => $teller->branch_id,
                'vault_id'           => $teller->vault_id,
                'teller_id'          => $teller->id,
                'user_id'            => $performedBy,
                'transaction_type'   => 'CUSTOMER_CASH_DEPOSIT',
                'source_type'        => TellerTransaction::class,
                'source_id'          => $tellerTransaction->id,
                'entry_type'         => 'DEBIT',
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => 'TELLER_CASH',
                'credit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'debit'              => $amount,
                'credit'             => 0,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration ?? 'Customer cash deposit',
                'status'             => 'PENDING',
                'approved_by'        => null,
                'transaction_date'   => now(),
            ]);

            $tellerTransaction->update([
                'posted' => true,
            ]);

            $cashLedger->update([
                'status' => 'APPROVED',
                'approved_by' => $performedBy,
            ]);

            event(new FinancialTransactionPosted($cashLedger));

            return [
                'customer_transaction' => $customerTransaction,
                'teller_transaction' => $tellerTransaction->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }

    public function withdraw(
        Teller $teller,
        CustomerAccount $account,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Withdrawal amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $teller,
            $account,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            if (!$teller->active) {
                throw new Exception('Teller is inactive.');
            }

            if ($teller->status !== 'OPEN') {
                throw new Exception('Teller must be open to process customer withdrawals.');
            }

            if ($account->status !== 'ACTIVE') {
                throw new Exception('Customer account is not active.');
            }

            $tellerBalance = TellerBalance::where('teller_id', $teller->id)
                ->lockForUpdate()
                ->first();

            if (!$tellerBalance) {
                throw new Exception('Teller balance not found.');
            }

            if ($tellerBalance->available_balance < $amount) {
                throw new Exception('Insufficient teller cash balance.');
            }

            $customerTransaction = $this->customerAccountService->withdraw(
                $account,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Customer cash withdrawal'
            );

            $tellerTransaction = TellerTransaction::create([
                'teller_id' => $teller->id,
                'transaction_no' => app(\App\Services\Common\TransactionNumberService::class)->generate('TLR'),
                'transaction_type' => 'CUSTOMER_WITHDRAWAL',
                'amount' => $amount,
                'currency' => $tellerBalance->currency,
                'reference' => $reference,
                'narration' => $narration ?? 'Customer cash withdrawal',
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ]);

            event(new FinancialTransactionCreated($tellerTransaction));

            $tellerBalance->ledger_balance -= $amount;
            $tellerBalance->available_balance -= $amount;
            $tellerBalance->last_transaction_id = $tellerTransaction->id;
            $tellerBalance->save();

            $cashLedger = CashLedger::create([
                'reference_no'       => $tellerTransaction->transaction_no,
                'branch_id'          => $teller->branch_id,
                'vault_id'           => $teller->vault_id,
                'teller_id'          => $teller->id,
                'user_id'            => $performedBy,
                'transaction_type'   => 'CUSTOMER_CASH_WITHDRAWAL',
                'source_type'        => TellerTransaction::class,
                'source_id'          => $tellerTransaction->id,
                'entry_type'         => 'CREDIT',
                'account_type'       => 'TELLER_CASH',
                'account_code'       => 'TELLER_CASH',
                'debit_account_key'  => 'CUSTOMER_WITHDRAWAL_CONTROL',
                'credit_account_key' => 'TELLER_CASH',
                'debit'              => 0,
                'credit'             => $amount,
                'running_balance'    => $tellerBalance->ledger_balance,
                'currency'           => $tellerBalance->currency,
                'narration'          => $narration ?? 'Customer cash withdrawal',
                'status'             => 'PENDING',
                'approved_by'        => null,
                'transaction_date'   => now(),
            ]);

            $tellerTransaction->update([
                'posted' => true,
            ]);

            $cashLedger->update([
                'status' => 'APPROVED',
                'approved_by' => $performedBy,
            ]);

            event(new FinancialTransactionPosted($cashLedger));

            return [
                'customer_transaction' => $customerTransaction,
                'teller_transaction' => $tellerTransaction->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}