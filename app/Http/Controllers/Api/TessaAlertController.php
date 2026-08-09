<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tessa\ResolveTessaAlertRequest;
use App\Models\TessaAlert;
use App\Services\Tessa\HighReversalDetectionService;
use App\Services\Tessa\TellerVarianceDetectionService;
use App\Services\Tessa\UnusualApprovalDetectionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class TessaAlertController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = TessaAlert::query()->latest('detected_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        return $this->success($query->get(), 'TESSA alerts retrieved successfully.');
    }

    public function show(TessaAlert $tessaAlert)
    {
        return $this->success($tessaAlert, 'TESSA alert retrieved successfully.');
    }

    public function acknowledge(Request $request, TessaAlert $tessaAlert)
    {
        try {
            if ($tessaAlert->status !== 'OPEN') {
                throw new Exception("Alert is already {$tessaAlert->status}.");
            }

            $tessaAlert->update([
                'status' => 'ACKNOWLEDGED',
                'acknowledged_by' => $request->user()->id,
                'acknowledged_at' => now(),
            ]);

            return $this->success($tessaAlert->fresh(), 'Alert acknowledged successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function resolve(ResolveTessaAlertRequest $request, TessaAlert $tessaAlert)
    {
        try {
            if ($tessaAlert->status === 'RESOLVED') {
                throw new Exception('Alert is already resolved.');
            }

            $tessaAlert->update([
                'status' => 'RESOLVED',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'resolution_note' => $request->resolution_note,
            ]);

            return $this->success($tessaAlert->fresh(), 'Alert resolved successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Manually trigger all detection rules, rather than waiting for the
     * scheduler -- useful for testing and for demonstrating the feature
     * before cron is configured in a given environment.
     */
    public function detect(
        TellerVarianceDetectionService $tellerVariance,
        HighReversalDetectionService $highReversal,
        UnusualApprovalDetectionService $unusualApproval
    ) {
        $results = [
            'teller_variance' => count($tellerVariance->detect()),
            'high_reversal' => count($highReversal->detect()),
            'unusual_approval' => count($unusualApproval->detect()),
        ];

        return $this->success($results, 'Detection run completed.');
    }
}
