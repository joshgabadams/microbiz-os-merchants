#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend

cat > src/app/core/fixed-deposit-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface FixedDeposit {
  id: number;
  fd_no: string;
  customer_account_id: number;
  settlement_account_id: number;
  principal_amount: string;
  currency: string;
  interest_rate: string;
  pre_liquidation_rate: string;
  pre_liquidation_penalty_fee: string;
  tenor_days: number;
  start_date: string;
  maturity_date: string;
  status: 'ACTIVE' | 'LIQUIDATED_EARLY' | 'LIQUIDATED_AT_MATURITY';
  interest_paid: string | null;
  narration: string | null;
}

export interface LiquidationCalculation {
  is_early: boolean;
  elapsed_days: number;
  rate_applied: number;
  principal: number;
  gross_interest: number;
  fee_applied: number;
  net_interest: number;
  total_payout: number;
}

@Injectable({ providedIn: 'root' })
export class FixedDepositApiService {
  private readonly base = `${environment.apiUrl}/v1/fixed-deposits`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<FixedDeposit[]>> {
    return this.http.get<ApiResponse<FixedDeposit[]>>(this.base);
  }

  book(payload: {
    customer_account_id: number;
    settlement_account_id: number;
    principal_amount: number;
    interest_rate: number;
    pre_liquidation_rate: number;
    pre_liquidation_penalty_fee?: number;
    tenor_days: number;
    branch_id: number;
    narration?: string;
    start_date?: string;
    backdate_reason?: string;
  }): Observable<ApiResponse<FixedDeposit>> {
    return this.http.post<ApiResponse<FixedDeposit>>(`${this.base}/book`, payload);
  }

  previewLiquidation(id: number): Observable<ApiResponse<LiquidationCalculation>> {
    return this.http.get<ApiResponse<LiquidationCalculation>>(`${this.base}/${id}/preview-liquidation`);
  }

