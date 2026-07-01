<?php

namespace App\Services\Accounting;

use App\Models\TillSession;
use App\Models\Reconciliation;
use App\Models\CashLedger;
use Exception;

class ReconciliationService
{
    /**
     * Run reconciliation for a teller session
     */
    public function reconcileSession(TillSession $session, float $physicalCash): Reconciliation
    {
        // 1. Calculate system balance
        $systemBalance = $this->calculateSystemBalance($session);

        // 2. Compute variance
        $variance = $physicalCash - $systemBalance;

        // 3. Create reconciliation record
        $reconciliation = Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id'       => $session->teller_id,
            'branch_id'       => $session->branch_id,
            'system_balance'  => $systemBalance,
            'physical_cash'   => $physicalCash,
            'variance'        => $variance,
            'status'          => $variance == 0 ? 'MATCHED' : 'MISMATCHED',
            'reconciled_at'   => now(),
        ]);

        // 4. If mismatch → flag session
        if ($variance != 0) {
            $session->update([
                'status' => 'MISMATCHED'
            ]);
        } else {
            $session->update([
                'status' => 'RECONCILED'
            ]);
        }

        return $reconciliation;
    }

    /**
     * Compute system balance from ledger
     */
    private function calculateSystemBalance(TillSession $session): float
    {
        $debits = CashLedger::where('till_session_id', $session->id)
            ->sum('debit');

        $credits = CashLedger::where('till_session_id', $session->id)
            ->sum('credit');

        return $debits - $credits;
    }
}