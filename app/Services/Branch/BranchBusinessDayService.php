<?php

namespace App\Services\Branch;

use App\Models\Branch;
use App\Models\BranchBusinessDay;
use Exception;
use Illuminate\Support\Facades\DB;

class BranchBusinessDayService
{
    /**
     * Open a business day for a branch.
     *
     * A branch may have only one OPEN business day at a time.
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
            Branch::findOrFail($branchId);

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
