<?php

namespace App\Services\Vault;

use App\Models\CashLedger;
use App\Models\Vault;
use App\Models\VaultBalance;
use App\Models\VaultTransaction;
use App\Services\Common\TransactionNumberService;
use App\Services\Accounting\GlPostingService;
use Illuminate\Support\Facades\DB;
use Exception;

class VaultTransactionService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * Deposit cash into a vault.
     *
     * @throws Exception
     */
    public function deposit(
        Vault $vault,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): VaultTransaction {

        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $vault,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {

            /*
            |--------------------------------------------------------------------------
            | Use a single transaction timestamp
            |--------------------------------------------------------------------------
            */
            $transactionDate = now();

            /*
            |--------------------------------------------------------------------------
            | Ensure the vault is active
            |--------------------------------------------------------------------------
            */
            if (!$vault->active) {
                throw new Exception('Vault is inactive.');
            }

            /*
            |--------------------------------------------------------------------------
            | Lock Vault Balance
            |--------------------------------------------------------------------------
            */
            $balance = VaultBalance::where('vault_id', $vault->id)
                ->lockForUpdate()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Create Balance Record If Missing
            |--------------------------------------------------------------------------
            */
            if (!$balance) {

                $balance = VaultBalance::create([
                    'vault_id'            => $vault->id,
                    'currency'            => $vault->currency ?? 'NGN',
                    'ledger_balance'      => 0,
                    'available_balance'   => 0,
                    'locked_balance'      => 0,
                    'last_transaction_id' => null,
                ]);

                $balance->refresh();
            }

            /*
            |--------------------------------------------------------------------------
            | Generate Transaction Number
            |--------------------------------------------------------------------------
            */
            $transactionNumber = $this->transactionNumberService
                ->generate('VLT');

            /*
            |--------------------------------------------------------------------------
            | Create Vault Transaction
            |--------------------------------------------------------------------------
            */
            $transaction = VaultTransaction::create([

                'vault_id'          => $vault->id,

                'transaction_no'    => $transactionNumber,

                'transaction_type'  => 'DEPOSIT',

                'amount'            => $amount,

                'currency'          => $balance->currency,

                'reference'         => $reference,

                'narration'         => $narration,

                'performed_by'      => $performedBy,

                'approved_by'       => null,

                'transaction_date'  => $transactionDate,

                'posted'            => false,

            ]);

            $transaction->refresh();

            /*
            |--------------------------------------------------------------------------
            | Update Vault Balance
            |--------------------------------------------------------------------------
            */
            $balance->ledger_balance =
                $balance->ledger_balance + $amount;

            $balance->available_balance =
                $balance->available_balance + $amount;

            $balance->last_transaction_id =
                $transaction->id;

            $balance->save();

            /*
            |--------------------------------------------------------------------------
            | Create Cash Ledger Entry
            |--------------------------------------------------------------------------
            */
            $cashLedger = CashLedger::create([

                'reference_no'      => $transaction->transaction_no,

                'branch_id'         => $vault->branch_id,

                'vault_id'          => $vault->id,

                'teller_id'         => null,

                'user_id'           => $performedBy,

                'transaction_type'  => 'VAULT_DEPOSIT',

                'source_type'       => VaultTransaction::class,

                'source_id'         => $transaction->id,

                'entry_type'        => 'DEBIT',

                'account_type'      => 'VAULT_CASH',

                'account_code'      => 'VAULT_CASH',

                'debit_account_key'      => 'VAULT_CASH',

                'credit_account_key'      => 'CASH_CONTROL',

                'debit'             => $amount,

                'credit'            => 0,

                'running_balance'   => $balance->ledger_balance,

                'currency'          => $balance->currency,

                'narration'         => $narration,

                'status'            => 'PENDING',

                'approved_by'       => null,

                'transaction_date'  => $transactionDate,

            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $transaction->posted = true;
            $transaction->save();

            return $transaction;
        });
    }

   /**
 * Withdraw cash from a vault.
 *
 * @throws Exception
 */
public function withdraw(
    Vault $vault,
    float $amount,
    int $performedBy,
    ?string $reference = null,
    ?string $narration = null
): VaultTransaction {

    // Validate amount
    if ($amount <= 0) {
        throw new Exception(
            'Withdrawal amount must be greater than zero.'
        );
    }

    return DB::transaction(function () use (
        $vault,
        $amount,
        $performedBy,
        $reference,
        $narration
    ) {

        /*
        |--------------------------------------------------------------------------
        | Transaction Timestamp
        |--------------------------------------------------------------------------
        */
        $transactionDate = now();

        /*
        |--------------------------------------------------------------------------
        | Ensure Vault is Active
        |--------------------------------------------------------------------------
        */
        if (!$vault->active) {
            throw new Exception(
                'Vault is inactive.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Lock Vault Balance
        |--------------------------------------------------------------------------
        */
        $balance = VaultBalance::where(
            'vault_id',
            $vault->id
        )
        ->lockForUpdate()
        ->first();

        /*
        |--------------------------------------------------------------------------
        | Ensure Vault Has Sufficient Funds
        |--------------------------------------------------------------------------
        */
        if (!$balance) {
            throw new Exception(
                'Vault balance record not found.'
            );
        }

        if ($balance->available_balance < $amount) {
            throw new Exception(
                'Insufficient vault balance.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Transaction Number
        |--------------------------------------------------------------------------
        */
        $transactionNumber = $this
            ->transactionNumberService
            ->generate('VLT');

        /*
        |--------------------------------------------------------------------------
        | Create Vault Transaction
        |--------------------------------------------------------------------------
        */
        $transaction = VaultTransaction::create([

            'vault_id'         => $vault->id,

            'transaction_no'   => $transactionNumber,

            'transaction_type' => 'WITHDRAWAL',

            'amount'           => $amount,

            'currency'         => $balance->currency,

            'reference'        => $reference,

            'narration'        => $narration,

            'performed_by'     => $performedBy,

            'approved_by'      => null,

            'transaction_date' => $transactionDate,

            'posted'           => false,

        ]);

        $transaction->refresh();

        /*
        |--------------------------------------------------------------------------
        | Update Vault Balance
        |--------------------------------------------------------------------------
        */
        $balance->ledger_balance -= $amount;

        $balance->available_balance -= $amount;

        $balance->last_transaction_id = $transaction->id;

        $balance->save();

        /*
        |--------------------------------------------------------------------------
        | Create Cash Ledger Entry
        |--------------------------------------------------------------------------
        */
        $cashLedger = CashLedger::create([

            'reference_no'       => $transaction->transaction_no,

            'branch_id'          => $vault->branch_id,

            'vault_id'           => $vault->id,

            'teller_id'          => null,

            'user_id'            => $performedBy,

            'transaction_type'   => 'VAULT_WITHDRAWAL',

            'source_type'        => VaultTransaction::class,

            'source_id'          => $transaction->id,

            'entry_type'         => 'CREDIT',

            'account_type'       => 'VAULT_CASH',

            'account_code'       => 'VAULT_CASH',

            'debit_account_key'  => 'CASH_CONTROL',

            'credit_account_key' => 'VAULT_CASH',

            'debit'              => 0,

            'credit'             => $amount,

            'running_balance'    => $balance->ledger_balance,

            'currency'           => $balance->currency,

            'narration'          => $narration,

            'status'             => 'PENDING',

            'approved_by'        => null,

            'transaction_date'   => $transactionDate,

        ]);

        /*
        |--------------------------------------------------------------------------
        | Post to General Ledger
        |--------------------------------------------------------------------------
        */
        $this->glPostingService
            ->postFromCashLedger($cashLedger);

        /*
        |--------------------------------------------------------------------------
        | Mark Cash Ledger Approved
        |--------------------------------------------------------------------------
        */
        $cashLedger->status = 'APPROVED';

        $cashLedger->save();

        /*
        |--------------------------------------------------------------------------
        | Mark Transaction Posted
        |--------------------------------------------------------------------------
        */
        $transaction->posted = true;

        $transaction->save();

        return $transaction->refresh();

    });
}
}