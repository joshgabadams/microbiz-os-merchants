<?php

namespace App\Services\Accounting;

use App\Models\CashLedger;
use App\Models\GlJournal;
use Exception;

class GlPostingService
{
    public function __construct(
        protected GlAccountResolver $resolver
    ) {
    }

    /**
     * Post a CashLedger transaction to the General Ledger.
     *
     * @throws Exception
     */
    public function postFromCashLedger(
        CashLedger $ledger
    ): bool {

        $this->validateLedger($ledger);

        /*
        |--------------------------------------------------------------------------
        | Resolve Business Account Keys
        |--------------------------------------------------------------------------
        */

        $debitAccount = $this->resolver->resolve(
            $ledger->debit_account_key
        );

        $creditAccount = $this->resolver->resolve(
            $ledger->credit_account_key
        );

        /*
        |--------------------------------------------------------------------------
        | Determine Posting Amount
        |--------------------------------------------------------------------------
        */

        $amount = max(
            $ledger->debit,
            $ledger->credit
        );

        /*
        |--------------------------------------------------------------------------
        | Debit Entry
        |--------------------------------------------------------------------------
        */

        $debitEntry = $this->createJournalEntry(

            account: $debitAccount,

            entryType: 'DEBIT',

            amount: $amount,

            ledger: $ledger

        );

        /*
        |--------------------------------------------------------------------------
        | Credit Entry
        |--------------------------------------------------------------------------
        */

        $creditEntry = $this->createJournalEntry(

            account: $creditAccount,

            entryType: 'CREDIT',

            amount: $amount,

            ledger: $ledger

        );

        /*
        |--------------------------------------------------------------------------
        | Validate Double Entry
        |--------------------------------------------------------------------------
        */

        $this->validateBalance(
            $debitEntry,
            $creditEntry
        );

        /*
        |--------------------------------------------------------------------------
        | Mark Ledger Posted
        |--------------------------------------------------------------------------
        */

        $ledger->update([
            'status' => 'APPROVED'
        ]);

        return true;
    }

    /**
     * Validate Ledger.
     */
    private function validateLedger(
        CashLedger $ledger
    ): void {

        if (!in_array(
            $ledger->status,
            ['PENDING', 'POSTED']
        )) {

            throw new Exception(
                "Ledger {$ledger->reference_no} is not postable."
            );
        }

        if (($ledger->debit + $ledger->credit) <= 0) {

            throw new Exception(
                "Ledger contains no monetary value."
            );
        }

        if (
            empty($ledger->debit_account_key) ||
            empty($ledger->credit_account_key)
        ) {

            throw new Exception(
                "Ledger account keys are missing."
            );
        }
    }

    /**
     * Create Journal Entry.
     */
    private function createJournalEntry(
        $account,
        string $entryType,
        float $amount,
        CashLedger $ledger
    ): GlJournal {

        return GlJournal::create([

            'gl_account_id' => $account->id,

            'entry_type' => $entryType,

            'amount' => $amount,

            'reference' => $ledger->reference_no,

            'source_type' => CashLedger::class,

            'source_id' => $ledger->id,

            'currency' => $ledger->currency,

            'narration' => $ledger->narration,

            'posted_at' => now(),

        ]);
    }

    /**
     * Ensure Double Entry.
     */
    private function validateBalance(
        GlJournal $debitEntry,
        GlJournal $creditEntry
    ): void {

        if ($debitEntry->amount != $creditEntry->amount) {

            throw new Exception(
                "Unbalanced GL Posting."
            );
        }
    }
}