<?php

namespace App\Services\Customer;

use App\Events\FinancialTransactionCreated;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerAccountService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService
    ) {
    }

    public function deposit(
        CustomerAccount $account,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): CustomerAccountTransaction {
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $account,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            if ($account->status !== 'ACTIVE') {
                throw new Exception('Customer account is not active.');
            }

            $balance = $this->getOrCreateBalance($account);

            $transaction = $this->createTransaction(
                account: $account,
                transactionType: 'CASH_DEPOSIT',
                amount: $amount,
                performedBy: $performedBy,
                reference: $reference,
                narration: $narration ?? 'Customer cash deposit'
            );

            $balance->ledger_balance += $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_id = $transaction->id;
            $balance->save();

            $transaction->update([
                'posted' => true,
            ]);

            return $transaction->fresh();
        });
    }

    public function withdraw(
    CustomerAccount $account,
    float $amount,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): CustomerAccountTransaction {
    if ($amount <= 0) {
        throw new Exception('Withdrawal amount must be greater than zero.');
    }

    return DB::transaction(function () use (
        $account,
        $amount,
        $performedBy,
        $reference,
        $narration
    ) {
        if ($account->status !== 'ACTIVE') {
            throw new Exception('Customer account is not active.');
        }

        $balance = $this->getOrCreateBalance($account);

        if ($balance->available_balance < $amount) {
            throw new Exception('Insufficient customer account balance.');
        }

        $transaction = $this->createTransaction(
            account: $account,
            transactionType: 'CASH_WITHDRAWAL',
            amount: $amount,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration ?? 'Customer cash withdrawal'
        );

        $balance->ledger_balance -= $amount;
        $balance->available_balance -= $amount;
        $balance->last_transaction_id = $transaction->id;
        $balance->save();

        $transaction->update([
            'posted' => true,
        ]);

        return $transaction->fresh();
    });
}

    protected function getOrCreateBalance(
        CustomerAccount $account
    ): CustomerAccountBalance {
        $balance = CustomerAccountBalance::where(
            'customer_account_id',
            $account->id
        )
        ->lockForUpdate()
        ->first();

        if (!$balance) {
            $balance = CustomerAccountBalance::create([
                'customer_account_id' => $account->id,
                'currency' => $account->currency ?? 'NGN',
                'ledger_balance' => 0,
                'available_balance' => 0,
                'locked_balance' => 0,
                'last_transaction_id' => null,
            ]);

            $balance->refresh();
        }

        return $balance;
    }

    protected function createTransaction(
        CustomerAccount $account,
        string $transactionType,
        float $amount,
        int $performedBy,
        ?string $reference,
        ?string $narration
    ): CustomerAccountTransaction {
         $transaction = CustomerAccountTransaction::create([
            'customer_account_id' => $account->id,
            'transaction_no' => $this->transactionNumberService->generate('CUS'),
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'currency' => $account->currency ?? 'NGN',
            'reference' => $reference,
            'narration' => $narration,
            'performed_by' => $performedBy,
            'approved_by' => null,
            'transaction_date' => now(),
            'posted' => false,
       ])->refresh();

        event(new FinancialTransactionCreated($transaction));

        return $transaction;
    }
}