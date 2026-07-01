<?php

namespace App\Services\Accounting;

use App\Models\CashLedger;
use App\Models\GlJournal;
use App\Models\GlAccount;
use Exception;

class GlPostingService
{
    /**
     * Post a CashLedger entry into the General Ledger
     */
    public function postFromCashLedger(CashLedger $ledger): bool
    {
        // Ensure ledger is valid
        $this->validateLedger($ledger);

        // 1. Resolve GL accounts
        $debitAccount = $this->resolveDebitAccount($ledger);
        $creditAccount = $this->resolveCreditAccount($ledger);

        if (!$debitAccount || !$creditAccount) {
            throw new Exception("GL Account mapping failed for ledger reference: {$ledger->reference_no}");
        }

        // 2. Create DEBIT entry
        $debitEntry = GlJournal::create([
            'gl_account_id' => $debitAccount->id,
            'entry_type'     => 'DEBIT',
            'amount'         => $ledger->debit,
            'reference'      => $ledger->reference_no,
            'source_type'    => CashLedger::class,
            'source_id'      => $ledger->id,
            'posted_at'      => now(),
        ]);

        // 3. Create CREDIT entry
        $creditEntry = GlJournal::create([
            'gl_account_id' => $creditAccount->id,
            'entry_type'     => 'CREDIT',
            'amount'         => $ledger->credit,
            'reference'      => $ledger->reference_no,
            'source_type'    => CashLedger::class,
            'source_id'      => $ledger->id,
            'posted_at'      => now(),
        ]);

        // 4. Validate double-entry integrity
        $this->validateBalance($debitEntry, $creditEntry);

        return true;
    }

    /**
     * Ensure ledger is valid before posting
     */
    private function validateLedger(CashLedger $ledger): void
    {
        if ($ledger->status !== 'POSTED' && $ledger->status !== 'PENDING') {
            throw new Exception("Ledger is not in a postable state.");
        }

        if (!$ledger->debit && !$ledger->credit) {
            throw new Exception("Ledger must have either debit or credit amount.");
        }
    }

    /**
     * Resolve DEBIT GL account
     */
    private function resolveDebitAccount(CashLedger $ledger): ?GlAccount
    {
        return match ($ledger->transaction_type) {
            'CASH_IN',
            'CUSTOMER_DEPOSIT',
            'VAULT_TO_TELLER' => GlAccount::where('account_code', 'TELLER_CASH')->first(),

            'CASH_OUT',
            'CUSTOMER_WITHDRAWAL' => GlAccount::where('account_code', 'CUSTOMER_LIABILITY')->first(),

            default => null,
        };
    }

    /**
     * Resolve CREDIT GL account
     */
    private function resolveCreditAccount(CashLedger $ledger): ?GlAccount
    {
        return match ($ledger->transaction_type) {
            'CUSTOMER_DEPOSIT',
            'VAULT_TO_TELLER' => GlAccount::where('account_code', 'CUSTOMER_LIABILITY')->first(),

            'CASH_OUT',
            'CUSTOMER_WITHDRAWAL' => GlAccount::where('account_code', 'TELLER_CASH')->first(),

            default => null,
        };
    }

    /**
     * Ensure debit and credit are balanced
     */
    private function validateBalance(GlJournal $debitEntry, GlJournal $creditEntry): void
    {
        if ($debitEntry->amount !== $creditEntry->amount) {
            throw new Exception("Unbalanced GL entries detected for reference: {$debitEntry->reference}");
        }
    }
}