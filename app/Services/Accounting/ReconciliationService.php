<?php

namespace App\Services\Accounting;

use App\Models\Reconciliation;
use App\Models\TillSession;
use Exception;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * Reconcile an OPEN till session against physically counted cash.
     *
     * Reconciliation records the result but does not close the till.
     *
     * @throws Exception
     */
    public function reconcileSession(
        TillSession $session,
        float $physicalCash,
        ?int $reconciledBy = null,
        ?string $notes = null
    ): Reconciliation {
        if ($physicalCash < 0) {
            throw new Exception(
                'Physical cash cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $session,
            $physicalCash,
            $reconciledBy,
            $notes
        ) {
            $lockedSession = TillSession::whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== 'OPEN') {
                throw new Exception(
                    'Only an open till session can be reconciled.'
                );
            }

            $existing = Reconciliation::where(
                'till_session_id',
                $lockedSession->id
            )
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new Exception(
                    'This till session has already been reconciled.'
                );
            }

            /*
             * TillSession.expected_cash is the authoritative expected
             * cash position for this session.
             *
             * CashLedger and TellerTransaction currently identify the
             * teller but not the exact till session, so aggregating them
             * here could mix activity from different sessions.
             */
            $systemBalance = round(
                (float) $lockedSession->expected_cash,
                2
            );

            $physicalCash = round($physicalCash, 2);

            $variance = round(
                $physicalCash - $systemBalance,
                2
            );

            $status = abs($variance) < 0.005
                ? 'MATCHED'
                : 'MISMATCHED';

            $reconciliation = Reconciliation::create([
                'till_session_id' => $lockedSession->id,
                'teller_id' => $lockedSession->teller_id,
                'branch_id' => $lockedSession->branch_id,

                'system_balance' => $systemBalance,
                'physical_cash' => $physicalCash,
                'variance' => $variance,

                'status' => $status,

                'reconciled_by' => $reconciledBy,
                'approved_by' => null,

                'notes' => $notes,
                'reconciled_at' => now(),
            ]);

            /*
             * Reconciliation state belongs to the reconciliation record.
             * TillSession.status remains OPEN until the separate
             * till-closing workflow closes it.
             */
            $lockedSession->update([
                'physical_cash' => $physicalCash,
                'variance' => $variance,
            ]);

            return $reconciliation->fresh();
        });
    }
}