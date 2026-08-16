#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Http/Controllers/Api/AgentDashboardController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Domain\MPay\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentReconciliation;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Traits\ApiResponse;

/**
 * Blueprint §15.1: Agency dashboard. Backend data layer for the widget
 * list Module 15 specifies -- not a frontend UI (that's separate,
 * unbuilt work), just the real numbers a real dashboard would call.
 *
 * Three widgets from the Blueprint's own list are honestly marked
 * not_yet_tracked rather than faked: open complaints (Module 14 is
 * unbuilt), TESSA alerts (Module 18's agent intelligence is unbuilt),
 * and high-risk agents (a real per-transaction risk assessment exists,
 * but no confirmed per-agent aggregation of it has been reviewed --
 * safer to flag this honestly than guess at an aggregation rule).
 *
 * Reconciliation exceptions and float shortages use each agent's MOST
 * RECENT reconciliation only, not a historical count -- a mismatch
 * that has since been re-reconciled and matched should not still show
 * as an open exception.
 */
class AgentDashboardController extends Controller
{
    use ApiResponse;

    public function agencyDashboard()
    {
        $today = now()->toDateString();

        $todayCompletedBase = AgentTransaction::whereDate('transaction_date', $today)
            ->where('status', 'COMPLETED');

        $latestReconciliationIds = AgentReconciliation::selectRaw('MAX(id) as id')
            ->groupBy('agent_id')
            ->pluck('id');

        $latestReconciliations = AgentReconciliation::whereIn('id', $latestReconciliationIds)->get();

        return $this->success(
            [
                'agents' => [
                    'total' => Agent::count(),
                    'active' => Agent::where('status', AgentStatus::ACTIVE->value)->count(),
                    'pending_approval' => Agent::where('status', AgentStatus::PENDING_APPROVAL->value)->count(),
                    'suspended' => Agent::where('status', AgentStatus::SUSPENDED->value)->count(),
                    'dormant' => Agent::where('status', AgentStatus::DORMANT->value)->count(),
                ],
                'terminals' => [
                    'active' => AgentTerminal::where('status', 'ACTIVE')->count(),
                    'non_compliant' => AgentTerminal::where('geo_fence_compliant', false)->count(),
                ],
                'daily_transaction_values' => [
                    'date' => $today,
                    'total' => (float) (clone $todayCompletedBase)->sum('amount'),
                    'cash_in' => (float) (clone $todayCompletedBase)->where('transaction_type', 'CASH_IN')->sum('amount'),
                    'cash_out' => (float) (clone $todayCompletedBase)->where('transaction_type', 'CASH_OUT')->sum('amount'),
                    'transfer' => (float) (clone $todayCompletedBase)->where('transaction_type', 'TRANSFER')->sum('amount'),
                ],
                'reconciliation' => [
                    'exceptions' => $latestReconciliations->where('status', 'MISMATCHED')->count(),
                    'float_shortages' => $latestReconciliations->where('status', 'MISMATCHED')->filter(
                        fn (AgentReconciliation $r) => (float) $r->variance < 0
                    )->count(),
                ],
                'complaints' => [
                    'open' => 0,
                    'not_yet_tracked' => true,
                ],
                'tessa_alerts' => [
                    'count' => 0,
                    'not_yet_tracked' => true,
                ],
                'high_risk_agents' => [
                    'count' => 0,
                    'not_yet_tracked' => true,
                ],
            ],
            'Agency dashboard generated successfully.'
        );
    }
}
MBOS_EOF

echo "Dashboard controller applied."
