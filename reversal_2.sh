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
use App\Http\Controllers\Api\TransactionReversalController;
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

        Route::post('/customer-transactions/{customerAccountTransaction}/reverse', [TransactionReversalController::class, 'reverse'])
            ->middleware('permission:transactions.reverse');

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

        Route::get('/tessa/summary', [TessaAlertController::class, 'summary'])
            ->middleware('permission:tessa.view');
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
            'transactions.reverse' => 'cash-management',
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
                'permissions' => ['approvals.approve', 'approvals.reject', 'branch_eod.close', 'tessa.view', 'tessa.manage', 'transactions.reverse'],
            ],
            'compliance-officer' => [
                'label' => 'Compliance Officer',
                'permissions' => ['gl.sync', 'offices.sync', 'tessa.view', 'tessa.manage', 'transactions.reverse'],
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

cat > tests/Feature/TransactionReversalTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\TellerTransaction;
use App\Models\User;
use App\Services\CashManagement\TransactionReversalService;
use App\Services\Customer\CustomerCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReversalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTellerWithBalance(float $balance): Teller
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-'.uniqid(),
            'display_name' => 'Test Teller',
            'active' => true,
            'status' => 'OPEN',
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $teller;
    }

    protected function makeCustomerAccount(): CustomerAccount
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
            'ledger_balance' => 0,
            'available_balance' => 0,
        ]);

        return $account;
    }

    public function test_deposit_populates_the_link_between_customer_and_teller_transaction(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->first();

        $this->assertNotNull($tellerTransaction, 'The teller transaction should be linked to the customer transaction via the new FK.');
        $this->assertEquals($result['teller_transaction']->id, $tellerTransaction->id);
    }

    public function test_reversal_finds_the_correct_pair_even_with_two_identical_amount_deposits(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $first = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);
        $second = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTxForFirst = TellerTransaction::where(
            'customer_account_transaction_id',
            $first['customer_transaction']->id
        )->firstOrFail();

        $tellerTxForSecond = TellerTransaction::where(
            'customer_account_transaction_id',
            $second['customer_transaction']->id
        )->firstOrFail();

        $this->assertNotEquals($tellerTxForFirst->id, $tellerTxForSecond->id, 'Each deposit must resolve to its own distinct teller transaction, not either one.');

        app(TransactionReversalService::class)->reverseCustomerDeposit(
            $first['customer_transaction']->fresh(),
            $tellerTxForFirst->fresh(),
            $user->id
        );

        $this->assertTrue($first['customer_transaction']->fresh()->is_reversed, 'The first deposit should be reversed.');
        $this->assertFalse($second['customer_transaction']->fresh()->is_reversed, 'The second deposit must remain untouched.');
    }

    public function test_reversal_endpoint_correctly_reverses_a_deposit_and_posts_real_gl_entries(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->firstOrFail();

        $reversalResult = app(TransactionReversalService::class)->reverseCustomerDeposit(
            $result['customer_transaction']->fresh(),
            $tellerTransaction->fresh(),
            $user->id
        );

        $this->assertTrue($result['customer_transaction']->fresh()->is_reversed);
        $this->assertTrue($tellerTransaction->fresh()->is_reversed);

        $journalEntries = GlJournal::where('reference', $reversalResult['teller_reversal_transaction']->transaction_no)->get();
        $this->assertCount(2, $journalEntries, 'The reversal should create exactly 2 balanced GlJournal rows.');

        $accountBalance = CustomerAccountBalance::where('customer_account_id', $account->id)->first();
        $this->assertEquals(0, (float) $accountBalance->available_balance);
    }

    public function test_reversing_an_already_reversed_transaction_is_rejected(): void
    {
        $teller = $this->makeTellerWithBalance(50000);
        $account = $this->makeCustomerAccount();
        $user = User::factory()->create();

        $result = app(CustomerCashService::class)->deposit($teller, $account, 5000, $user->id);

        $tellerTransaction = TellerTransaction::where(
            'customer_account_transaction_id',
            $result['customer_transaction']->id
        )->firstOrFail();

        $service = app(TransactionReversalService::class);
        $service->reverseCustomerDeposit($result['customer_transaction']->fresh(), $tellerTransaction->fresh(), $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already been reversed');

        $service->reverseCustomerDeposit(
            $result['customer_transaction']->fresh(),
            $tellerTransaction->fresh(),
            $user->id
        );
    }
}
MBOS_EOF

echo "Reversal Part 2 of 2 applied (routes, RBAC, tests). Next: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\RbacSeeder\" && php artisan test --filter=TransactionReversalTest"