  liquidate(id: number, branchId: number, narration?: string): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/${id}/liquidate`, { branch_id: branchId, narration });
  }
}
MBOS_EOF

cat > src/app/features/fixed-deposits/fixed-deposit-list.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { FixedDepositApiService, FixedDeposit, LiquidationCalculation } from '../../core/fixed-deposit-api.service';

@Component({
  selector: 'app-fixed-deposit-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Fixed Deposits</h1>

    <details class="create-form">
      <summary>+ Book Fixed Deposit</summary>
      <form (ngSubmit)="onBook()">
        <label>Source Account ID <input type="number" name="source" [(ngModel)]="form.customer_account_id" required /></label>
        <label>Settlement Account ID <input type="number" name="settlement" [(ngModel)]="form.settlement_account_id" required /></label>
        <label>Branch ID <input type="number" name="branch" [(ngModel)]="form.branch_id" required /></label>
        <label>Principal <input type="number" name="principal" [(ngModel)]="form.principal_amount" required /></label>
        <label>Interest Rate % (full term) <input type="number" step="0.01" name="rate" [(ngModel)]="form.interest_rate" required /></label>
        <label>Pre-Liquidation Rate % <input type="number" step="0.01" name="pre_rate" [(ngModel)]="form.pre_liquidation_rate" required /></label>
        <label>Pre-Liquidation Fee <input type="number" name="fee" [(ngModel)]="form.pre_liquidation_penalty_fee" /></label>
        <label>Tenor (days) <input type="number" name="tenor" [(ngModel)]="form.tenor_days" required /></label>
        <label>Narration <input type="text" name="narration" [(ngModel)]="form.narration" /></label>
        <label class="checkbox-label">
          <input type="checkbox" name="backdate_toggle" [(ngModel)]="backdatingEnabled" />
          Backdate this booking
        </label>
        @if (backdatingEnabled) {
          <label>Start Date <input type="date" name="start_date" [(ngModel)]="form.start_date" [max]="todayIso" required /></label>
          <label>Backdate Reason <input type="text" name="backdate_reason" [(ngModel)]="form.backdate_reason" required /></label>
        }
        @if (bookError()) {
          <p class="error">{{ bookError() }}</p>
        }
        <button type="submit" [disabled]="booking()">
          {{ booking() ? 'Booking...' : 'Book Fixed Deposit' }}
        </button>
      </form>
    </details>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }
    @if (actionMessage()) {
      <p class="success">{{ actionMessage() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (deposits().length === 0) {
      <p>No fixed deposits booked yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>FD No</th>
            <th>Principal</th>
            <th>Rate</th>
            <th>Pre-Liq Rate</th>
            <th>Tenor</th>
            <th>Maturity</th>
            <th>Status</th>
            <th>Interest Paid</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (fd of deposits(); track fd.id) {
            <tr>
              <td>{{ fd.fd_no }}</td>
              <td>{{ fd.principal_amount }}</td>
              <td>{{ fd.interest_rate }}%</td>
              <td>{{ fd.pre_liquidation_rate }}%</td>
              <td>{{ fd.tenor_days }}d</td>
              <td>{{ fd.maturity_date }}</td>
              <td>{{ fd.status }}</td>
              <td>{{ fd.interest_paid ?? '-' }}</td>
              <td class="actions">
                @if (fd.status === 'ACTIVE') {
                  <button (click)="previewFor(fd)" [disabled]="busyId() === fd.id">Preview</button>
                }
              </td>
            </tr>
            @if (previewingId() === fd.id && preview()) {
              <tr class="preview-row">
                <td colspan="9">
                  <div class="preview-box">
                    <p>
                      <strong>{{ preview()!.is_early ? 'Early liquidation' : 'Maturity liquidation' }}</strong>
                      &middot; {{ preview()!.elapsed_days }} days elapsed &middot; rate {{ preview()!.rate_applied }}%
                    </p>
                    <p>
                      Principal: {{ preview()!.principal }} &middot;
                      Gross interest: {{ preview()!.gross_interest }} &middot;
                      Fee: {{ preview()!.fee_applied }} &middot;
                      Net interest: {{ preview()!.net_interest }}
                    </p>
                    <p><strong>Total payout: {{ preview()!.total_payout }}</strong></p>
                    <label class="inline-label">Branch ID <input type="number" [(ngModel)]="liquidationBranchId" name="liq_branch" /></label>
                    <button (click)="confirmLiquidate(fd)" [disabled]="busyId() === fd.id" class="confirm">
                      Confirm Liquidation
                    </button>
                    <button (click)="cancelPreview()" class="cancel">Cancel</button>
                  </div>
                </td>
              </tr>
            }
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .success { color: #1a7f37; }
    table { font-size: 0.85rem; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border: 1px solid #1e2761;
      background: white;
      color: #1e2761;
      border-radius: 6px;
      font-size: 0.8rem;
    }
    .preview-row td { background: #f4f6fb; padding: 0; }
    .preview-box { padding: 1rem; }
    .preview-box p { margin: 0.3rem 0; font-size: 0.85rem; }
    .preview-box button {
      padding: 0.4rem 0.9rem;
      border-radius: 6px;
      font-size: 0.85rem;
      border: none;
      margin-right: 0.5rem;
      margin-top: 0.5rem;
    }
    .confirm { background: #1a7f37; color: white; }
    .cancel { background: #d5d9e6; color: #1b2140; }
    .inline-label { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; margin-right: 0.75rem; }
    .inline-label input { width: 80px; padding: 0.3rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    .create-form {
      background: white;
      border-radius: 8px;
      padding: 0.8rem 1rem;
      margin-bottom: 1.5rem;
    }
    .create-form summary { cursor: pointer; font-weight: 500; }
    .create-form form {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.6rem 1rem;
      max-width: 640px;
    }
    .create-form label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    .checkbox-label { flex-direction: row !important; align-items: center; gap: 0.5rem !important; }
    .create-form input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    .create-form button {
      grid-column: 1 / -1;
      padding: 0.5rem;
      background: #1e2761;
      color: white;
      border: none;
      border-radius: 6px;
    }
  `],
})
export class FixedDepositListComponent implements OnInit {
  deposits = signal<FixedDeposit[]>([]);
  loading = signal(true);

