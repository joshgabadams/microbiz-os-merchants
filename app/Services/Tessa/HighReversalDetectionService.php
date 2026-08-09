<?php

namespace App\Services\Tessa;

use App\Models\Teller;
use App\Models\TellerTransaction;
use App\Models\TessaAlert;

/**
 * Detects a teller with an unusually high count of reversed
 * transactions within a rolling window -- a common way errors or
 * fraud get quietly covered up, distinct from a single large variance.
 */
class HighReversalDetectionService
{
    use CreatesTessaAlerts;

    /**
     * @return TessaAlert[] alerts created or updated this run
     */
    public function detect(): array
    {
        $config = config('tessa.high_reversal');
        $windowStart = now()->subHours($config['window_hours']);
        $alerts = [];

        $reversalCounts = TellerTransaction::where('is_reversed', true)
            ->where('reversed_at', '>=', $windowStart)
            ->selectRaw('teller_id, COUNT(*) as reversal_count')
            ->groupBy('teller_id')
            ->having('reversal_count', '>=', $config['count_threshold'])
            ->get();

        foreach ($reversalCounts as $row) {
            $teller = Teller::find($row->teller_id);

            if (! $teller) {
                continue;
            }

            $tellerLabel = $teller->teller_code ?? "Teller #{$teller->id}";
            $count = (int) $row->reversal_count;

            $severity = match (true) {
                $count >= $config['count_threshold'] * 3 => 'HIGH',
                $count >= $config['count_threshold'] * 2 => 'MEDIUM',
                default => 'LOW',
            };

            $alerts[] = $this->upsertOpenAlert(
                alertType: 'HIGH_REVERSAL',
                severity: $severity,
                subjectType: Teller::class,
                subjectId: $teller->id,
                title: "{$tellerLabel} has {$count} reversed transactions in the last {$config['window_hours']}h",
                description: "{$tellerLabel} reversed {$count} transactions within the last {$config['window_hours']} hours, exceeding the configured threshold of {$config['count_threshold']}. A repeated pattern of reversals can indicate errors being covered up or deliberate misuse of the reversal path.",
                metadata: [
                    'teller_id' => $teller->id,
                    'reversal_count' => $count,
                    'window_hours' => $config['window_hours'],
                    'threshold' => $config['count_threshold'],
                ]
            );
        }

        return $alerts;
    }
}
