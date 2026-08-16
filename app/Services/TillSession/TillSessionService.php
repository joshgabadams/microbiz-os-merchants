<?php

namespace App\Services\TillSession;

use App\Models\Reconciliation;
use App\Models\Teller;
use App\Models\TillSession;
use App\Services\Branch\BranchBusinessDayService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TillSessionService
{
    public function __construct(
        protected BranchBusinessDayService $branchBusinessDayService
    ) {
    }

    /**
     * Open a till session.
     *
     * A till session may only be opened while the teller's branch
     * has an OPEN business day.
     *
     * Only one OPEN till session may exist for a teller at a time.
     *
     * @throws Exception
     */
    public function open(array $data): TillSession
    {
        return DB::transaction(function () use ($data) {
            $teller = Teller::whereKey($data['teller_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $teller->active) {
                throw new Exception(
                    'Cannot open a till session for an inactive teller.'
                );
            }

            $businessDay = $this->branchBusinessDayService
                ->requireOpenDay($teller->branch_id);

            $existing = TillSession::where(
                'teller_id',
                $teller->id
            )
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new Exception(
                    'This teller already has an open session.'
                );
            }

            $openingFloat = round(
                (float) $data['opening_float'],
                2
            );

            if ($openingFloat < 0) {
                throw new Exception(
                    'Opening float cannot be negative.'
                );
            }

            return TillSession::create([
                'session_reference' => $this->generateSessionReference(),

                'teller_id' => $teller->id,

                'branch_id' => $teller->branch_id,

                'branch_business_day_id' => $businessDay->id,

                'vault_id' => $teller->vault_id,

                'opened_by' => $data['opened_by'],

                'closed_by' => null,

                'opening_float' => $openingFloat,

                'current_balance' => $openingFloat,

                'expected_cash' => $openingFloat,

                'physical_cash' => null,

                'variance' => 0,

                'status' => 'OPEN',

                'opened_at' => now(),

                'closed_at' => null,
            ]);
        });
    }

    /**
     * Close a reconciled till session.
     *
     * Production-fast policy:
     *
     * - the session must still be OPEN;
     * - the associated branch business day must still be OPEN;
     * - the session must already have a reconciliation;
     * - only a MATCHED reconciliation may close automatically;
     * - mismatched reconciliations remain blocked until a future
     *   controlled variance-approval workflow is implemented.
     *
     * @throws Exception
     */
    public function close(
        int $sessionId,
        int $closedBy
    ): TillSession {
        return DB::transaction(function () use (
            $sessionId,
            $closedBy
        ) {
            $session = TillSession::whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'OPEN') {
                throw new Exception(
                    'Only an open till session can be closed.'
                );
            }

            if ($session->branch_business_day_id === null) {
                throw new Exception(
                    'Till session is not associated with a branch business day.'
                );
            }

            $businessDay = $this->branchBusinessDayService
                ->requireOpenDay($session->branch_id);

            if ($businessDay->id !== $session->branch_business_day_id) {
                throw new Exception(
                    'Till session does not belong to the current open business day.'
                );
            }

            $reconciliation = Reconciliation::where(
                'till_session_id',
                $session->id
            )
                ->lockForUpdate()
                ->first();

            if ($reconciliation === null) {
                throw new Exception(
                    'Till session must be reconciled before it can be closed.'
                );
            }

            if ($reconciliation->status !== 'MATCHED') {
                throw new Exception(
                    'Till session cannot be closed while reconciliation is mismatched.'
                );
            }

            $session->update([
                'physical_cash' => $reconciliation->physical_cash,

                'variance' => $reconciliation->variance,

                'closed_by' => $closedBy,

                'status' => 'CLOSED',

                'closed_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    /**
     * Generate a unique till-session reference.
     */
    protected function generateSessionReference(): string
    {
        do {
            $reference = 'TS-'
                . now()->format('YmdHis')
                . '-'
                . strtoupper(Str::random(6));
        } while (
            TillSession::where(
                'session_reference',
                $reference
            )->exists()
        );

        return $reference;
    }
}