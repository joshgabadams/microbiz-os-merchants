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

        Route::post('/float/agent/allocate/request', [ApprovalController::class, 'requestAgentAllocateFloat'])
            ->middleware('permission:approvals.create');
        Route::post('/float/agent/return/request', [ApprovalController::class, 'requestAgentReturnFloat'])
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

        Route::get('/merchants/{merchant}/locations', [MerchantController::class, 'listLocations']);
        Route::post('/merchants/{merchant}/locations', [MerchantController::class, 'addLocation'])
            ->middleware('permission:merchants.locations.manage');

        Route::get('/merchants/{merchant}/terminals', [MerchantController::class, 'listTerminals']);

        Route::post('/merchant-terminals', [MerchantController::class, 'assignTerminal'])
            ->middleware('permission:merchant-terminals.assign');

        Route::post('/merchant-terminals/{terminal}/activate', [MerchantController::class, 'activateTerminal'])
            ->middleware('permission:merchant-terminals.activate');

        Route::post('/merchant-terminals/{terminal}/suspend', [MerchantController::class, 'suspendTerminal'])
            ->middleware('permission:merchant-terminals.suspend');

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

        // ---------- AG-03: Locations ----------

Route::get(
    '/agents/{agent}/locations',
    [AgentController::class, 'listLocations']
);

Route::post(
    '/agents/{agent}/locations',
    [AgentController::class, 'createLocation']
)->middleware('permission:agents.locations.create');

Route::post(
    '/agents/{agent}/locations/{location}/verify',
    [AgentController::class, 'verifyLocation']
)->middleware('permission:agents.locations.verify');

Route::post(
    '/agents/{agent}/locations/{location}/reject',
    [AgentController::class, 'rejectLocation']
)->middleware('permission:agents.locations.verify');

Route::post(
    '/agents/{agent}/complete-compliance-review',
    [AgentController::class, 'completeComplianceReview']
)->middleware('permission:agents.compliance.review');


// ---------- AG-03: Agreements ----------

Route::get(
    '/agents/{agent}/agreements',
    [AgentController::class, 'listAgreements']
);

Route::post(
    '/agents/{agent}/agreements',
    [AgentController::class, 'createAgreement']
)->middleware('permission:agents.agreements.create');

Route::post(
    '/agents/{agent}/agreements/{agreement}/execute',
    [AgentController::class, 'executeAgreement']
)->middleware('permission:agents.agreements.execute');

// ---------- AG-04: Operators ----------

Route::get(
    '/agents/{agent}/operators',
    [AgentController::class, 'listOperators']
);

Route::post(
    '/agents/{agent}/operators',
    [AgentController::class, 'createOperator']
)->middleware('permission:agents.operators.manage');

Route::post(
    '/agent-operators/{operator}/activate',
    [AgentController::class, 'activateOperator']
)->middleware('permission:agents.operators.manage');

Route::post(
    '/agent-operators/{operator}/suspend',
    [AgentController::class, 'suspendOperator']
)->middleware('permission:agents.operators.manage');

// ---------- AG-04: Terminals & Geo-Fence ----------

Route::get(
    '/agents/{agent}/terminals',
    [AgentController::class, 'listTerminals']
);

Route::post(
    '/agent-terminals',
    [AgentController::class, 'createTerminal']
)->middleware('permission:agents.terminals.assign');

Route::post(
    '/agent-terminals/{terminal}/assign',
    [AgentController::class, 'assignTerminalLocation']
)->middleware('permission:agents.terminals.assign');

Route::post(
    '/agent-terminals/{terminal}/activate',
    [AgentController::class, 'activateTerminal']
)->middleware('permission:agents.terminals.activate');

Route::post(
    '/agent-terminals/{terminal}/suspend',
    [AgentController::class, 'suspendTerminal']
)->middleware('permission:agents.terminals.suspend');

Route::post(
    '/agent-terminals/{terminal}/heartbeat',
    [AgentController::class, 'terminalHeartbeat']
);

Route::post(
    '/agent-terminals/{terminal}/location-check',
    [AgentController::class, 'terminalLocationCheck']
);

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
