<?php

namespace App\Services\Branch;

use App\Models\Branch;
use App\Models\BranchBusinessDay;
use App\Models\TillSession;
use Exception;
use Illuminate\Support\Facades\DB;

class BranchBusinessDayService
{
    /**
     * Open a business day for a branch.
     *
     * A branch may have only one OPEN business day at a time.
     *
     * A business date that has already been used by the branch
     * cannot be opened again.
     *
     * @throws Exception
     */
    public function open(
        int $branchId,
        string $businessDate,
        int $openedBy,
        ?string $notes = null
    ): BranchBusinessDay {
        return DB::transaction(function () use (
            $branchId,
            $businessDate,
            $openedBy,
            $notes
        ) {
            /*
             * Ensure the branch exists before attempting to
             * create a business day for it.
             */
            Branch::findOrFail($branchId);

            /*
             * A branch may have only one OPEN business day.
             *
             * Lock the existing row, when present, so competing
             * close/open operations cannot mutate it underneath us.
             */
            $existingOpenDay = BranchBusinessDay::where(
                'branch_id',
                $branchId
            )
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($existingOpenDay !== null) {
                throw new Exception(
                    "Branch {$branchId} already has an open business day."
                );
            }

            /*
             * A previously used business date must never be reopened,
             * even if that business day is already CLOSED.
             */
            $existingDay = BranchBusinessDay::where(
                'branch_id',
                $branchId
            )
                ->whereDate(
                    'business_date',
                    $businessDate
                )
                ->lockForUpdate()
                ->first();

            if ($existingDay !== null) {
                throw new Exception(
                    "Business date {$businessDate} has already been used for branch {$branchId}."
                );
            }

            return BranchBusinessDay::create([
                'branch_id' => $branchId,
                'business_date' => $businessDate,
                'status' => 'OPEN',
                'opened_by' => $openedBy,
                'opened_at' => now(),
                'closed_by' => null,
                'closed_at' => null,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Close the currently OPEN business day.
     *
     * A business day cannot be closed while any till session
     * belonging to that exact business day remains OPEN.
     *
     * Till sessions must therefore complete their reconciliation
     * and close workflow before branch end-of-day can complete.
     *
     * @throws Exception
     */
    public function close(
        int $branchId,
        int $closedBy,
        ?string $notes = null
    ): BranchBusinessDay {
        return DB::transaction(function () use (
            $branchId,
            $closedBy,
            $notes
        ) {
            /*
             * Lock the OPEN business day for the duration of the
             * close operation.
             */
            $businessDay = BranchBusinessDay::where(
                'branch_id',
                $branchId
            )
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($businessDay === null) {
                throw new Exception(
                    "Branch {$branchId} does not have an open business day."
                );
            }

            /*
             * A branch business day must not close while a till
             * session attached to this exact business day remains OPEN.
             *
             * Scope this check by branch_business_day_id rather than
             * branch_id alone. That prevents historical sessions from
             * other business days from interfering with today's close.
             */
            $hasOpenTillSessions = TillSession::where(
                'branch_business_day_id',
                $businessDay->id
            )
                ->where('status', 'OPEN')
                ->exists();

            if ($hasOpenTillSessions) {
                throw new Exception(
                    "Branch {$branchId} cannot close its business day while till sessions remain open."
                );
            }

            /*
             * All till sessions for this business day are closed,
             * so the branch business day may now be closed.
             */
            $businessDay->update([
                'status' => 'CLOSED',
                'closed_by' => $closedBy,
                'closed_at' => now(),
                'notes' => $notes ?? $businessDay->notes,
            ]);

            return $businessDay->fresh();
        });
    }

    /**
     * Return the currently OPEN business day, if one exists.
     */
    public function currentOpenDay(
        int $branchId
    ): ?BranchBusinessDay {
        return BranchBusinessDay::where(
            'branch_id',
            $branchId
        )
            ->where('status', 'OPEN')
            ->first();
    }

    /**
     * Require the branch to have an OPEN business day.
     *
     * @throws Exception
     */
    public function requireOpenDay(
        int $branchId
    ): BranchBusinessDay {
        $businessDay = $this->currentOpenDay($branchId);

        if ($businessDay === null) {
            throw new Exception(
                "Branch {$branchId} does not have an open business day."
            );
        }

        return $businessDay;
    }

    /**
     * Determine whether the branch currently has an OPEN business day.
     */
    public function isOpen(
        int $branchId
    ): bool {
        return BranchBusinessDay::where(
            'branch_id',
            $branchId
        )
            ->where('status', 'OPEN')
            ->exists();
    }
}
