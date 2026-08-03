#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend
mkdir -p src/app/features/call-over-report

cat > src/app/core/report-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface CallOverTransaction {
  id: number;
  transaction_no: string;
  transaction_type: string;
  amount: string;
  currency: string;
  reference: string | null;
  narration: string | null;
  transaction_date: string;
  posted: boolean;
  performer?: { id: number; name: string };
  approver?: { id: number; name: string } | null;
}

export interface CallOverReport {
  teller_id?: number;
  vault_id?: number;
  from_date: string;
  to_date: string;
  count: number;
  total_debits: string;
  total_credits: string;
  transactions: CallOverTransaction[];
}

@Injectable({ providedIn: 'root' })
export class ReportApiService {
  private readonly base = `${environment.apiUrl}/v1/reports`;

  constructor(private http: HttpClient) {}

  tellerTransactions(params: {
    teller_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<CallOverReport>> {
    return this.http.get<ApiResponse<CallOverReport>>(`${this.base}/teller-transactions`, {
      params: params as unknown as Record<string, string | number>,
    });
  }

  vaultTransactions(params: {
    vault_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<CallOverReport>> {
    return this.http.get<ApiResponse<CallOverReport>>(`${this.base}/vault-transactions`, {
      params: params as unknown as Record<string, string | number>,
    });
  }
}
MBOS_EOF

cat > src/app/features/call-over-report/call-over-report.component.ts << 'MBOS_EOF'
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ReportApiService, CallOverReport } from '../../core/report-api.service';

@Component({
  selector: 'app-call-over-report',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1 class="no-print">Call-Over Report</h1>

    <form class="filters no-print" (ngSubmit)="onRun()">
      <label>
        Report Type
        <select name="reportType" [(ngModel)]="reportType">
          <option value="teller">Teller</option>
          <option value="vault">Vault</option>
        </select>
      </label>

      @if (reportType === 'teller') {
        <label>Teller ID <input type="number" name="teller_id" [(ngModel)]="tellerId" required /></label>
      } @else {
        <label>Vault ID <input type="number" name="vault_id" [(ngModel)]="vaultId" required /></label>
      }

      <label>From Date <input type="date" name="from_date" [(ngModel)]="fromDate" required /></label>
      <label>To Date <input type="date" name="to_date" [(ngModel)]="toDate" required /></label>

      <button type="submit" [disabled]="loading()">
        {{ loading() ? 'Running...' : 'Run Report' }}
      </button>

      @if (report()) {
        <button type="button" (click)="print()">Print</button>
      }
    </form>

    @if (error()) {
      <p class="error no-print">{{ error() }}</p>
    }

    @if (report(); as r) {
      <div class="report">
        <div class="report-header">
          <h2>
            {{ reportType === 'teller' ? 'Teller #' + r.teller_id : 'Vault #' + r.vault_id }}
            Call-Over -- {{ r.from_date }} to {{ r.to_date }}
          </h2>
          <p>
            {{ r.count }} transaction(s) &middot;
            Total Debits: {{ r.total_debits }} &middot;
            Total Credits: {{ r.total_credits }}
          </p>
        </div>

        @if (r.transactions.length === 0) {
          <p>No transactions found for this period.</p>
        } @else {
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Date/Time</th>
                <th>Transaction No</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Narration</th>
                <th>Performed By</th>
                <th>Posted</th>
                <th class="no-print">Sign-off</th>
              </tr>
            </thead>
            <tbody>
              @for (txn of r.transactions; track txn.id; let i = $index) {
                <tr>
                  <td>{{ i + 1 }}</td>
                  <td>{{ txn.transaction_date }}</td>
                  <td>{{ txn.transaction_no }}</td>
                  <td>{{ txn.transaction_type }}</td>
                  <td>{{ txn.amount }} {{ txn.currency }}</td>
                  <td>{{ txn.reference ?? '-' }}</td>
                  <td>{{ txn.narration ?? '-' }}</td>
                  <td>{{ txn.performer?.name ?? '-' }}</td>
                  <td>{{ txn.posted ? 'Yes' : 'No' }}</td>
                  <td class="no-print sign-off"></td>
                </tr>
              }
            </tbody>
          </table>
        }
      </div>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .filters {
      background: white;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .filters label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    .filters input, .filters select { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    .filters button {
      padding: 0.55rem 1rem;
      background: #1e2761;
      color: white;
      border: none;
      border-radius: 6px;
      height: fit-content;
    }
    .report { background: white; border-radius: 8px; padding: 1.2rem; }
    .report-header h2 { font-size: 1.05rem; margin: 0 0 0.3rem; }
    .report-header p { color: #6b7290; font-size: 0.85rem; margin: 0 0 1rem; }
    table { font-size: 0.8rem; }
    .sign-off { width: 100px; border-bottom: 1px solid #333 !important; }

    @media print {
      .no-print { display: none !important; }
      body { background: white; }
    }
  `],
})
export class CallOverReportComponent {
  reportType: 'teller' | 'vault' = 'teller';
  tellerId: number | null = null;
  vaultId: number | null = null;
  fromDate = '';
  toDate = '';

  loading = signal(false);
  error = signal<string | null>(null);
  report = signal<CallOverReport | null>(null);

  constructor(private api: ReportApiService) {}

  onRun(): void {
    if (!this.fromDate || !this.toDate) {
      return;
    }

    this.loading.set(true);
    this.error.set(null);
    this.report.set(null);

    const obs =
      this.reportType === 'teller'
        ? this.tellerId
          ? this.api.tellerTransactions({ teller_id: this.tellerId, from_date: this.fromDate, to_date: this.toDate })
          : null
        : this.vaultId
          ? this.api.vaultTransactions({ vault_id: this.vaultId, from_date: this.fromDate, to_date: this.toDate })
          : null;

    if (!obs) {
      this.loading.set(false);
      return;
    }

    obs.subscribe({
      next: (res) => {
        this.loading.set(false);
        this.report.set(res.data);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err?.error?.message ?? 'Failed to generate report.');
      },
    });
  }

  print(): void {
    window.print();
  }
}
MBOS_EOF

cat > src/app/app.routes.ts << 'MBOS_EOF'
import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () =>
      import('./features/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    loadComponent: () =>
      import('./features/dashboard/dashboard.component').then((m) => m.DashboardComponent),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'merchants', pathMatch: 'full' },
      {
        path: 'merchants',
        loadComponent: () =>
          import('./features/merchants/merchant-list.component').then(
            (m) => m.MerchantListComponent
          ),
      },
      {
        path: 'wallets',
        loadComponent: () =>
          import('./features/wallets/wallet-list.component').then(
            (m) => m.WalletListComponent
          ),
      },
      {
        path: 'vaults',
        loadComponent: () =>
          import('./features/vaults/vault-list.component').then(
            (m) => m.VaultListComponent
          ),
      },
      {
        path: 'tellers',
        loadComponent: () =>
          import('./features/tellers/teller-list.component').then(
            (m) => m.TellerListComponent
          ),
      },
      {
        path: 'approvals',
        loadComponent: () =>
          import('./features/approvals/approval-list.component').then(
            (m) => m.ApprovalListComponent
          ),
      },
      {
        path: 'float-transfer',
        loadComponent: () =>
          import('./features/float-transfer/float-transfer.component').then(
            (m) => m.FloatTransferComponent
          ),
      },
      {
        path: 'customer-cash',
        loadComponent: () =>
          import('./features/customer-cash/customer-cash.component').then(
            (m) => m.CustomerCashComponent
          ),
      },
      {
        path: 'balancing',
        loadComponent: () =>
          import('./features/balancing/balancing.component').then(
            (m) => m.BalancingComponent
          ),
      },
      {
        path: 'branch-eod',
        loadComponent: () =>
          import('./features/branch-eod/branch-eod.component').then(
            (m) => m.BranchEodComponent
          ),
      },
      {
        path: 'call-over-report',
        loadComponent: () =>
          import('./features/call-over-report/call-over-report.component').then(
            (m) => m.CallOverReportComponent
          ),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
MBOS_EOF

cat > src/app/features/dashboard/dashboard.component.ts << 'MBOS_EOF'
import { Component } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="shell">
      <aside class="sidebar">
        <div class="brand">MicroBiz OS</div>
        <nav>
          <a routerLink="/vaults" routerLinkActive="active">Vaults</a>
          <a routerLink="/tellers" routerLinkActive="active">Tellers</a>
          <a routerLink="/float-transfer" routerLinkActive="active">Float Transfer</a>
          <a routerLink="/approvals" routerLinkActive="active">Approvals</a>
          <a routerLink="/customer-cash" routerLinkActive="active">Customer Cash</a>
          <a routerLink="/balancing" routerLinkActive="active">Balancing</a>
          <a routerLink="/branch-eod" routerLinkActive="active">Branch EOD</a>
          <a routerLink="/call-over-report" routerLinkActive="active">Call-Over Report</a>
          <a routerLink="/merchants" routerLinkActive="active">Merchants</a>
          <a routerLink="/wallets" routerLinkActive="active">Wallets</a>
        </nav>
        <div class="user">
          <span>{{ auth.user()?.name }}</span>
          <button (click)="logout()">Sign out</button>
        </div>
      </aside>
      <main class="content">
        <router-outlet></router-outlet>
      </main>
    </div>
  `,
  styles: [`
    .shell {
      display: flex;
      min-height: 100vh;
    }
    .sidebar {
      width: 220px;
      background: #1e2761;
      color: white;
      display: flex;
      flex-direction: column;
      padding: 1.5rem 1rem;
    }
    .brand {
      font-weight: 600;
      font-size: 1.1rem;
      margin-bottom: 2rem;
    }
    nav {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      flex: 1;
    }
    nav a {
      color: #cadcfc;
      text-decoration: none;
      padding: 0.5rem 0.6rem;
      border-radius: 6px;
      font-size: 0.9rem;
    }
    nav a.active {
      background: rgba(255,255,255,0.12);
      color: white;
    }
    .user {
      font-size: 0.85rem;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      border-top: 1px solid rgba(255,255,255,0.15);
      padding-top: 1rem;
    }
    .user button {
      background: transparent;
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      border-radius: 6px;
      padding: 0.35rem 0.6rem;
      font-size: 0.8rem;
    }
    .content {
      flex: 1;
      padding: 2rem;
    }
  `],
})
export class DashboardComponent {
  constructor(public auth: AuthService, private router: Router) {}

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        this.auth.clearSessionLocally();
        this.router.navigate(['/login']);
      },
    });
  }
}
MBOS_EOF

echo "Call-over report feature added."