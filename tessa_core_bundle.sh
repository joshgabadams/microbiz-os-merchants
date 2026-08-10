#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_08_000001_create_tessa_alerts_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tessa_alerts', function (Blueprint $table) {

            $table->id();

            $table->string('alert_type');
            // TELLER_VARIANCE, HIGH_REVERSAL, UNUSUAL_APPROVAL (first pass);
            // VAULT_LIQUIDITY, EXCESSIVE_FLOAT, CASH_FORECAST,
            // MERCHANT_TRANSACTION reserved for later phases.

            $table->string('severity');
            // LOW, MEDIUM, HIGH

            // Polymorphic subject: the teller, the approval request, etc.
            // that this alert is actually about.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('title');

            // Per TESSA's own design principle: "must provide reasons for
            // every alert" -- this is the human-readable explanation, not
            // just a severity flag.
            $table->text('description');

            // The specific numbers behind the alert (variance amount,
            // reversal count, approval latency in seconds, thresholds
            // used) -- kept structured for the eventual dashboard, not
            // just embedded in the description text.
            $table->json('metadata')->nullable();

            $table->string('status')->default('OPEN');
            // OPEN, ACKNOWLEDGED, RESOLVED

            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamp('detected_at');

            $table->timestamps();

            $table->index(['alert_type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tessa_alerts');
    }
};
MBOS_EOF

cat > app/Models/TessaAlert.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TessaAlert extends Model
{
    protected $fillable = [
        'alert_type',
        'severity',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'metadata',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'detected_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
MBOS_EOF

cat > config/tessa.php << 'MBOS_EOF'
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TESSA Alert Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | None of the numbers below are confirmed bank policy -- they are
    | reasonable, clearly-flagged defaults chosen to avoid inventing an
    | absolute currency figure (which would vary hugely by branch size).
    | Adjust here once real thresholds are confirmed with Operations/Risk.
    |
    */

    'teller_variance' => [
        // Severity is scaled by variance as a PERCENTAGE of expected
        // cash, not an absolute amount -- self-scaling across branch
        // sizes rather than guessing a naira figure.
        'high_threshold_pct' => 5.0,    // variance >= 5% of expected cash
        'medium_threshold_pct' => 1.0,  // variance >= 1% of expected cash
        // Below medium_threshold_pct is still alerted (any non-zero
        // variance in a well-controlled cash operation is worth a
        // record), just at LOW severity.
    ],

    'high_reversal' => [
        // Reversals by the same teller within the rolling window below.
        'count_threshold' => 3,
        'window_hours' => 24,
    ],

    'unusual_approval' => [
        // An approval decided faster than this is treated as too fast
        // for genuine review ("rubber-stamping").
        'rubber_stamp_seconds' => 30,
    ],

];
MBOS_EOF

mkdir -p app/Services/Tessa
cat > app/Services/Tessa/CreatesTessaAlerts.php << 'MBOS_EOF'
<?php

namespace App\Services\Tessa;

use App\Models\TessaAlert;

/**
 * Shared alert-creation logic for all TESSA detection services.
 *
 * Reuses an existing OPEN alert for the same (alert_type, subject)
 * rather than creating a new one every detection run -- a teller with
 * an ongoing variance shouldn't accumulate a fresh alert every time the
 * scheduler fires. Once a human resolves an alert, a genuinely new
 * occurrence creates a fresh one rather than silently reopening the old
 * record (which would lose the resolution history).
 */
trait CreatesTessaAlerts
{
    protected function upsertOpenAlert(
        string $alertType,
        string $severity,
        string $subjectType,
        int $subjectId,
        string $title,
        string $description,
        array $metadata
    ): TessaAlert {
        $existing = TessaAlert::where('alert_type', $alertType)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'OPEN')
            ->first();

        if ($existing) {
            $existing->update([
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'metadata' => $metadata,
                'detected_at' => now(),
            ]);

            return $existing->fresh();
        }

        return TessaAlert::create([
            'alert_type' => $alertType,
            'severity' => $severity,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'status' => 'OPEN',
            'detected_at' => now(),
        ]);
    }
}
MBOS_EOF

cat > app/Services/Tessa/TellerVarianceDetectionService.php << 'MBOS_EOF'
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
MBOS_EOF

cat > app/Services/Tessa/HighReversalDetectionService.php << 'MBOS_EOF'
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
MBOS_EOF

cat > app/Services/Tessa/UnusualApprovalDetectionService.php << 'MBOS_EOF'
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
MBOS_EOF

mkdir -p app/Console/Commands
cat > app/Console/Commands/TessaDetectAlerts.php << 'MBOS_EOF'
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
MBOS_EOF

cat > routes/console.php << 'MBOS_EOF'
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First scheduled task in this application -- for this to actually run
// in production, cron must call `php artisan schedule:run` every
// minute (standard Laravel deployment requirement, not yet configured
// anywhere in this project as far as this session has seen).
Schedule::command('tessa:detect-alerts')->everyFifteenMinutes();
MBOS_EOF

cat > app/Http/Controllers/Api/TessaAlertController.php << 'MBOS_EOF'
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
MBOS_EOF

mkdir -p app/Http/Requests/Tessa
cat > app/Http/Requests/Tessa/ResolveTessaAlertRequest.php << 'MBOS_EOF'
<?php

namespace App\Http\Requests\Tessa;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveTessaAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
MBOS_EOF

echo "TESSA core (standalone files) applied. Next: paste current routes/api.php and RbacSeeder.php so routes/permissions can be added safely."