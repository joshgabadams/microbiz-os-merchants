<?php

namespace App\Services\Teller;

use App\Models\CashLedger;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class TellerTransactionService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * Receive float from a vault.
     *
     * @throws Exception
     */
    public function receiveFloat(
        Teller $teller,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): TellerTransaction {

        if ($amount <= 0) {
            throw new Exception(
                'Float amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $teller,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {

            $transactionDate = now();

            /*
            |--------------------------------------------------------------------------
            | Ensure Teller is Active
            |--------------------------------------------------------------------------
            */
            if (!$teller->active) {
                throw new Exception('Teller is inactive.');
            }

            /*
            |--------------------------------------------------------------------------
            | Get or Create Teller Balance
            |--------------------------------------------------------------------------
            */
            $balance = $this->getOrCreateBalance($teller);

            /*
            |--------------------------------------------------------------------------
            | Create Teller Transaction
            |--------------------------------------------------------------------------
            */
            $transaction = $this->createTransaction(
                teller: $teller,
                transactionType: 'RECEIVE_FLOAT',
                amount: $amount,
                performedBy: $performedBy,
                reference: $reference,
                narration: $narration,
                transactionDate: $transactionDate,
            );

            /*
            |--------------------------------------------------------------------------
            | Update Teller Balance
            |--------------------------------------------------------------------------
            */
            $balance->ledger_balance += $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_id = $transaction->id;
            $balance->save();

            /*
            |--------------------------------------------------------------------------
            | Create Cash Ledger Entry
            |--------------------------------------------------------------------------
            */
            $cashLedger = CashLedger::create([

                'reference_no'       => $transaction->transaction_no,

                'branch_id'          => $teller->branch_id,

                'vault_id'           => $teller->vault_id,

                'teller_id'          => $teller->id,

                'user_id'            => $performedBy,

                'transaction_type'   => 'FLOAT_RECEIPT',

                'source_type'        => TellerTransaction::class,

                'source_id'          => $transaction->id,

                'entry_type'         => 'DEBIT',

                'account_type'       => 'TELLER_CASH',

                'account_code'       => 'TELLER_CASH',

                'debit_account_key'  => 'TELLER_CASH',

                'credit_account_key' => 'VAULT_CASH',

                'debit'              => $amount,

                'credit'             => 0,

                'running_balance'    => $balance->ledger_balance,

                'currency'           => $balance->currency,

                'narration'          => $narration,

                'status'             => 'PENDING',

                'approved_by'        => null,

                'transaction_date'   => $transactionDate,

            ]);

            /*
            |--------------------------------------------------------------------------
            | Post GL Transaction
            |--------------------------------------------------------------------------
            */
            $this->postTransaction(
                $transaction,
                $cashLedger
            );

            return $transaction->fresh();

        });
    }
/**
 * Return float to vault.
 *
 * @throws Exception
 */
public function returnFloat(
    Teller $teller,
    float $amount,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    if ($amount <= 0) {
        throw new Exception('Float amount must be greater than zero.');
    }

    return DB::transaction(function () use (
        $teller,
        $amount,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        $balance = $this->getOrCreateBalance($teller);

        if ($balance->available_balance < $amount) {
            throw new Exception('Insufficient teller balance.');
        }

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'RETURN_FLOAT',
            amount: $amount,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration,
            transactionDate: $transactionDate,
        );

        $balance->ledger_balance -= $amount;
        $balance->available_balance -= $amount;
        $balance->last_transaction_id = $transaction->id;
        $balance->save();

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'FLOAT_RETURN',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'CREDIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'VAULT_CASH',
            'credit_account_key' => 'TELLER_CASH',
            'debit'              => 0,
            'credit'             => $amount,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration,
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $this->postTransaction(
            $transaction,
            $cashLedger
        );

        return $transaction->fresh();
    });
}
    /**
 * Open teller for the day/session.
 *
 * @throws Exception
 */
