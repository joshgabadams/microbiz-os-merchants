<?php

namespace App\Services\Tessa;

use App\Models\TellerCashBalance;
use App\Models\TessaAlert;

/**
 * Detects teller cash variances from real balancing records
 * (TellerBalancingService already computes variance/status -- this
 * reads that output, it doesn't recompute anything).
 *
 * Severity scales by variance as a percentage of expected cash, not an
 * absolute currency figure -- see config/tessa.php for why.
 */
class TellerVarianceDetectionService
{
    use CreatesTessaAlerts;

    /**
     * @return TessaAlert[] alerts created or updated this run
     */
    public function detect(): array
    {
        $config = config('tessa.teller_variance');
        $alerts = [];

        $unbalanced = TellerCashBalance::where('status', '!=', 'BALANCED')
            ->with('teller')
            ->get();

        foreach ($unbalanced as $balance) {
            $expected = (float) $balance->expected_cash;
            $variance = (float) $balance->variance;

            // A variance against zero expected cash has no meaningful
            // percentage -- treat as HIGH rather than divide by zero.
            $variancePct = $expected != 0.0
                ? abs($variance / $expected) * 100
                : 100.0;

            $severity = match (true) {
                $variancePct >= $config['high_threshold_pct'] => 'HIGH',
                $variancePct >= $config['medium_threshold_pct'] => 'MEDIUM',
                default => 'LOW',
            };

            $direction = $balance->status === 'OVER' ? 'over' : 'short';
            $tellerLabel = $balance->teller->teller_code ?? "Teller #{$balance->teller_id}";

            $alerts[] = $this->upsertOpenAlert(
                alertType: 'TELLER_VARIANCE',
                severity: $severity,
                subjectType: TellerCashBalance::class,
                subjectId: $balance->id,
                title: "{$tellerLabel} is {$direction} by {$variancePct}%",
                description: "On {$balance->business_date->toDateString()}, {$tellerLabel} balanced with physical cash of {$balance->physical_cash} against an expected {$balance->expected_cash} -- a variance of {$balance->variance} ({$direction}), which is " . round($variancePct, 2) . "% of expected cash.",
                metadata: [
                    'teller_id' => $balance->teller_id,
                    'business_date' => $balance->business_date->toDateString(),
                    'expected_cash' => $expected,
                    'physical_cash' => (float) $balance->physical_cash,
                    'variance' => $variance,
                    'variance_pct' => round($variancePct, 2),
                    'status' => $balance->status,
                    'thresholds_used' => $config,
                ]
            );
        }

        return $alerts;
    }
}
