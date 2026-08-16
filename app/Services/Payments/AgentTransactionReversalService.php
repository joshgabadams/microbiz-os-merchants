<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionReversed;
use App\Models\AgentBalance;
use App\Models\AgentTransaction;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Customer\CustomerAccountService;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentTransactionReversalService
{
    protected const REVERSIBLE_TYPES = ['CASH_IN', 'CASH_OUT', 'TRANSFER'];

    public function __construct(
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {}

    /**
     * Reverse a completed agency transaction.
     *
     * Covers CASH_IN, CASH_OUT and TRANSFER. INTERBANK_TRANSFER is
     * deliberately excluded -- it never actually reaches COMPLETED
     * today (AgentInterbankTransferService's dispatch always fails),
     * so there is nothing to reverse.
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
            $lockedTransaction = AgentTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTransaction) {
                throw new Exception('Agent transaction not found.');
            }

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

            if (! in_array($lockedTransaction->transaction_type, self::REVERSIBLE_TYPES, true)) {
                throw new Exception(
                    'Only CASH_IN, CASH_OUT and TRANSFER agent transactions can be reversed.'
                );
            }

            return match ($lockedTransaction->transaction_type) {
                'CASH_IN' => $this->reverseCashIn($lockedTransaction, $approvedBy, $narration),
                'CASH_OUT' => $this->reverseCashOut($lockedTransaction, $approvedBy, $narration),
                'TRANSFER' => $this->reverseTransfer($lockedTransaction, $approvedBy, $narration),
            };
        });
    }

    /**
     * @throws Exception
     */
    protected function reverseCashIn(
        AgentTransaction $lockedTransaction,
        int $approvedBy,
        ?string $narration
    ): AgentTransaction {
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

        if ((float) $agentBalance->declared_physical_cash < $amount) {
            throw new Exception(
                'Agent has insufficient declared physical cash for reversal.'
            );
        }

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

        $reversalReference = $this->transactionNumberService->generate(
            'AGR'
        );

        $this->customerAccountService->withdraw(
            $customerAccount,
            $amount,
            $approvedBy,
            $reversalReference,
            $reversalNarration
        );

        $agentBalance->ledger_float += $amount;
        $agentBalance->available_float += $amount;
        $agentBalance->declared_physical_cash -= $amount;

        if ($commissionAmount > 0) {
            $agentBalance->pending_commission -= $commissionAmount;
        }

        $agentBalance->save();

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

        $this->glPostingService->postFromCashLedger($cashLedger);

        $agentBalance->last_transaction_id = $cashLedger->id;
        $agentBalance->save();

        return $this->finalizeReversal($lockedTransaction, $approvedBy);
    }

    /**
     * Reverses a CASH_OUT: the exact inverse of Blueprint §13.3.
     *
     * Original CASH_OUT:
     *   ledger_float           += amount
     *   available_float        += amount
     *   declared_physical_cash -= amount
     *   pending_commission     += commission
     *
     * Reversal performs the exact inverse, with the same
     * insufficient-balance protection CASH_IN reversal already
     * established.
     *
     * @throws Exception
     */
    protected function reverseCashOut(
        AgentTransaction $lockedTransaction,
        int $approvedBy,
        ?string $narration
    ): AgentTransaction {
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

        if ((float) $agentBalance->available_float < $amount) {
            throw new Exception(
                'Agent has insufficient available float for reversal.'
            );
        }

        if (
            $commissionAmount > 0
            && (float) $agentBalance->pending_commission < $commissionAmount
        ) {
            throw new Exception(
                'Agent has insufficient pending commission for reversal.'
            );
        }

        $reversalNarration = $narration
            ?? "Reversal of agent cash-out {$lockedTransaction->transaction_no}";

        $reversalReference = $this->transactionNumberService->generate(
            'AGR'
        );

        $this->customerAccountService->deposit(
            $customerAccount,
            $amount,
            $approvedBy,
            $reversalReference,
            $reversalNarration
        );

        $agentBalance->ledger_float -= $amount;
        $agentBalance->available_float -= $amount;
        $agentBalance->declared_physical_cash += $amount;

        if ($commissionAmount > 0) {
            $agentBalance->pending_commission -= $commissionAmount;
        }

        $agentBalance->save();

        $cashLedger = CashLedger::create([
            'reference_no' => $reversalReference,
            'branch_id' => $lockedTransaction->agent->branch_id,
            'vault_id' => null,
            'teller_id' => null,
            'user_id' => $approvedBy,
            'transaction_type' => 'AGENT_CASH_OUT_REVERSAL',
            'source_type' => AgentTransaction::class,
            'source_id' => $lockedTransaction->id,
            'entry_type' => 'DEBIT',
            'account_type' => 'AGENCY_FLOAT',
            'account_code' => 'AGENCY_FLOAT',
            'debit_account_key' => 'AGENCY_FLOAT',
            'credit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
            'debit' => $amount,
            'credit' => 0,
            'running_balance' => $agentBalance->ledger_float,
            'currency' => $agentBalance->currency,
            'narration' => $reversalNarration,
            'status' => 'PENDING',
            'approved_by' => $approvedBy,
            'transaction_date' => now(),
        ]);

        $this->glPostingService->postFromCashLedger($cashLedger);

        $agentBalance->last_transaction_id = $cashLedger->id;
        $agentBalance->save();

        return $this->finalizeReversal($lockedTransaction, $approvedBy);
    }

    /**
     * Reverses a TRANSFER: moves the amount back from the original
     * destination to the original source. No GL posting -- matches
     * AgentTransferService's own precedent that same-institution
     * transfers have zero net GL effect.
     *
     * @throws Exception
     */
    protected function reverseTransfer(
        AgentTransaction $lockedTransaction,
        int $approvedBy,
        ?string $narration
    ): AgentTransaction {
        $fromAccountId = $lockedTransaction->customer_account_id;
        $toAccountId = $lockedTransaction->channel_metadata['to_customer_account_id'] ?? null;

        if (! $fromAccountId || ! $toAccountId) {
            throw new Exception(
                'The original transfer is missing account information required to reverse it.'
            );
        }

        $fromAccount = CustomerAccount::find($fromAccountId);
        $toAccount = CustomerAccount::find($toAccountId);

        if (! $fromAccount || ! $toAccount) {
            throw new Exception(
                'One or both accounts for the original transfer no longer exist.'
            );
        }

        if ($fromAccount->status !== 'ACTIVE' || $toAccount->status !== 'ACTIVE') {
            throw new Exception(
                'Both accounts must be active to reverse this transfer.'
            );
        }

        $amount = (float) $lockedTransaction->amount;
        $commissionAmount = (float) $lockedTransaction->commission_amount;

        $orderedIds = $fromAccount->id < $toAccount->id
            ? [$fromAccount->id, $toAccount->id]
            : [$toAccount->id, $fromAccount->id];

        CustomerAccountBalance::whereIn('customer_account_id', $orderedIds)
            ->orderBy('customer_account_id')
            ->lockForUpdate()
            ->get();

        $reversalNarration = $narration
            ?? "Reversal of agent transfer {$lockedTransaction->transaction_no}";

        $reversalReference = $this->transactionNumberService->generate(
            'AGR'
        );

        $this->customerAccountService->withdraw(
            $toAccount,
            $amount,
            $approvedBy,
            $reversalReference,
            $reversalNarration
        );

        $this->customerAccountService->deposit(
            $fromAccount,
            $amount,
            $approvedBy,
            $reversalReference,
            $reversalNarration
        );

        if ($commissionAmount > 0) {
            $agentBalance = AgentBalance::where(
                'agent_id',
                $lockedTransaction->agent_id
            )
                ->lockForUpdate()
                ->first();

            if ($agentBalance) {
                if ((float) $agentBalance->pending_commission < $commissionAmount) {
                    throw new Exception(
                        'Agent has insufficient pending commission for reversal.'
                    );
                }

                $agentBalance->pending_commission -= $commissionAmount;
                $agentBalance->save();
            }
        }

        return $this->finalizeReversal($lockedTransaction, $approvedBy);
    }

    /**
     * Preserve the original AgentTransaction and record its reversal
     * state. The checker is the user who legally authorizes and
     * executes the approved reversal.
     */
    protected function finalizeReversal(
        AgentTransaction $lockedTransaction,
        int $approvedBy
    ): AgentTransaction {
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
    }
}
