#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > routes/api.php << 'MBOS_EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfficeSyncController;
use App\Http\Controllers\Api\GlAccountSyncController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\TellerController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\CustomerCashController;
use App\Http\Controllers\Api\BalancingController;
use App\Http\Controllers\Api\BranchEodController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\FixedDepositController;
use App\Http\Controllers\Api\TessaAlertController;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sync/offices', [OfficeSyncController::class, 'sync'])
        ->middleware('permission:offices.sync');
    Route::get('/sync/glaccounts', [GlAccountSyncController::class, 'sync'])
        ->middleware('permission:gl.sync');

    Route::apiResource('vaults', VaultController::class)->only(['index', 'show']);
    Route::apiResource('vaults', VaultController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:vaults.manage');

    Route::apiResource('tellers', TellerController::class)->only(['index', 'show']);
    Route::apiResource('tellers', TellerController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('permission:tellers.manage');

    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/mfa/setup', [MfaController::class, 'setup']);
        Route::post('/mfa/enable', [MfaController::class, 'enable']);
        Route::post('/mfa/disable', [MfaController::class, 'disable']);

        Route::post('/teller/open', [TellerController::class, 'open'])
            ->middleware('permission:tellers.manage');
        Route::post('/teller/close', [TellerController::class, 'close'])
            ->middleware('permission:tellers.manage');

        Route::post('/float/allocate/request', [ApprovalController::class, 'requestAllocateFloat'])
            ->middleware('permission:approvals.create');
        Route::post('/float/return/request', [ApprovalController::class, 'requestReturnFloat'])
            ->middleware('permission:approvals.create');

        Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
        Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])
            ->middleware('permission:approvals.approve');
        Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])
            ->middleware('permission:approvals.reject');

        Route::post('/customer/deposit', [CustomerCashController::class, 'deposit'])
            ->middleware('permission:customer_cash.deposit');
        Route::post('/customer/withdraw', [CustomerCashController::class, 'withdraw'])
            ->middleware('permission:customer_cash.withdraw');

        Route::post('/teller/balance', [BalancingController::class, 'tellerBalance'])
            ->middleware('permission:tellers.manage');
        Route::post('/vault/balance', [BalancingController::class, 'vaultBalance'])
            ->middleware('permission:vaults.manage');

        Route::post('/branch/eod', [BranchEodController::class, 'close'])
            ->middleware('permission:branch_eod.close');

        Route::get('/merchants', [MerchantController::class, 'index']);
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show']);

        Route::patch('/merchants/{merchant}', [MerchantController::class, 'update'])
            ->middleware('permission:merchants.edit');

        Route::get('/merchants/{merchant}/owners', [MerchantController::class, 'listOwners']);
        Route::post('/merchants/{merchant}/owners', [MerchantController::class, 'addOwner'])
            ->middleware('permission:merchants.owners.manage');

        Route::get('/merchants/{merchant}/documents', [MerchantController::class, 'listDocuments']);
        Route::post('/merchants/{merchant}/documents', [MerchantController::class, 'addDocument'])
            ->middleware('permission:merchants.documents.manage');

        Route::post('/merchants/onboard', [MerchantController::class, 'onboard'])
            ->middleware('permission:merchants.onboard');

        Route::post('/merchants/{merchant}/submit', [MerchantController::class, 'submit'])
            ->middleware('permission:merchants.submit');

        Route::post('/merchants/{merchant}/approve', [MerchantController::class, 'approve'])
            ->middleware('permission:merchants.approve');

        Route::post('/merchants/{merchant}/reject', [MerchantController::class, 'reject'])
            ->middleware('permission:merchants.reject');

        Route::post('/merchants/{merchant}/activate', [MerchantController::class, 'activate'])
            ->middleware('permission:merchants.activate');

        Route::post('/merchants/{merchant}/suspend', [MerchantController::class, 'suspend'])
            ->middleware('permission:merchants.suspend');

        Route::post('/merchants/{merchant}/reactivate', [MerchantController::class, 'reactivate'])
            ->middleware('permission:merchants.reactivate');

        Route::post('/merchants/{merchant}/deactivate', [MerchantController::class, 'deactivate'])
            ->middleware('permission:merchants.deactivate');

        Route::post('/merchants/collect/qr', [MerchantController::class, 'collectQr'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/collect/pos', [MerchantController::class, 'collectPos'])
            ->middleware('permission:payments.process');

        Route::post('/merchants/settle', [MerchantController::class, 'settle'])
            ->middleware('permission:merchants.settle');

        // Agent registry (Sprint AG-01 per the M-PAY Agency Banking Blueprint).
        // Locations/Agreements/Operators/Terminals are separate later sprints
        // (AG-03/AG-04) -- deliberately not built yet.
        Route::get('/agents', [AgentController::class, 'index']);
        Route::get('/agents/{agent}', [AgentController::class, 'show']);

        Route::post('/agents', [AgentController::class, 'store'])
            ->middleware('permission:agents.create');

        Route::patch('/agents/{agent}', [AgentController::class, 'update'])
            ->middleware('permission:agents.edit');

        Route::post('/agents/{agent}/submit', [AgentController::class, 'submit'])
            ->middleware('permission:agents.submit');

        Route::post('/agents/{agent}/approve', [AgentController::class, 'approve'])
            ->middleware('permission:agents.approve');

        Route::post('/agents/{agent}/reject', [AgentController::class, 'reject'])
            ->middleware('permission:agents.reject');

        Route::post('/agents/{agent}/activate', [AgentController::class, 'activate'])
            ->middleware('permission:agents.activate');

        Route::post('/agents/{agent}/restrict', [AgentController::class, 'restrict'])
            ->middleware('permission:agents.restrict');

        Route::post('/agents/{agent}/suspend', [AgentController::class, 'suspend'])
            ->middleware('permission:agents.suspend');

        Route::post('/agents/{agent}/reactivate', [AgentController::class, 'reactivate'])
            ->middleware('permission:agents.reactivate');

        Route::post('/agents/{agent}/terminate', [AgentController::class, 'terminate'])
            ->middleware('permission:agents.terminate');

        Route::get('/wallets', [WalletController::class, 'index']);
        Route::get('/wallets/{wallet}', [WalletController::class, 'show']);

        Route::post('/wallets/onboard', [WalletController::class, 'onboard'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/topup', [WalletController::class, 'topUp'])
            ->middleware('permission:wallets.manage');

        Route::post('/wallets/transfer', [WalletController::class, 'transfer'])
            ->middleware('permission:wallets.manage');

        Route::get('/reports/teller-transactions', [ReportController::class, 'tellerTransactions']);
        Route::get('/reports/vault-transactions', [ReportController::class, 'vaultTransactions']);
        Route::get('/reports/teller-ledger', [ReportController::class, 'tellerLedger']);
        Route::get('/reports/vault-ledger', [ReportController::class, 'vaultLedger']);

        Route::get('/fixed-deposits', [FixedDepositController::class, 'index']);
        Route::get('/fixed-deposits/{fixedDeposit}', [FixedDepositController::class, 'show']);
        Route::get('/fixed-deposits/{fixedDeposit}/preview-liquidation', [FixedDepositController::class, 'previewLiquidation']);

        Route::post('/fixed-deposits/book', [FixedDepositController::class, 'book'])
            ->middleware('permission:fixed_deposits.book');

        Route::post('/fixed-deposits/{fixedDeposit}/liquidate', [FixedDepositController::class, 'liquidate'])
            ->middleware('permission:fixed_deposits.liquidate');

        // TESSA rule-based alerts (first pass: teller variance, high
        // reversal, unusual approval -- see config/tessa.php).
        Route::get('/tessa/alerts', [TessaAlertController::class, 'index'])
            ->middleware('permission:tessa.view');
        Route::get('/tessa/alerts/{tessaAlert}', [TessaAlertController::class, 'show'])
            ->middleware('permission:tessa.view');
        Route::post('/tessa/alerts/{tessaAlert}/acknowledge', [TessaAlertController::class, 'acknowledge'])
            ->middleware('permission:tessa.manage');
        Route::post('/tessa/alerts/{tessaAlert}/resolve', [TessaAlertController::class, 'resolve'])
            ->middleware('permission:tessa.manage');
        Route::post('/tessa/detect', [TessaAlertController::class, 'detect'])
            ->middleware('permission:tessa.manage');
    });
});
MBOS_EOF

