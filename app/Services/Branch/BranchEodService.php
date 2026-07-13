<?php

namespace App\Services\Branch;

use App\Models\ApprovalRequest;
use App\Models\BranchEod;
use App\Models\Teller;
use App\Models\TellerCashBalance;
use App\Models\TellerTransaction;
use App\Models\VaultCashBalance;
use App\Models\VaultTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class BranchEodService
{
    public function close(
        int $branchId,
        string $businessDate,
        int $closedBy,
        ?string $note = null
    ): BranchEod {
        return DB::transaction(function () use (
            $branchId,
            $businessDate,
            $closedBy,
            $note
        ) {
            $totalTellers = Teller::where('branch_id', $branchId)->count();

            $closedTellers = Teller::where('branch_id', $branchId)
                ->where('status', 'CLOSED')
                ->count();

            $balancedTellers = TellerCashBalance::whereDate('business_date', $businessDate)
                ->where('status', 'BALANCED')
                ->whereHas('teller', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->count();

            $vaultBalanced = VaultCashBalance::whereDate('business_date', $businessDate)
                ->where('status', 'BALANCED')
                ->whereHas('vault', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->exists();

            $hasPendingApprovals = ApprovalRequest::where('status', 'PENDING')->exists();

            $hasUnpostedTransactions =
                TellerTransaction::where('posted', false)->exists()
                || VaultTransaction::where('posted', false)->exists();

            $canClose =
                $totalTellers > 0
                && $closedTellers === $totalTellers
                && $balancedTellers === $totalTellers
                && $vaultBalanced
                && !$hasPendingApprovals
                && !$hasUnpostedTransactions;

            $status = $canClose ? 'CLOSED' : 'FAILED';

            $eod = BranchEod::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                ],
                [
                    'status' => $status,
                    'total_tellers' => $totalTellers,
                    'closed_tellers' => $closedTellers,
                    'balanced_tellers' => $balancedTellers,
                    'vault_balanced' => $vaultBalanced,
                    'has_pending_approvals' => $hasPendingApprovals,
                    'has_unposted_transactions' => $hasUnpostedTransactions,
                    'closed_by' => $canClose ? $closedBy : null,
                    'closed_at' => $canClose ? now() : null,
                    'note' => $note,
                ]
            );

            if (!$canClose) {
                throw new Exception(
                    'Branch EOD failed. Ensure all tellers are closed and balanced, vault is balanced, no pending approvals, and no unposted transactions exist.'
                );
            }

            return $eod;
        });
    }
}