<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentReconciliation;
use App\Models\AgentTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Module 13: Agent Reconciliation. Closes the "float mismatch"
 * exception category for physical cash specifically -- the most
 * concrete, directly buildable piece of Module 13's broader
 * reconciliation list, since it stays within a single real system
 * (agent_transactions/agent_balances) rather than guessing at
 * external FINCORE360/processor posting semantics.
 *
 * Mirrors ReconciliationService's exact till-session pattern: compare
 * a recorded value against an authoritative computed value, flag
 * MATCHED/MISMATCHED, record the result. No session/period concept
 * exists for agents yet, so this is a point-in-time check rather than
 * bounded to a session -- reconcileAgent() can be called repeatedly.
 *
 * Reversed transactions are correctly excluded automatically: the
 * real AgentTransactionReversalService flips a reversed transaction's
 * status to REVERSED (never creates a new AgentTransaction row), so
 * filtering by status = COMPLETED already accounts for reversals
 * without any extra logic here.
 */
class AgentReconciliationService
{
    /**
     * @throws Exception
     */
    public function reconcileAgent(
        Agent $agent,
        ?int $reconciledBy = null,
        ?string $notes = null
    ): AgentReconciliation {
        return DB::transaction(function () use ($agent, $reconciledBy, $notes) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception(
                    "Agent {$agent->agent_code} has no float balance to reconcile."
                );
            }

            /*
             * System-expected physical cash is derived from real
             * transaction history rather than aggregating anything
             * external -- CASH_IN increases physical cash (agent
             * receives it from the customer), CASH_OUT decreases it
             * (agent pays it out).
             */
            $totalCashIn = (float) AgentTransaction::where('agent_id', $agent->id)
                ->where('transaction_type', 'CASH_IN')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            $totalCashOut = (float) AgentTransaction::where('agent_id', $agent->id)
                ->where('transaction_type', 'CASH_OUT')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            $systemExpectedPhysicalCash = round($totalCashIn - $totalCashOut, 2);
            $declaredPhysicalCash = round((float) $balance->declared_physical_cash, 2);
            $variance = round($declaredPhysicalCash - $systemExpectedPhysicalCash, 2);

            $status = abs($variance) < 0.005 ? 'MATCHED' : 'MISMATCHED';

            return AgentReconciliation::create([
                'agent_id' => $agent->id,
                'branch_id' => $agent->branch_id,

                'system_expected_physical_cash' => $systemExpectedPhysicalCash,
                'declared_physical_cash' => $declaredPhysicalCash,
                'variance' => $variance,

                'status' => $status,

                'reconciled_by' => $reconciledBy,
                'approved_by' => null,

                'notes' => $notes,
                'reconciled_at' => now(),
            ]);
        });
    }
}
