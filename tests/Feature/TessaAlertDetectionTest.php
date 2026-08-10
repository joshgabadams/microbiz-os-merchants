<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Teller;
use App\Models\TellerCashBalance;
use App\Models\TellerTransaction;
use App\Models\TessaAlert;
use App\Models\User;
use App\Services\Tessa\HighReversalDetectionService;
use App\Services\Tessa\TellerVarianceDetectionService;
use App\Services\Tessa\UnusualApprovalDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TessaAlertDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTeller(): Teller
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        return Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-'.uniqid(),
            'display_name' => 'Test Teller',
            'active' => true,
            'status' => 'OPEN',
        ]);
    }

    // ---------- Teller Variance ----------

    public function test_short_teller_balance_creates_high_severity_alert(): void
    {
        $teller = $this->makeTeller();

        TellerCashBalance::create([
            'teller_id' => $teller->id,
            'business_date' => now()->toDateString(),
            'opening_cash' => 10000,
            'float_received' => 0,
            'customer_deposits' => 0,
            'customer_withdrawals' => 0,
            'float_returned' => 0,
            'expected_cash' => 10000,
            'physical_cash' => 9000,
            'variance' => -1000,
            'status' => 'SHORT',
            'balanced_by' => User::factory()->create()->id,
            'balanced_at' => now(),
        ]);

        $alerts = app(TellerVarianceDetectionService::class)->detect();

        $this->assertCount(1, $alerts);
        $this->assertEquals('HIGH', $alerts[0]->severity);
        $this->assertEquals('TELLER_VARIANCE', $alerts[0]->alert_type);
        $this->assertEquals('OPEN', $alerts[0]->status);
    }

    public function test_balanced_teller_creates_no_alert(): void
    {
        $teller = $this->makeTeller();

        TellerCashBalance::create([
            'teller_id' => $teller->id,
            'business_date' => now()->toDateString(),
            'opening_cash' => 10000,
            'float_received' => 0,
            'customer_deposits' => 0,
            'customer_withdrawals' => 0,
            'float_returned' => 0,
            'expected_cash' => 10000,
            'physical_cash' => 10000,
            'variance' => 0,
            'status' => 'BALANCED',
            'balanced_by' => User::factory()->create()->id,
            'balanced_at' => now(),
        ]);

        $alerts = app(TellerVarianceDetectionService::class)->detect();

        $this->assertCount(0, $alerts);
        $this->assertEquals(0, TessaAlert::count());
    }

    public function test_rerunning_variance_detection_updates_not_duplicates(): void
    {
        $teller = $this->makeTeller();

        TellerCashBalance::create([
            'teller_id' => $teller->id,
            'business_date' => now()->toDateString(),
            'opening_cash' => 10000,
            'float_received' => 0,
            'customer_deposits' => 0,
            'customer_withdrawals' => 0,
            'float_returned' => 0,
            'expected_cash' => 10000,
            'physical_cash' => 9500,
            'variance' => -500,
            'status' => 'SHORT',
            'balanced_by' => User::factory()->create()->id,
            'balanced_at' => now(),
        ]);

        $service = app(TellerVarianceDetectionService::class);
        $service->detect();
        $service->detect();
        $service->detect();

        $this->assertEquals(1, TessaAlert::where('alert_type', 'TELLER_VARIANCE')->count());
    }

    // ---------- High Reversal ----------

    public function test_teller_with_reversals_at_threshold_creates_alert(): void
    {
        $teller = $this->makeTeller();
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            TellerTransaction::create([
                'teller_id' => $teller->id,
                'transaction_no' => 'TLR-TEST-'.$i,
                'transaction_type' => 'CUSTOMER_WITHDRAWAL',
                'amount' => 1000,
                'currency' => 'NGN',
                'performed_by' => $user->id,
                'transaction_date' => now(),
                'posted' => true,
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $user->id,
            ]);
        }

        $alerts = app(HighReversalDetectionService::class)->detect();

        $this->assertCount(1, $alerts);
        $this->assertEquals('HIGH_REVERSAL', $alerts[0]->alert_type);
        $this->assertEquals(3, $alerts[0]->metadata['reversal_count']);
    }

    public function test_teller_with_reversals_below_threshold_creates_no_alert(): void
    {
        $teller = $this->makeTeller();
        $user = User::factory()->create();

        TellerTransaction::create([
            'teller_id' => $teller->id,
            'transaction_no' => 'TLR-TEST-1',
            'transaction_type' => 'CUSTOMER_WITHDRAWAL',
            'amount' => 1000,
            'currency' => 'NGN',
            'performed_by' => $user->id,
            'transaction_date' => now(),
            'posted' => true,
            'is_reversed' => true,
            'reversed_at' => now(),
            'reversed_by' => $user->id,
        ]);

        $alerts = app(HighReversalDetectionService::class)->detect();

        $this->assertCount(0, $alerts);
    }

    public function test_reversals_outside_window_are_not_counted(): void
    {
        $teller = $this->makeTeller();
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $transaction = TellerTransaction::create([
                'teller_id' => $teller->id,
                'transaction_no' => 'TLR-TEST-OLD-'.$i,
                'transaction_type' => 'CUSTOMER_WITHDRAWAL',
                'amount' => 1000,
                'currency' => 'NGN',
                'performed_by' => $user->id,
                'transaction_date' => now()->subDays(5),
                'posted' => true,
                'is_reversed' => true,
                'reversed_at' => now()->subDays(5),
                'reversed_by' => $user->id,
            ]);
        }

        $alerts = app(HighReversalDetectionService::class)->detect();

        $this->assertCount(0, $alerts);
    }

    // ---------- Unusual Approval ----------

    public function test_fast_approved_request_creates_alert(): void
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $request = ApprovalRequest::create([
            'request_no' => 'APR-TEST-1',
            'request_type' => 'ALLOCATE_FLOAT',
            'payload' => ['amount' => 5000],
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'APPROVED',
            'maker_id' => $maker->id,
            'checker_id' => $checker->id,
        ]);

        ApprovalRequest::where('id', $request->id)->update([
            'created_at' => now()->subSeconds(3),
            'approved_at' => now(),
        ]);

        $alerts = app(UnusualApprovalDetectionService::class)->detect();

        $this->assertCount(1, $alerts);
        $this->assertEquals('UNUSUAL_APPROVAL', $alerts[0]->alert_type);
        $this->assertEquals('HIGH', $alerts[0]->severity);
    }

    public function test_normally_paced_approval_creates_no_alert(): void
    {
        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $request = ApprovalRequest::create([
            'request_no' => 'APR-TEST-2',
            'request_type' => 'ALLOCATE_FLOAT',
            'payload' => ['amount' => 5000],
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'APPROVED',
            'maker_id' => $maker->id,
            'checker_id' => $checker->id,
        ]);

        ApprovalRequest::where('id', $request->id)->update([
            'created_at' => now()->subMinutes(10),
            'approved_at' => now(),
        ]);

        $alerts = app(UnusualApprovalDetectionService::class)->detect();

        $this->assertCount(0, $alerts);
    }
}
