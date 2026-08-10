#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend

cat > src/app/core/tessa-api.service.ts << 'MBOS_EOF'
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
    const query: Record<string, string> = {};
    if (params?.status) query['status'] = params.status;
    if (params?.alert_type) query['alert_type'] = params.alert_type;
    if (params?.severity) query['severity'] = params.severity;

    return this.http.get<ApiResponse<TessaAlert[]>>(`${this.base}/alerts`, { params: query });
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

cat > src/app/features/tessa/tessa-dashboard.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SlicePipe } from '@angular/common';
import { TessaApiService, TessaAlert, TessaSummary } from '../../core/tessa-api.service';

@Component({
  selector: 'app-tessa-dashboard',
  standalone: true,
  imports: [FormsModule, SlicePipe],
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

    @if (loadError()) {
      <p class="error">{{ loadError() }}</p>
    }

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
    .error { color: #b3261e; }
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
  loadError = signal<string | null>(null);

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
    this.loadError.set(null);
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
        error: (err) => {
          this.loading.set(false);
          this.loadError.set(err?.error?.message ?? 'Failed to load TESSA alerts.');
        },
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

echo "Fixed the undefined query-param bug (alerts silently filtered on literal severity=undefined/alert_type=undefined) and added visible error state."