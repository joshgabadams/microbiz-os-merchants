#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

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

    /**
     * Aggregate counts for the management dashboard -- computed
     * server-side rather than having the frontend fetch every alert and
     * aggregate client-side, since this is meant to scale as alert
     * volume grows.
     */
    public function summary()
    {
        $summary = [
            'total_open' => TessaAlert::where('status', 'OPEN')->count(),
            'total_acknowledged' => TessaAlert::where('status', 'ACKNOWLEDGED')->count(),
            'resolved_last_7_days' => TessaAlert::where('status', 'RESOLVED')
                ->where('resolved_at', '>=', now()->subDays(7))
                ->count(),
            'open_by_severity' => TessaAlert::where('status', 'OPEN')
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity'),
            'open_by_type' => TessaAlert::where('status', 'OPEN')
                ->selectRaw('alert_type, COUNT(*) as count')
                ->groupBy('alert_type')
                ->pluck('count', 'alert_type'),
        ];

        return $this->success($summary, 'TESSA summary retrieved successfully.');
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

cat > frontend/src/app/core/tessa-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface TessaAlert {
  id: number;
  alert_type: 'TELLER_VARIANCE' | 'HIGH_REVERSAL' | 'UNUSUAL_APPROVAL';
  severity: 'LOW' | 'MEDIUM' | 'HIGH';
  subject_type: string;
  subject_id: number;
  title: string;
  description: string;
  metadata: Record<string, unknown>;
  status: 'OPEN' | 'ACKNOWLEDGED' | 'RESOLVED';
  acknowledged_by: number | null;
  acknowledged_at: string | null;
  resolved_by: number | null;
  resolved_at: string | null;
  resolution_note: string | null;
  detected_at: string;
}

export interface TessaSummary {
  total_open: number;
  total_acknowledged: number;
  resolved_last_7_days: number;
  open_by_severity: Record<string, number>;
  open_by_type: Record<string, number>;
}

@Injectable({ providedIn: 'root' })
export class TessaApiService {
  private readonly base = `${environment.apiUrl}/v1/tessa`;

  constructor(private http: HttpClient) {}

  summary(): Observable<ApiResponse<TessaSummary>> {
    return this.http.get<ApiResponse<TessaSummary>>(`${this.base}/summary`);
  }

  list(params?: { status?: string; alert_type?: string; severity?: string }): Observable<ApiResponse<TessaAlert[]>> {
    return this.http.get<ApiResponse<TessaAlert[]>>(`${this.base}/alerts`, { params });
  }

  acknowledge(id: number): Observable<ApiResponse<TessaAlert>> {
    return this.http.post<ApiResponse<TessaAlert>>(`${this.base}/alerts/${id}/acknowledge`, {});
  }

  resolve(id: number, resolutionNote: string): Observable<ApiResponse<TessaAlert>> {
    return this.http.post<ApiResponse<TessaAlert>>(`${this.base}/alerts/${id}/resolve`, { resolution_note: resolutionNote });
  }

  detect(): Observable<ApiResponse<{ teller_variance: number; high_reversal: number; unusual_approval: number }>> {
    return this.http.post<ApiResponse<{ teller_variance: number; high_reversal: number; unusual_approval: number }>>(`${this.base}/detect`, {});
  }
}
MBOS_EOF

mkdir -p frontend/src/app/features/tessa
cat > frontend/src/app/features/tessa/tessa-dashboard.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TessaApiService, TessaAlert, TessaSummary } from '../../core/tessa-api.service';