public function openTeller(
    Teller $teller,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    return DB::transaction(function () use (
        $teller,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        if ($teller->status === 'OPEN') {
            throw new Exception('Teller is already open.');
        }

        $balance = $this->getOrCreateBalance($teller);

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'OPENING_CASH',
            amount: $balance->available_balance,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration ?? 'Teller opening cash',
            transactionDate: $transactionDate,
        );

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'TELLER_OPENING',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'DEBIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'TELLER_CASH',
            'credit_account_key' => 'OPENING_CASH_CONTROL',
            'debit'              => $balance->available_balance,
            'credit'             => 0,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration ?? 'Teller opening cash',
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $transaction->update([
    'posted' => true,
]);

$cashLedger->update([
    'status' => 'APPROVED',
    'approved_by' => $performedBy,
]);

        $teller->update([
            'status' => 'OPEN',
        ]);

        return $transaction->fresh();
    });
}

    /**
 * Close teller for the day/session.
 *
 * @throws Exception
 */
public function closeTeller(
    Teller $teller,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): TellerTransaction {

    return DB::transaction(function () use (
        $teller,
        $performedBy,
        $reference,
        $narration
    ) {
        $transactionDate = now();

        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        if ($teller->status === 'CLOSED') {
            throw new Exception('Teller is already closed.');
        }

        $balance = $this->getOrCreateBalance($teller);

        $transaction = $this->createTransaction(
            teller: $teller,
            transactionType: 'CLOSING_CASH',
            amount: $balance->available_balance,
            performedBy: $performedBy,
            reference: $reference,
            narration: $narration ?? 'Teller closing cash',
            transactionDate: $transactionDate,
        );

        $cashLedger = CashLedger::create([
            'reference_no'       => $transaction->transaction_no,
            'branch_id'          => $teller->branch_id,
            'vault_id'           => $teller->vault_id,
            'teller_id'          => $teller->id,
            'user_id'            => $performedBy,
            'transaction_type'   => 'TELLER_CLOSING',
            'source_type'        => TellerTransaction::class,
            'source_id'          => $transaction->id,
            'entry_type'         => 'CREDIT',
            'account_type'       => 'TELLER_CASH',
            'account_code'       => 'TELLER_CASH',
            'debit_account_key'  => 'CLOSING_CASH_CONTROL',
            'credit_account_key' => 'TELLER_CASH',
            'debit'              => 0,
            'credit'             => $balance->available_balance,
            'running_balance'    => $balance->ledger_balance,
            'currency'           => $balance->currency,
            'narration'          => $narration ?? 'Teller closing cash',
            'status'             => 'PENDING',
            'approved_by'        => null,
            'transaction_date'   => $transactionDate,
        ]);

        $transaction->update([
            'posted' => true,
        ]);

        $cashLedger->update([
            'status' => 'APPROVED',
            'approved_by' => $performedBy,
        ]);

        $teller->update([
            'status' => 'CLOSED',
        ]);

        return $transaction->fresh();
    });
}


    /**
     * Get or create teller balance.
     */
    protected function getOrCreateBalance(
        Teller $teller
    ): TellerBalance {

        $balance = TellerBalance::where(
            'teller_id',
            $teller->id
        )
        ->lockForUpdate()
        ->first();

        if (!$balance) {

            $balance = TellerBalance::create([

                'teller_id'           => $teller->id,

                'currency'            => 'NGN',

                'ledger_balance'      => 0,

                'available_balance'   => 0,

                'locked_balance'      => 0,

                'last_transaction_id' => null,

            ]);

            $balance->refresh();
        }

        return $balance;
    }

    /**
     * Create teller transaction.
     */
    protected function createTransaction(
        Teller $teller,
        string $transactionType,
        float $amount,
        int $performedBy,
        ?string $reference,
        ?string $narration,
        $transactionDate
    ): TellerTransaction {

        $transaction = TellerTransaction::create([

            'teller_id'         => $teller->id,

            'transaction_no'    => $this->transactionNumberService
                                        ->generate('TLR'),

            'transaction_type'  => $transactionType,

            'amount'            => $amount,

            'currency'          => 'NGN',

            'reference'         => $reference,

            'narration'         => $narration,

            'performed_by'      => $performedBy,

            'approved_by'       => null,

            'transaction_date'  => $transactionDate,

            'posted'            => false,

        ]);

        return $transaction->refresh();
    }

    /**
     * Post transaction to the General Ledger.
     */
    protected function postTransaction(
        TellerTransaction $transaction,
        CashLedger $cashLedger
    ): void {

        $this->glPostingService
            ->postFromCashLedger($cashLedger);

        $transaction->update([
            'posted' => true,
        ]);
    }
}