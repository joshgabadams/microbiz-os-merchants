#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

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
            'vault-officer' => [
                'label' => 'Vault Officer',
                'permissions' => ['vaults.manage', 'approvals.create'],
            ],
            'branch-manager' => [
                'label' => 'Branch Manager',
                'permissions' => ['approvals.approve', 'approvals.reject', 'branch_eod.close'],
            ],
            'compliance-officer' => [
                'label' => 'Compliance Officer',
                'permissions' => ['gl.sync', 'offices.sync'],
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
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\FixedDepositController;
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
    });
});
MBOS_EOF

cat > tests/Feature/FixedDepositTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\User;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeFundedAccount(float $balance): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_booking_debits_source_account_and_creates_active_fd(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 500, 365, $user->id
        );

        $this->assertEquals('ACTIVE', $fd->status);
        $this->assertEquals(100000, (float) $fd->principal_amount);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(100000, (float) $sourceBalance->available_balance);
    }

    public function test_booking_rejects_insufficient_source_balance(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(50000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 500, 365, $user->id
        );
    }

    public function test_liquidation_at_full_maturity_pays_correct_simple_interest(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-MATURE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals('LIQUIDATED_AT_MATURITY', $result['fixed_deposit']->status);
        $this->assertEquals(10000, (float) $result['calculation']['net_interest']);
        $this->assertEquals(110000, (float) $result['calculation']['total_payout']);
        $this->assertEquals(0, (float) $result['calculation']['fee_applied']);
    }

    public function test_early_liquidation_applies_reduced_rate_and_penalty_fee(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-EARLY',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 50,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
        $this->assertEquals(164.38, (float) $result['calculation']['gross_interest']);
        $this->assertEquals(50, (float) $result['calculation']['fee_applied']);
        $this->assertEquals(114.38, (float) $result['calculation']['net_interest']);
    }

    public function test_early_liquidation_fee_never_makes_interest_negative(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-FEE-EXCEEDS',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $user->id);

        $this->assertEquals(0, (float) $result['calculation']['net_interest']);
        $this->assertEquals(100000, (float) $result['calculation']['total_payout']);
    }

    public function test_already_liquidated_fd_cannot_be_liquidated_again(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-DOUBLE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 50000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 90,
            'start_date' => now()->subDays(90),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $service = app(FixedDepositService::class);
        $service->liquidate($fd, $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already');

        $service->liquidate($fd->fresh(), $user->id);
    }

    public function test_settlement_account_receives_principal_plus_interest(): void
    {
        $user = User::factory()->create();
        $source = $this->makeFundedAccount(200000);
        $settlement = $this->makeFundedAccount(0);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-SETTLEMENT',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $settlement->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        app(FixedDepositService::class)->liquidate($fd, $user->id);

        $settlementBalance = CustomerAccountBalance::where('customer_account_id', $settlement->id)->first();

        $this->assertEquals(110000, (float) $settlementBalance->available_balance);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(200000, (float) $sourceBalance->available_balance);
    }
}
MBOS_EOF

echo "FD Backfill Part B applied (RbacSeeder, routes/api.php, FixedDepositTest). Now run: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\RbacSeeder\" && php artisan db:seed --class=\"Database\Seeders\GlAccountSeeder\""