@Component({
  selector: 'app-tessa-dashboard',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="header-row">
      <h1>TESSA -- Risk & Monitoring Dashboard</h1>
      <button class="detect-btn" (click)="runDetection()" [disabled]="detecting()">
        {{ detecting() ? 'Running...' : 'Run Detection Now' }}
      </button>
    </div>

    @if (detectMessage()) {
      <p class="success">{{ detectMessage() }}</p>
    }

    @if (summary(); as s) {
      <div class="summary-cards">
        <div class="card total">
          <span class="card-value">{{ s.total_open }}</span>
          <span class="card-label">Open Alerts</span>
        </div>
        <div class="card high">
          <span class="card-value">{{ s.open_by_severity['HIGH'] ?? 0 }}</span>
          <span class="card-label">High Severity</span>
        </div>
        <div class="card medium">
          <span class="card-value">{{ s.open_by_severity['MEDIUM'] ?? 0 }}</span>
          <span class="card-label">Medium Severity</span>
        </div>
        <div class="card low">
          <span class="card-value">{{ s.open_by_severity['LOW'] ?? 0 }}</span>
          <span class="card-label">Low Severity</span>
        </div>
        <div class="card neutral">
          <span class="card-value">{{ s.total_acknowledged }}</span>
          <span class="card-label">Acknowledged</span>
        </div>
        <div class="card neutral">
          <span class="card-value">{{ s.resolved_last_7_days }}</span>
          <span class="card-label">Resolved (7d)</span>
        </div>
      </div>

      <div class="type-breakdown">
        <span class="type-chip">Teller Variance: {{ s.open_by_type['TELLER_VARIANCE'] ?? 0 }}</span>
        <span class="type-chip">High Reversal: {{ s.open_by_type['HIGH_REVERSAL'] ?? 0 }}</span>
        <span class="type-chip">Unusual Approval: {{ s.open_by_type['UNUSUAL_APPROVAL'] ?? 0 }}</span>
      </div>
    }

    <div class="filters">
      <label>Status
        <select [(ngModel)]="statusFilter" (ngModelChange)="load()">
          <option value="OPEN">Open</option>
          <option value="ACKNOWLEDGED">Acknowledged</option>
          <option value="RESOLVED">Resolved</option>
          <option value="">All</option>
        </select>
      </label>
      <label>Severity
        <select [(ngModel)]="severityFilter" (ngModelChange)="load()">
          <option value="">All</option>
          <option value="HIGH">High</option>
          <option value="MEDIUM">Medium</option>
          <option value="LOW">Low</option>
        </select>
      </label>
      <label>Type
        <select [(ngModel)]="typeFilter" (ngModelChange)="load()">
          <option value="">All</option>
          <option value="TELLER_VARIANCE">Teller Variance</option>
          <option value="HIGH_REVERSAL">High Reversal</option>
          <option value="UNUSUAL_APPROVAL">Unusual Approval</option>
        </select>
      </label>
    </div>

    @if (loading()) {
      <p>Loading...</p>
    } @else if (alerts().length === 0) {
      <p>No alerts match this filter.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Severity</th>
            <th>Type</th>
            <th>Title</th>
            <th>Detected</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (alert of alerts(); track alert.id) {
            <tr>
              <td><span class="badge" [class]="alert.severity.toLowerCase()">{{ alert.severity }}</span></td>
              <td>{{ alert.alert_type }}</td>
              <td class="title-cell">
                <strong>{{ alert.title }}</strong>
                <p class="desc">{{ alert.description }}</p>
              </td>
              <td>{{ alert.detected_at | slice:0:16 }}</td>
              <td>{{ alert.status }}</td>
              <td class="actions">
                @if (alert.status === 'OPEN') {
                  <button (click)="acknowledge(alert)" [disabled]="busyId() === alert.id">Acknowledge</button>
                }
                @if (alert.status !== 'RESOLVED') {
                  <button (click)="startResolve(alert)" [disabled]="busyId() === alert.id">Resolve</button>
                }
              </td>
            </tr>
            @if (resolvingId() === alert.id) {
              <tr class="resolve-row">
                <td colspan="6">
                  <input type="text" placeholder="Resolution note..." [(ngModel)]="resolutionNote" name="note-{{ alert.id }}" />
                  <button (click)="confirmResolve(alert)" [disabled]="busyId() === alert.id" class="confirm">Confirm</button>
                  <button (click)="cancelResolve()" class="cancel">Cancel</button>
                </td>
              </tr>
            }
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    h1 { margin: 0; font-size: 1.3rem; }
    .detect-btn {
      padding: 0.5rem 1rem;
      background: #1e2761;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 0.85rem;
    }
    .success { color: #1a7f37; }
    .summary-cards {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 0.75rem;
      margin-bottom: 1rem;
    }
    .card {
      background: white;
      border-radius: 8px;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      border-top: 4px solid #d5d9e6;
    }
    .card.high { border-top-color: #b3261e; }
    .card.medium { border-top-color: #b8860b; }
    .card.low { border-top-color: #1a7f37; }
    .card.total { border-top-color: #1e2761; }
    .card-value { font-size: 1.6rem; font-weight: 700; }
    .card-label { font-size: 0.75rem; color: #555; margin-top: 0.25rem; }
    .type-breakdown { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; }
    .type-chip {
      background: #eef1f8;
      padding: 0.3rem 0.7rem;
      border-radius: 20px;
      font-size: 0.8rem;
    }
    .filters { display: flex; gap: 1rem; margin-bottom: 1rem; }
    .filters label { display: flex; flex-direction: column; font-size: 0.8rem; gap: 0.25rem; }
    .filters select { padding: 0.3rem; border-radius: 6px; border: 1px solid #d5d9e6; }
    table { font-size: 0.85rem; width: 100%; }
    .badge {
      padding: 0.2rem 0.6rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      color: white;
    }
    .badge.high { background: #b3261e; }
    .badge.medium { background: #b8860b; }
    .badge.low { background: #1a7f37; }
    .title-cell { max-width: 400px; }
    .desc { font-size: 0.78rem; color: #555; margin: 0.2rem 0 0; }
    .actions button {
      padding: 0.3rem 0.6rem;
      margin-right: 0.3rem;
      border: 1px solid #1e2761;
      background: white;
      color: #1e2761;
      border-radius: 6px;
      font-size: 0.78rem;
    }
    .resolve-row td { background: #f4f6fb; padding: 0.6rem; }
    .resolve-row input { padding: 0.4rem; width: 300px; margin-right: 0.5rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    .confirm { background: #1a7f37; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; margin-right: 0.4rem; }
    .cancel { background: #d5d9e6; color: #1b2140; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; }
  `],
})
export class TessaDashboardComponent implements OnInit {
  summary = signal<TessaSummary | null>(null);
  alerts = signal<TessaAlert[]>([]);
  loading = signal(true);

  statusFilter = 'OPEN';
  severityFilter = '';
  typeFilter = '';

  detecting = signal(false);
  detectMessage = signal<string | null>(null);

  busyId = signal<number | null>(null);
  resolvingId = signal<number | null>(null);
  resolutionNote = '';

  constructor(private api: TessaApiService) {}

  ngOnInit(): void {
    this.loadSummary();
    this.load();
  }

  loadSummary(): void {
    this.api.summary().subscribe({
      next: (res) => this.summary.set(res.data),
      error: () => {},
    });
  }

  load(): void {
    this.loading.set(true);
    this.api
      .list({
        status: this.statusFilter || undefined,
        severity: this.severityFilter || undefined,
        alert_type: this.typeFilter || undefined,
      })
      .subscribe({
        next: (res) => {
          this.alerts.set(res.data);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  runDetection(): void {
    this.detecting.set(true);
    this.detectMessage.set(null);

    this.api.detect().subscribe({
      next: (res) => {
        this.detecting.set(false);
        const d = res.data;
        this.detectMessage.set(
          `Detection complete: ${d.teller_variance} variance, ${d.high_reversal} reversal, ${d.unusual_approval} approval alert(s) open/updated.`
        );
        this.loadSummary();
        this.load();
      },
      error: () => this.detecting.set(false),
    });
  }

  acknowledge(alert: TessaAlert): void {
    this.busyId.set(alert.id);
    this.api.acknowledge(alert.id).subscribe({
      next: () => {
        this.busyId.set(null);
        this.loadSummary();
        this.load();
      },
      error: () => this.busyId.set(null),
    });
  }

  startResolve(alert: TessaAlert): void {
    this.resolvingId.set(alert.id);
    this.resolutionNote = '';
  }

  cancelResolve(): void {
    this.resolvingId.set(null);
    this.resolutionNote = '';
  }

  confirmResolve(alert: TessaAlert): void {
    if (!this.resolutionNote.trim()) {
      return;
    }

    this.busyId.set(alert.id);
    this.api.resolve(alert.id, this.resolutionNote).subscribe({
      next: () => {
        this.busyId.set(null);
        this.resolvingId.set(null);
        this.loadSummary();
        this.load();
      },
      error: () => this.busyId.set(null),
    });
  }
}
MBOS_EOF

echo "TESSA dashboard backend+frontend applied. Next: paste current frontend/src/app/app.routes.ts and frontend/src/app/features/dashboard/dashboard.component.ts to wire the route/sidebar link."