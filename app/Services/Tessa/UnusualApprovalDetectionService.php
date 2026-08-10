<?php

namespace App\Services\Tessa;

use App\Models\ApprovalRequest;
use App\Models\TessaAlert;

/**
 * Detects approvals decided implausibly fast to be a genuine review --
 * the natural complement to the maker-checker control itself: a
 * maker-checker step that gets rubber-stamped provides no real second
 * pair of eyes, even though it technically satisfies the control.
 */
class UnusualApprovalDetectionService
{
    use CreatesTessaAlerts;

    /**
     * @return TessaAlert[] alerts created or updated this run
     */
    public function detect(): array
    {
        $thresholdSeconds = config('tessa.unusual_approval.rubber_stamp_seconds');
        $alerts = [];

        $approved = ApprovalRequest::where('status', 'APPROVED')
            ->whereNotNull('approved_at')
            ->with(['maker', 'checker'])
            ->get();

        foreach ($approved as $request) {
            $latencySeconds = $request->created_at->diffInSeconds($request->approved_at);

            if ($latencySeconds > $thresholdSeconds) {
                continue;
            }

            $checkerLabel = $request->checker->name ?? "User #{$request->checker_id}";

            $alerts[] = $this->upsertOpenAlert(
                alertType: 'UNUSUAL_APPROVAL',
                severity: $latencySeconds <= 5 ? 'HIGH' : 'MEDIUM',
                subjectType: ApprovalRequest::class,
                subjectId: $request->id,
                title: "Approval {$request->request_no} decided in {$latencySeconds}s",
                description: "Approval request {$request->request_no} ({$request->request_type}) was approved by {$checkerLabel} just {$latencySeconds} seconds after it was created, well under the {$thresholdSeconds}s threshold for a plausible independent review. This may indicate the checker approved without genuinely reviewing the request.",
                metadata: [
                    'approval_request_id' => $request->id,
                    'request_type' => $request->request_type,
                    'maker_id' => $request->maker_id,
                    'checker_id' => $request->checker_id,
                    'latency_seconds' => $latencySeconds,
                    'threshold_seconds' => $thresholdSeconds,
                ]
            );
        }

        return $alerts;
    }
}