  booking = signal(false);
  bookError = signal<string | null>(null);

  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);
  actionMessage = signal<string | null>(null);

  previewingId = signal<number | null>(null);
  preview = signal<LiquidationCalculation | null>(null);
  liquidationBranchId: number | null = null;
  backdatingEnabled = false;
  todayIso = new Date().toISOString().split('T')[0];

  form = {
    customer_account_id: null as number | null,
    settlement_account_id: null as number | null,
    branch_id: null as number | null,
    principal_amount: null as number | null,
    interest_rate: null as number | null,
    pre_liquidation_rate: null as number | null,
    pre_liquidation_penalty_fee: 0 as number | null,
    tenor_days: null as number | null,
    narration: '',
    start_date: '',
    backdate_reason: '',
  };

  constructor(private api: FixedDepositApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (res) => {
        this.deposits.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
      },
    });
  }

  onBook(): void {
    const f = this.form;
    if (!f.customer_account_id || !f.settlement_account_id || !f.branch_id || !f.principal_amount || f.interest_rate === null || f.pre_liquidation_rate === null || !f.tenor_days) {
      return;
    }

    if (this.backdatingEnabled && (!f.start_date || !f.backdate_reason)) {
      this.bookError.set('Start date and a backdate reason are both required when backdating is enabled.');
      return;
    }

    this.booking.set(true);
    this.bookError.set(null);

    this.api
      .book({
        customer_account_id: f.customer_account_id,
        settlement_account_id: f.settlement_account_id,
        branch_id: f.branch_id,
        principal_amount: f.principal_amount,
        interest_rate: f.interest_rate,
        pre_liquidation_rate: f.pre_liquidation_rate,
        pre_liquidation_penalty_fee: f.pre_liquidation_penalty_fee ?? 0,
        tenor_days: f.tenor_days,
        narration: f.narration || undefined,
        start_date: this.backdatingEnabled ? f.start_date : undefined,
        backdate_reason: this.backdatingEnabled ? f.backdate_reason : undefined,
      })
      .subscribe({
        next: () => {
          this.booking.set(false);
          this.backdatingEnabled = false;
          this.form = {
            customer_account_id: null, settlement_account_id: null, branch_id: null, principal_amount: null,
            interest_rate: null, pre_liquidation_rate: null, pre_liquidation_penalty_fee: 0,
            tenor_days: null, narration: '', start_date: '', backdate_reason: '',
          };
          this.load();
        },
        error: (err) => {
          this.booking.set(false);
          this.bookError.set(err?.error?.message ?? 'Failed to book fixed deposit.');
        },
      });
  }

  previewFor(fd: FixedDeposit): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.busyId.set(fd.id);

    this.api.previewLiquidation(fd.id).subscribe({
      next: (res) => {
        this.busyId.set(null);
        this.previewingId.set(fd.id);
        this.preview.set(res.data);
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Failed to calculate preview.');
      },
    });
  }

  cancelPreview(): void {
    this.previewingId.set(null);
    this.preview.set(null);
  }

  confirmLiquidate(fd: FixedDeposit): void {
    if (!this.liquidationBranchId) {
      this.actionError.set('Branch ID is required to liquidate.');
      return;
    }

    this.busyId.set(fd.id);
    this.actionError.set(null);
    this.actionMessage.set(null);

    this.api.liquidate(fd.id, this.liquidationBranchId).subscribe({
      next: () => {
        this.busyId.set(null);
        this.previewingId.set(null);
        this.preview.set(null);
        this.liquidationBranchId = null;
        this.actionMessage.set(`${fd.fd_no} liquidated successfully.`);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Liquidation failed.');
      },
    });
  }
}
MBOS_EOF

echo "Backdating Part 3 of 3 applied (frontend)."