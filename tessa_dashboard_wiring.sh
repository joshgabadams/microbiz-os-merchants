#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend

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
        path: 'agents',
        loadComponent: () =>
          import('./features/agents/agent-list.component').then(
            (m) => m.AgentListComponent
          ),
      },
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
      {
        path: 'fixed-deposits',
        loadComponent: () =>
          import('./features/fixed-deposits/fixed-deposit-list.component').then(
            (m) => m.FixedDepositListComponent
          ),
      },
      {
        path: 'tessa',
        loadComponent: () =>
          import('./features/tessa/tessa-dashboard.component').then(
            (m) => m.TessaDashboardComponent
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
          <a routerLink="/tessa" routerLinkActive="active">TESSA</a>
          <a routerLink="/vaults" routerLinkActive="active">Vaults</a>
          <a routerLink="/tellers" routerLinkActive="active">Tellers</a>
          <a routerLink="/float-transfer" routerLinkActive="active">Float Transfer</a>
          <a routerLink="/approvals" routerLinkActive="active">Approvals</a>
          <a routerLink="/customer-cash" routerLinkActive="active">Customer Cash</a>
          <a routerLink="/balancing" routerLinkActive="active">Balancing</a>
          <a routerLink="/branch-eod" routerLinkActive="active">Branch EOD</a>
          <a routerLink="/call-over-report" routerLinkActive="active">Call-Over Report</a>
          <a routerLink="/ledger-statement" routerLinkActive="active">Ledger Statement</a>
          <a routerLink="/agents" routerLinkActive="active">Agents</a>
          <a routerLink="/fixed-deposits" routerLinkActive="active">Fixed Deposits</a>
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

echo "TESSA dashboard routing + sidebar link applied."