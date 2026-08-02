#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend
mkdir -p src/app/features/ledger-statement

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

export interface LedgerEntry {
  id: number;
  reference_no: string;
  transaction_type: string;
  narration: string | null;
  debit: string;
  credit: string;
  running_balance: string;
  status: string;
  transaction_date: string;
}

export interface LedgerStatement {
  teller_id?: number;
  vault_id?: number;
  from_date: string;
  to_date: string;
  opening_balance: string;
  closing_balance: string;
  count: number;
  entries: LedgerEntry[];
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

  tellerLedger(params: {
    teller_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<LedgerStatement>> {
    return this.http.get<ApiResponse<LedgerStatement>>(`${this.base}/teller-ledger`, {
      params: params as unknown as Record<string, string | number>,
    });
  }

  vaultLedger(params: {
    vault_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<LedgerStatement>> {
    return this.http.get<ApiResponse<LedgerStatement>>(`${this.base}/vault-ledger`, {
      params: params as unknown as Record<string, string | number>,
    });
  }
}
MBOS_EOF

cat > src/app/features/ledger-statement/ledger-statement.component.ts << 'MBOS_EOF'
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ReportApiService, LedgerStatement } from '../../core/report-api.service';

@Component({
  selector: 'app-ledger-statement',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1 class="no-print">Teller / Vault Statement (Running Balance)</h1>

    <form class="filters no-print" (ngSubmit)="onRun()">
      <label>
        Type
        <select name="statementType" [(ngModel)]="statementType">
          <option value="teller">Teller</option>
          <option value="vault">Vault</option>
        </select>
      </label>

      @if (statementType === 'teller') {
        <label>Teller ID <input type="number" name="teller_id" [(ngModel)]="tellerId" required /></label>
      } @else {
        <label>Vault ID <input type="number" name="vault_id" [(ngModel)]="vaultId" required /></label>
      }

      <label>From Date <input type="date" name="from_date" [(ngModel)]="fromDate" required /></label>
      <label>To Date <input type="date" name="to_date" [(ngModel)]="toDate" required /></label>

      <button type="submit" [disabled]="loading()">
        {{ loading() ? 'Running...' : 'Generate Statement' }}
      </button>

      @if (statement()) {
        <button type="button" (click)="print()">Print</button>
      }
    </form>

    @if (error()) {
      <p class="error no-print">{{ error() }}</p>
    }

    @if (statement(); as s) {
      <div class="report">
        <div class="report-header">
          <h2>
            {{ statementType === 'teller' ? 'Teller #' + s.teller_id : 'Vault #' + s.vault_id }}
            Statement -- {{ s.from_date }} to {{ s.to_date }}
          </h2>
          <p>
            Opening Balance: {{ s.opening_balance }} &middot;
            Closing Balance: {{ s.closing_balance }} &middot;
            {{ s.count }} entries
          </p>
        </div>

        @if (s.entries.length === 0) {
          <p>No ledger entries found for this period. Opening balance carried forward: {{ s.opening_balance }}.</p>
        } @else {
          <table>
            <thead>
              <tr>
                <th>Date/Time</th>
                <th>Reference</th>
                <th>Type</th>
                <th>Narration</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Running Balance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr class="opening-row">
                <td colspan="6"><em>Opening Balance</em></td>
                <td><strong>{{ s.opening_balance }}</strong></td>
                <td></td>
              </tr>
              @for (entry of s.entries; track entry.id) {
                <tr>
                  <td>{{ entry.transaction_date }}</td>
                  <td>{{ entry.reference_no }}</td>
                  <td>{{ entry.transaction_type }}</td>
                  <td>{{ entry.narration ?? '-' }}</td>
                  <td>{{ +entry.debit > 0 ? entry.debit : '-' }}</td>
                  <td>{{ +entry.credit > 0 ? entry.credit : '-' }}</td>
                  <td>{{ entry.running_balance }}</td>
                  <td>{{ entry.status }}</td>
                </tr>
              }
              <tr class="closing-row">
                <td colspan="6"><em>Closing Balance</em></td>
                <td><strong>{{ s.closing_balance }}</strong></td>
                <td></td>
              </tr>
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
    .opening-row, .closing-row { background: #f4f6fb; }

    @media print {
      .no-print { display: none !important; }
      body { background: white; }
    }
  `],
})
export class LedgerStatementComponent {
  statementType: 'teller' | 'vault' = 'teller';
  tellerId: number | null = null;
  vaultId: number | null = null;
  fromDate = '';
  toDate = '';

  loading = signal(false);
  error = signal<string | null>(null);
  statement = signal<LedgerStatement | null>(null);

  constructor(private api: ReportApiService) {}

  onRun(): void {
    if (!this.fromDate || !this.toDate) {
      return;
    }

    this.loading.set(true);
    this.error.set(null);
    this.statement.set(null);

    const obs =
      this.statementType === 'teller'
        ? this.tellerId
          ? this.api.tellerLedger({ teller_id: this.tellerId, from_date: this.fromDate, to_date: this.toDate })
          : null
        : this.vaultId
          ? this.api.vaultLedger({ vault_id: this.vaultId, from_date: this.fromDate, to_date: this.toDate })
          : null;

    if (!obs) {
      this.loading.set(false);
      return;
    }

    obs.subscribe({
      next: (res) => {
        this.loading.set(false);
        this.statement.set(res.data);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err?.error?.message ?? 'Failed to generate statement.');
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
      {
        path: 'ledger-statement',
        loadComponent: () =>
          import('./features/ledger-statement/ledger-statement.component').then(
            (m) => m.LedgerStatementComponent
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
          <a routerLink="/ledger-statement" routerLinkActive="active">Ledger Statement</a>
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

echo "Ledger statement report added."