cat > database/seeders/RbacSeeder.php << 'MBOS_EOF'
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vaults.manage' => 'vault',
            'tellers.manage' => 'teller',
            'approvals.create' => 'approval',
            'approvals.approve' => 'approval',
            'approvals.reject' => 'approval',
            'customer_cash.deposit' => 'customer-cash',
            'customer_cash.withdraw' => 'customer-cash',
            'branch_eod.close' => 'branch',
            'gl.sync' => 'gl',
            'offices.sync' => 'sync',
            'fixed_deposits.book' => 'fixed-deposit',
            'fixed_deposits.liquidate' => 'fixed-deposit',
            'fixed_deposits.backdate' => 'fixed-deposit',
            'tessa.view' => 'tessa',
            'tessa.manage' => 'tessa',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(['name' => $name], ['module' => $module]);
        }

        $roles = [
            'admin' => [
                'label' => 'Platform Administrator',
                'is_system' => true,
                'permissions' => array_keys($permissions),
            ],
            'teller-officer' => [
                'label' => 'Teller Officer',
                'permissions' => ['tellers.manage', 'customer_cash.deposit', 'customer_cash.withdraw'],
            ],
            'deposit-officer' => [
                'label' => 'Deposit Officer',
                'permissions' => ['fixed_deposits.book', 'fixed_deposits.liquidate'],
            ],
            'deposit-supervisor' => [
                'label' => 'Deposit Supervisor',
                'permissions' => ['fixed_deposits.book', 'fixed_deposits.liquidate', 'fixed_deposits.backdate'],
            ],
            'vault-officer' => [
                'label' => 'Vault Officer',
                'permissions' => ['vaults.manage', 'approvals.create'],
            ],
            'branch-manager' => [
                'label' => 'Branch Manager',
                'permissions' => ['approvals.approve', 'approvals.reject', 'branch_eod.close', 'tessa.view', 'tessa.manage'],
            ],
            'compliance-officer' => [
                'label' => 'Compliance Officer',
                'permissions' => ['gl.sync', 'offices.sync', 'tessa.view', 'tessa.manage'],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label'], 'is_system' => $config['is_system'] ?? false],
            );

            $permissionIds = Permission::whereIn('name', $config['permissions'])->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $firstUser = User::first();

        if ($firstUser) {
            $adminRole = Role::where('name', 'admin')->first();
            $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);

            $this->command->info("Attached 'admin' role to existing user: {$firstUser->email}");
        } else {
            $this->command->warn('No existing user found -- run this after your user seeder, or create a user and re-run.');
        }
    }
}
MBOS_EOF

cat > tests/Feature/TessaAlertDetectionTest.php << 'MBOS_EOF'
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
MBOS_EOF

echo "TESSA wiring + tests applied. Next: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\RbacSeeder\" && php artisan test --filter=TessaAlertDetectionTest"