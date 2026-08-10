#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Http/Controllers/Api/AgentController.php << 'MBOS_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AddAgentDocumentRequest;
use App\Http\Requests\Agent\AddAgentOwnerRequest;
use App\Http\Requests\Agent\AgentReasonRequest;
use App\Http\Requests\Agent\OnboardAgentRequest;
use App\Models\Agent;
use App\Services\Payments\AgentActivationService;
use App\Services\Payments\AgentApprovalService;
use App\Services\Payments\AgentKycService;
use App\Services\Payments\AgentRegistrationService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AgentRegistrationService $registrationService,
        protected AgentApprovalService $approvalService,
        protected AgentActivationService $activationService,
        protected AgentKycService $kycService
    ) {
    }

    public function index()
    {
        return $this->success(
            Agent::with(['branch', 'supervisor'])->get(),
            'Agents retrieved successfully.'
        );
    }

    public function show(Agent $agent)
    {
        return $this->success(
            $agent->load(['branch', 'supervisor']),
            'Agent retrieved successfully.'
        );
    }

    public function store(OnboardAgentRequest $request)
    {
        try {
            $agent = $this->registrationService->register(
                $request->validated(),
                $request->user()->id
            );

            return $this->success($agent, 'Agent registered successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, Agent $agent)
    {
        $agent->update($request->only([
            'trading_name', 'registration_number', 'tax_identification_number',
            'phone', 'email', 'supervisor_id', 'principal_reference',
            'daily_transaction_limit', 'daily_cash_out_limit', 'single_transaction_limit',
            'next_review_date',
        ]));

        return $this->success($agent->fresh(), 'Agent updated successfully.');
    }

    public function submit(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->submit($agent, $request->user()->id);

            return $this->success($result, 'Agent submitted for approval.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approve(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->approve($agent, $request->user()->id);

            return $this->success($result, 'Agent approved.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reject(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->approvalService->reject($agent, $request->user()->id, $request->reason);

            return $this->success($result, 'Agent rejected.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activate(Agent $agent)
    {
        try {
            $result = $this->activationService->activate($agent);

            return $this->success($result, 'Agent activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function restrict(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->restrict($agent, $request->reason);

            return $this->success($result, 'Agent restricted.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspend(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->suspend($agent, $request->reason);

            return $this->success($result, 'Agent suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reactivate(Agent $agent)
    {
        try {
            $result = $this->activationService->reactivate($agent);

            return $this->success($result, 'Agent reactivated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function terminate(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->terminate($agent, $request->reason);

            return $this->success($result, 'Agent terminated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-02: KYC & Approval ----------

    public function listOwners(Agent $agent)
    {
        return $this->success($agent->owners, 'Agent owners retrieved successfully.');
    }

    public function addOwner(Agent $agent, AddAgentOwnerRequest $request)
    {
        $owner = $agent->owners()->create($request->validated());

        return $this->success($owner, 'Agent owner added successfully.', 201);
    }

    public function listDocuments(Agent $agent)
    {
        return $this->success($agent->documents, 'Agent documents retrieved successfully.');
    }

    public function addDocument(Agent $agent, AddAgentDocumentRequest $request)
    {
        $document = $agent->documents()->create($request->validated());

        return $this->success($document, 'Agent document added successfully.', 201);
    }

    public function completeKyc(Agent $agent, Request $request)
    {
        try {
            $result = $this->kycService->completeKyc($agent, $request->user()->id);

            return $this->success($result, 'Agent KYC completed; moved to location verification.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
MBOS_EOF

cat > database/seeders/PaymentsRbacSeeder.php << 'MBOS_EOF'
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PaymentsRbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'wallets.manage' => 'payments',
            'merchants.onboard' => 'payments',
            'merchants.edit' => 'payments',
            'merchants.submit' => 'payments',
            'merchants.approve' => 'payments',
            'merchants.reject' => 'payments',
            'merchants.activate' => 'payments',
            'merchants.suspend' => 'payments',
            'merchants.reactivate' => 'payments',
            'merchants.deactivate' => 'payments',
            'merchants.settle' => 'payments',
            'merchants.owners.manage' => 'payments',
            'merchants.documents.manage' => 'payments',
            'payments.process' => 'payments',
            'agency_banking.manage' => 'payments',
            'agents.view' => 'payments',
            'agents.create' => 'payments',
            'agents.edit' => 'payments',
            'agents.submit' => 'payments',
            'agents.approve' => 'payments',
            'agents.reject' => 'payments',
            'agents.activate' => 'payments',
            'agents.restrict' => 'payments',
            'agents.suspend' => 'payments',
            'agents.reactivate' => 'payments',
            'agents.terminate' => 'payments',
            'agents.owners.manage' => 'payments',
            'agents.documents.manage' => 'payments',
            'agents.kyc.review' => 'payments',
        ];

        foreach ($permissions as $name => $module) {
            Permission::firstOrCreate(['name' => $name], ['module' => $module]);
        }

        $roles = [
            'payments-officer' => [
                'label' => 'Payments Officer',
                'permissions' => ['wallets.manage', 'payments.process'],
            ],
            'merchant-support' => [
                'label' => 'Merchant Support',
                'permissions' => [
                    'merchants.onboard', 'merchants.edit', 'merchants.submit',
                    'merchants.owners.manage', 'merchants.documents.manage', 'payments.process',
                ],
            ],
            'merchant-approval-officer' => [
                'label' => 'Merchant Approval Officer',
                'permissions' => [
                    'merchants.approve', 'merchants.reject', 'merchants.activate',
                    'merchants.suspend', 'merchants.reactivate', 'merchants.deactivate',
                ],
            ],
            'merchant-settlement-officer' => [
                'label' => 'Merchant Settlement Officer',
                'permissions' => ['merchants.settle'],
            ],
            'agency-banking-agent' => [
                'label' => 'Agency Banking Agent',
                'permissions' => ['agency_banking.manage', 'payments.process'],
            ],
            'agent-registration-officer' => [
                'label' => 'Agent Registration Officer',
                'permissions' => [
                    'agents.view', 'agents.create', 'agents.edit', 'agents.submit',
                    'agents.owners.manage', 'agents.documents.manage',
                ],
            ],
            'agent-kyc-officer' => [
                'label' => 'Agent KYC Officer',
                'permissions' => ['agents.view', 'agents.kyc.review'],
            ],
            'agent-approval-officer' => [
                'label' => 'Agent Approval Officer',
                'permissions' => [
                    'agents.view', 'agents.approve', 'agents.reject', 'agents.activate',
                    'agents.restrict', 'agents.suspend', 'agents.reactivate', 'agents.terminate',
                ],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $config['label']],
            );

            $permissionIds = Permission::whereIn('name', $config['permissions'])->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->command->info('Payments domain permissions and roles seeded.');
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

        Route::get('/agents/{agent}/owners', [AgentController::class, 'listOwners']);
        Route::post('/agents/{agent}/owners', [AgentController::class, 'addOwner'])
            ->middleware('permission:agents.owners.manage');

        Route::get('/agents/{agent}/documents', [AgentController::class, 'listDocuments']);
        Route::post('/agents/{agent}/documents', [AgentController::class, 'addDocument'])
            ->middleware('permission:agents.documents.manage');

        Route::post('/agents/{agent}/complete-kyc', [AgentController::class, 'completeKyc'])
            ->middleware('permission:agents.kyc.review');

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

cat > tests/Feature/AgentKycTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\AgentApprovalService;
use App\Services\Payments\AgentKycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentKycTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDraftAgent(int $createdBy): Agent
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::DRAFT->value,
            'created_by' => $createdBy,
        ]);
    }

    public function test_submit_moves_draft_agent_to_pending_kyc_not_pending_approval(): void
    {
        $user = User::factory()->create();
        $agent = $this->makeDraftAgent($user->id);

        $result = app(AgentApprovalService::class)->submit($agent, $user->id);

        $this->assertEquals(AgentStatus::PENDING_KYC->value, $result->status);
    }

    public function test_kyc_cannot_be_completed_without_any_owners_or_documents(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('beneficial owner');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);
    }

    public function test_kyc_cannot_be_completed_with_owner_but_no_document(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create([
            'full_name' => 'Jane Owner',
            'ownership_percentage' => 100,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('document');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);
    }

    public function test_registering_officer_cannot_complete_their_own_agents_kyc(): void
    {
        $registrant = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot complete KYC review for their own agent');

        app(AgentKycService::class)->completeKyc($agent->fresh(), $registrant->id);
    }

    public function test_kyc_completes_successfully_with_owner_and_document_present(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);

        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $result = app(AgentKycService::class)->completeKyc($agent->fresh(), $reviewer->id);

        $this->assertEquals(AgentStatus::PENDING_LOCATION_VERIFICATION->value, $result->status);
        $this->assertEquals('COMPLETED', $result->kyc_status);
    }

    public function test_kyc_cannot_be_completed_twice(): void
    {
        $registrant = User::factory()->create();
        $reviewer = User::factory()->create();
        $agent = $this->makeDraftAgent($registrant->id);

        app(AgentApprovalService::class)->submit($agent, $registrant->id);
        $agent->owners()->create(['full_name' => 'Jane Owner', 'ownership_percentage' => 100]);
        $agent->documents()->create(['document_type' => 'ID_CARD']);

        $kycService = app(AgentKycService::class);
        $kycService->completeKyc($agent->fresh(), $reviewer->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not pending KYC');

        $kycService->completeKyc($agent->fresh(), $reviewer->id);
    }
}
MBOS_EOF

echo "AG-02 Part 2 of 2 applied (controller, RBAC, routes, tests). Next: php artisan migrate && php artisan db:seed --class=\"Database\Seeders\PaymentsRbacSeeder\" && php artisan test --filter=AgentKycTest"