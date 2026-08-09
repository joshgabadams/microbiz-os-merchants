<?php

namespace App\Console\Commands;

use App\Services\Tessa\HighReversalDetectionService;
use App\Services\Tessa\TellerVarianceDetectionService;
use App\Services\Tessa\UnusualApprovalDetectionService;
use Illuminate\Console\Command;

class TessaDetectAlerts extends Command
{
    protected $signature = 'tessa:detect-alerts';

    protected $description = 'Run all TESSA alert detection rules (teller variance, high reversal, unusual approval)';

    public function handle(
        TellerVarianceDetectionService $tellerVariance,
        HighReversalDetectionService $highReversal,
        UnusualApprovalDetectionService $unusualApproval
    ): int {
        $varianceAlerts = $tellerVariance->detect();
        $this->info('Teller variance: '.count($varianceAlerts).' alert(s) open/updated.');

        $reversalAlerts = $highReversal->detect();
        $this->info('High reversal: '.count($reversalAlerts).' alert(s) open/updated.');

        $approvalAlerts = $unusualApproval->detect();
        $this->info('Unusual approval: '.count($approvalAlerts).' alert(s) open/updated.');

        return self::SUCCESS;
    }
}
