<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionReversed;
use App\Models\AgentBalance;
use App\Models\AgentTransaction;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Customer\CustomerAccountService;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentTransactionReversalService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {}

    /**
     * Reverse a completed agency transaction.
     *
     * First production slice: CASH_IN only.
     *
     * @throws Exception
     */
    public function reverse(
        AgentTransaction $transaction,
        int $approvedBy,
        ?string $narration = null
    ): AgentTransaction {
        return DB::transaction(function () use (
            $transaction,
            $approvedBy,
            $narration
        ) {
            /*
             * Re-fetch and lock the transaction so two checkers cannot
             * approve the same reversal concurrently.
             */
            $lockedTransaction = AgentTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTransaction) {
                throw new Exception('Agent transaction not found.');
            }

            /*
             * Check the explicit reversal state before the general status
             * guard so a duplicate reversal produces the precise audit-safe
             * error rather than being reported merely as non-completed.
             */
            if (
                $lockedTransaction->reversed_at !== null
                || $lockedTransaction->status === 'REVERSED'
            ) {
                throw new Exception(
                    'Agent transaction has already been reversed.'
                );
            }

            if ($lockedTransaction->status !== 'COMPLETED') {
                throw new Exception(
                    'Only completed agent transactions can be reversed.'
                );
            }

            if ($lockedTransaction->transaction_type !== 'CASH_IN') {
                throw new Exception(
                    'Only CASH_IN agent transactions can be reversed in this implementation.'
                );
            }

            if (! $lockedTransaction->customer_account_id) {
                throw new Exception(
                    'Agent transaction has no customer account to reverse.'
                );
            }

            $customerAccount = CustomerAccount::find(
                $lockedTransaction->customer_account_id
            );

            if (! $customerAccount) {
                throw new Exception(
                    'Customer account for the agent transaction was not found.'
                );
            }

            if ($customerAccount->status !== 'ACTIVE') {
                throw new Exception(
                    'Customer account is not active.'
                );
            }

            $agentBalance = AgentBalance::where(
                'agent_id',
                $lockedTransaction->agent_id
            )
                ->lockForUpdate()
                ->first();

            if (! $agentBalance) {
                throw new Exception(
                    'Agent balance was not found for reversal.'
                );
            }

            $amount = (float) $lockedTransaction->amount;
            $commissionAmount = (float) $lockedTransaction->commission_amount;

            /*
             * CASH_IN originally increases physical cash. Reversal must not
             * push the agent's declared physical position below zero.
             */
            if ((float) $agentBalance->declared_physical_cash < $amount) {
                throw new Exception(
                    'Agent has insufficient declared physical cash for reversal.'
                );
            }

            /*
             * CASH_IN may have accrued pending commission. Reversal must
             * unwind that accrual without permitting a negative balance.
             */
            if (
                $commissionAmount > 0
                && (float) $agentBalance->pending_commission < $commissionAmount
            ) {
                throw new Exception(
                    'Agent has insufficient pending commission for reversal.'
                );
            }

            $reversalNarration = $narration
                ?? "Reversal of agent cash-in {$lockedTransaction->transaction_no}";

            /*
             * The reversal receives its own reference. The original
             * transaction number remains attached to the original business
             * transaction and must never be reused for the compensating entry.
             */
            $reversalReference = $this->transactionNumberService->generate(
                'AGR'
            );

            /*
             * Reverse the customer-side economic effect.
             *
             * CustomerAccountService performs its own lockForUpdate() and
             * verifies sufficient available balance before reducing it.
             *
             * The checker/approver is recorded as the actor performing the
             * compensating customer transaction.
             */
            $this->customerAccountService->withdraw(
                $customerAccount,
                $amount,
                $approvedBy,
                $reversalReference,
                $reversalNarration
            );

            /*
             * Reverse the agent-side economic effects of CASH_IN.
             *
             * Original CASH_IN:
             *   ledger_float           -= amount
             *   available_float        -= amount
             *   declared_physical_cash += amount
             *   pending_commission     += commission
             *
             * Reversal performs the exact inverse.
             */
            $agentBalance->ledger_float += $amount;
            $agentBalance->available_float += $amount;
            $agentBalance->declared_physical_cash -= $amount;

            if ($commissionAmount > 0) {
                $agentBalance->pending_commission -= $commissionAmount;
            }

            $agentBalance->save();

            /*
             * Original CASH_IN GL:
             *
             *   DR AGENCY_FLOAT
             *   CR CUSTOMER_DEPOSIT_CONTROL
             *
             * Reversal:
             *
             *   DR CUSTOMER_DEPOSIT_CONTROL
             *   CR AGENCY_FLOAT
             *
             * Never alter the original CashLedger or GlJournal records.
             * This is a new compensating financial entry.
             */
            $cashLedger = CashLedger::create([
                'reference_no' => $reversalReference,
                'branch_id' => $lockedTransaction->agent->branch_id,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $approvedBy,
                'transaction_type' => 'AGENT_CASH_IN_REVERSAL',
                'source_type' => AgentTransaction::class,
                'source_id' => $lockedTransaction->id,
                'entry_type' => 'CREDIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'credit_account_key' => 'AGENCY_FLOAT',
                'debit' => 0,
                'credit' => $amount,
                'running_balance' => $agentBalance->ledger_float,
                'currency' => $agentBalance->currency,
                'narration' => $reversalNarration,
                'status' => 'PENDING',
                'approved_by' => $approvedBy,
                'transaction_date' => now(),
            ]);

            /*
             * GlPostingService creates the compensating GL journals and
             * transitions the CashLedger to APPROVED. Do not duplicate that
             * status mutation here.
             */
            $this->glPostingService->postFromCashLedger($cashLedger);

            $agentBalance->last_transaction_id = $cashLedger->id;
            $agentBalance->save();

            /*
             * Preserve the original AgentTransaction and record its reversal
             * state. The checker is the user who legally authorizes and
             * executes the approved reversal.
             */
            $lockedTransaction->update([
                'status' => 'REVERSED',
                'reversed_at' => now(),
                'approved_by' => $approvedBy,
            ]);

            $reversedTransaction = $lockedTransaction->fresh();

            event(
                new FinancialTransactionReversed(
                    $reversedTransaction
                )
            );

            return $reversedTransaction;
        });
    }
}
