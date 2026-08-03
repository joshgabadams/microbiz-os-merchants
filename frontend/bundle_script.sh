#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend
mkdir -p src/app/features/vaults src/app/features/tellers src/app/features/approvals src/app/features/customer-cash src/app/features/balancing src/app/features/branch-eod

cat > src/app/core/models/api.models.ts << 'MBOS_EOF'
export interface Vault {
  id: number;
  branch_id: number;
  gl_account_id: number;
  code: string;
  name: string;
  type: string;
  currency: string;
  minimum_balance: string;
  maximum_balance: string;
  active: boolean;
}

export interface TellerModel {
  id: number;
  branch_id: number;
  vault_id: number | null;
  gl_account_id: number | null;
  user_id: number | null;
  teller_code: string;
  staff_code: string | null;
  display_name: string;
  daily_limit: string;
  opening_cash_limit: string;
  minimum_cash: string;
  maximum_cash: string;
  active: boolean;
  status: 'OPEN' | 'CLOSED' | 'SUSPENDED';
}

export interface ApprovalRequestModel {
  id: number;
  request_no: string;
  request_type: 'ALLOCATE_FLOAT' | 'RETURN_FLOAT';
  payload: Record<string, unknown>;
  amount: string;
  currency: string;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  maker_id: number;
  checker_id: number | null;
  approved_at: string | null;
  rejected_at: string | null;
  maker_note: string | null;
  checker_note: string | null;
  created_at: string;
}

export interface Merchant {
  id: number;
  merchant_code: string;
  business_name: string;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  branch_id: number | null;
  customer_account_id: number | null;
  status: 'ACTIVE' | 'SUSPENDED' | 'DEACTIVATED';
  balance?: {
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
    currency: string;
  };
}

export interface Wallet {
  id: number;
  wallet_no: string;
  owner_name: string;
  phone: string;
  kyc_tier: number;
  status: 'ACTIVE' | 'SUSPENDED' | 'DEACTIVATED';
  balance?: {
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
    currency: string;
  };
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  data: {
    user: AuthUser;
    token: string;
    token_type: string;
  };
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
          <a routerLink="/approvals" routerLinkActive="active">Approvals</a>
          <a routerLink="/customer-cash" routerLinkActive="active">Customer Cash</a>
          <a routerLink="/balancing" routerLinkActive="active">Balancing</a>
          <a routerLink="/branch-eod" routerLinkActive="active">Branch EOD</a>
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

cat > src/app/core/vault-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Vault } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class VaultApiService {
  private readonly base = `${environment.apiUrl}/vaults`;

  constructor(private http: HttpClient) {}

  list(): Observable<Vault[]> {
    return this.http.get<Vault[]>(this.base);
  }

  show(id: number): Observable<Vault> {
    return this.http.get<Vault>(`${this.base}/${id}`);
  }

  create(payload: {
    branch_id: number;
    gl_account_id: number;
    name: string;
    active?: boolean;
  }): Observable<Vault> {
    return this.http.post<Vault>(this.base, payload);
  }
}
MBOS_EOF

cat > src/app/core/teller-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, TellerModel } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class TellerApiService {
  private readonly base = `${environment.apiUrl}/tellers`;
  private readonly v1 = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<TellerModel[]>> {
    return this.http.get<ApiResponse<TellerModel[]>>(this.base);
  }

  open(payload: {
    teller_id: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.v1}/teller/open`, payload);
  }

  close(payload: {
    teller_id: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.v1}/teller/close`, payload);
  }
}
MBOS_EOF

cat > src/app/core/approval-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, ApprovalRequestModel } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class ApprovalApiService {
  private readonly base = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  pending(): Observable<ApiResponse<ApprovalRequestModel[]>> {
    return this.http.get<ApiResponse<ApprovalRequestModel[]>>(`${this.base}/approvals/pending`);
  }

  approve(id: number, checkerNote?: string): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/approvals/${id}/approve`,
      { checker_note: checkerNote }
    );
  }

  reject(id: number, checkerNote?: string): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/approvals/${id}/reject`,
      { checker_note: checkerNote }
    );
  }

  requestAllocateFloat(payload: {
    vault_id: number;
    teller_id: number;
    amount: number;
    reference?: string;
    narration?: string;
    maker_note?: string;
  }): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/float/allocate/request`,
      payload
    );
  }

  requestReturnFloat(payload: {
    vault_id: number;
    teller_id: number;
    amount: number;
    reference?: string;
    narration?: string;
    maker_note?: string;
  }): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/float/return/request`,
      payload
    );
  }
}
MBOS_EOF

cat > src/app/core/customer-cash-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class CustomerCashApiService {
  private readonly base = `${environment.apiUrl}/v1/customer`;

  constructor(private http: HttpClient) {}

  deposit(payload: {
    teller_id: number;
    customer_account_id: number;
    amount: number;
    performed_by: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/deposit`, payload);
  }

  withdraw(payload: {
    teller_id: number;
    customer_account_id: number;
    amount: number;
    performed_by: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/withdraw`, payload);
  }
}
MBOS_EOF

cat > src/app/core/balancing-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class BalancingApiService {
  private readonly base = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  tellerBalance(payload: {
    teller_id: number;
    business_date: string;
    physical_cash: number;
    balanced_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/teller/balance`, payload);
  }

  vaultBalance(payload: {
    vault_id: number;
    business_date: string;
    physical_cash: number;
    balanced_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/vault/balance`, payload);
  }
}
MBOS_EOF

cat > src/app/core/branch-eod-api.service.ts << 'MBOS_EOF'
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class BranchEodApiService {
  private readonly base = `${environment.apiUrl}/v1/branch`;

  constructor(private http: HttpClient) {}

  close(payload: {
    branch_id: number;
    business_date: string;
    closed_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/eod`, payload);
  }
}
MBOS_EOF

cat > src/app/features/vaults/vault-list.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { VaultApiService } from '../../core/vault-api.service';
import { Vault } from '../../core/models/api.models';

@Component({
  selector: 'app-vault-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Vaults</h1>

    <details class="create-form">
      <summary>+ New Vault</summary>
      <form (ngSubmit)="onCreate()">
        <label>Branch ID <input type="number" name="branch_id" [(ngModel)]="form.branch_id" required /></label>
        <label>GL Account ID <input type="number" name="gl_account_id" [(ngModel)]="form.gl_account_id" required /></label>
        <label>Name <input type="text" name="name" [(ngModel)]="form.name" required /></label>
        @if (createError()) {
          <p class="error">{{ createError() }}</p>
        }
        <button type="submit" [disabled]="creating()">
          {{ creating() ? 'Creating...' : 'Create Vault' }}
        </button>
      </form>
    </details>

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (vaults().length === 0) {
      <p>No vaults configured yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Type</th>
            <th>Currency</th>
            <th>Active</th>
          </tr>
        </thead>
        <tbody>
          @for (vault of vaults(); track vault.id) {
            <tr>
              <td>{{ vault.code }}</td>
              <td>{{ vault.name }}</td>
              <td>{{ vault.type }}</td>
              <td>{{ vault.currency }}</td>
              <td>{{ vault.active ? 'Yes' : 'No' }}</td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .create-form {
      background: white;
      border-radius: 8px;
      padding: 0.8rem 1rem;
      margin-bottom: 1.5rem;
    }
    .create-form summary { cursor: pointer; font-weight: 500; }
    .create-form form { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.6rem; max-width: 320px; }
    .create-form label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    .create-form input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    .create-form button { padding: 0.5rem; background: #1e2761; color: white; border: none; border-radius: 6px; }
  `],
})
export class VaultListComponent implements OnInit {
  vaults = signal<Vault[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  creating = signal(false);
  createError = signal<string | null>(null);

  form = { branch_id: null as number | null, gl_account_id: null as number | null, name: '' };

  constructor(private api: VaultApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (vaults) => {
        this.vaults.set(vaults);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load vaults.');
        this.loading.set(false);
      },
    });
  }

  onCreate(): void {
    if (!this.form.branch_id || !this.form.gl_account_id || !this.form.name) {
      return;
    }

    this.creating.set(true);
    this.createError.set(null);

    this.api
      .create({
        branch_id: this.form.branch_id,
        gl_account_id: this.form.gl_account_id,
        name: this.form.name,
        active: true,
      })
      .subscribe({
        next: () => {
          this.creating.set(false);
          this.form = { branch_id: null, gl_account_id: null, name: '' };
          this.load();
        },
        error: (err) => {
          this.creating.set(false);
          this.createError.set(err?.error?.message ?? 'Failed to create vault.');
        },
      });
  }
}
MBOS_EOF

cat > src/app/features/tellers/teller-list.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TellerApiService } from '../../core/teller-api.service';
import { TellerModel } from '../../core/models/api.models';

@Component({
  selector: 'app-teller-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Tellers</h1>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }
    @if (actionMessage()) {
      <p class="success">{{ actionMessage() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (tellers().length === 0) {
      <p>No tellers configured yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Display Name</th>
            <th>Status</th>
            <th>Daily Limit</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (teller of tellers(); track teller.id) {
            <tr>
              <td>{{ teller.teller_code }}</td>
              <td>{{ teller.display_name }}</td>
              <td>{{ teller.status }}</td>
              <td>{{ teller.daily_limit }}</td>
              <td class="actions">
                @if (teller.status !== 'OPEN') {
                  <button (click)="openTeller(teller.id)" [disabled]="busyId() === teller.id">Open</button>
                }
                @if (teller.status === 'OPEN') {
                  <button (click)="closeTeller(teller.id)" [disabled]="busyId() === teller.id">Close</button>
                }
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .success { color: #1a7f37; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border: 1px solid #1e2761;
      background: white;
      color: #1e2761;
      border-radius: 6px;
      font-size: 0.8rem;
    }
  `],
})
export class TellerListComponent implements OnInit {
  tellers = signal<TellerModel[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);
  actionMessage = signal<string | null>(null);

  constructor(private api: TellerApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (res) => {
        this.tellers.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load tellers.');
        this.loading.set(false);
      },
    });
  }

  openTeller(tellerId: number): void {
    this.runAction(tellerId, this.api.open({ teller_id: tellerId }));
  }

  closeTeller(tellerId: number): void {
    this.runAction(tellerId, this.api.close({ teller_id: tellerId }));
  }

  private runAction(tellerId: number, obs: ReturnType<TellerApiService['open']>): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.busyId.set(tellerId);

    obs.subscribe({
      next: (res) => {
        this.busyId.set(null);
        this.actionMessage.set(res.message);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Action failed.');
      },
    });
  }
}
MBOS_EOF

cat > src/app/features/approvals/approval-list.component.ts << 'MBOS_EOF'
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApprovalApiService } from '../../core/approval-api.service';
import { ApprovalRequestModel } from '../../core/models/api.models';

@Component({
  selector: 'app-approval-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Pending Approvals</h1>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (requests().length === 0) {
      <p>No pending approvals.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Request No</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Maker Note</th>
            <th>Checker Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (request of requests(); track request.id) {
            <tr>
              <td>{{ request.request_no }}</td>
              <td>{{ request.request_type }}</td>
              <td>{{ request.amount }} {{ request.currency }}</td>
              <td>{{ request.maker_note ?? '-' }}</td>
              <td>
                <input
                  type="text"
                  placeholder="Optional note"
                  [(ngModel)]="checkerNotes[request.id]"
                  name="note-{{ request.id }}"
                />
              </td>
              <td class="actions">
                <button (click)="approve(request.id)" [disabled]="busyId() === request.id" class="approve">Approve</button>
                <button (click)="reject(request.id)" [disabled]="busyId() === request.id" class="reject">Reject</button>
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .actions { display: flex; gap: 0.4rem; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border-radius: 6px;
      font-size: 0.8rem;
      border: 1px solid transparent;
    }
    .approve { background: #1a7f37; color: white; }
    .reject { background: #b3261e; color: white; }
    input[type="text"] {
      padding: 0.3rem 0.5rem;
      border: 1px solid #d5d9e6;
      border-radius: 6px;
      font-size: 0.85rem;
      width: 140px;
    }
  `],
})
export class ApprovalListComponent implements OnInit {
  requests = signal<ApprovalRequestModel[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);

  checkerNotes: Record<number, string> = {};

  constructor(private api: ApprovalApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.pending().subscribe({
      next: (res) => {
        this.requests.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load pending approvals.');
        this.loading.set(false);
      },
    });
  }

  approve(id: number): void {
    this.busyId.set(id);
    this.actionError.set(null);

    this.api.approve(id, this.checkerNotes[id]).subscribe({
      next: () => {
        this.busyId.set(null);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Approval failed.');
      },
    });
  }

  reject(id: number): void {
    this.busyId.set(id);
    this.actionError.set(null);

    this.api.reject(id, this.checkerNotes[id]).subscribe({
      next: () => {
        this.busyId.set(null);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Rejection failed.');
      },
    });
  }
}
MBOS_EOF

cat > src/app/features/customer-cash/customer-cash.component.ts << 'MBOS_EOF'
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { CustomerCashApiService } from '../../core/customer-cash-api.service';

@Component({
  selector: 'app-customer-cash',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Customer Cash</h1>
    <p class="hint">
      No list endpoint exists yet for customers/customer accounts, so teller
      and account IDs are entered directly here rather than picked from a
      dropdown -- check Tinker or add a listing endpoint if you want that.
    </p>

    <div class="forms">
      <form (ngSubmit)="onDeposit()">
        <h2>Deposit</h2>
        <label>Teller ID <input type="number" name="teller_id_d" [(ngModel)]="deposit.teller_id" required /></label>
        <label>Customer Account ID <input type="number" name="account_id_d" [(ngModel)]="deposit.customer_account_id" required /></label>
        <label>Amount <input type="number" name="amount_d" [(ngModel)]="deposit.amount" required /></label>
        <label>Reference <input type="text" name="ref_d" [(ngModel)]="deposit.reference" /></label>
        @if (depositError()) {
          <p class="error">{{ depositError() }}</p>
        }
        @if (depositSuccess()) {
          <p class="success">{{ depositSuccess() }}</p>
        }
        <button type="submit" [disabled]="depositBusy()">
          {{ depositBusy() ? 'Processing...' : 'Deposit' }}
        </button>
      </form>

      <form (ngSubmit)="onWithdraw()">
        <h2>Withdraw</h2>
        <label>Teller ID <input type="number" name="teller_id_w" [(ngModel)]="withdraw.teller_id" required /></label>
        <label>Customer Account ID <input type="number" name="account_id_w" [(ngModel)]="withdraw.customer_account_id" required /></label>
        <label>Amount <input type="number" name="amount_w" [(ngModel)]="withdraw.amount" required /></label>
        <label>Reference <input type="text" name="ref_w" [(ngModel)]="withdraw.reference" /></label>
        @if (withdrawError()) {
          <p class="error">{{ withdrawError() }}</p>
        }
        @if (withdrawSuccess()) {
          <p class="success">{{ withdrawSuccess() }}</p>
        }
        <button type="submit" [disabled]="withdrawBusy()">
          {{ withdrawBusy() ? 'Processing...' : 'Withdraw' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    h2 { font-size: 1rem; margin-top: 0; }
    .hint { color: #6b7290; font-size: 0.85rem; max-width: 480px; }
    .forms { display: flex; gap: 2rem; flex-wrap: wrap; }
    form {
      background: white;
      border-radius: 8px;
      padding: 1.2rem;
      width: 260px;
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    button { padding: 0.5rem; background: #1e2761; color: white; border: none; border-radius: 6px; }
    .error { color: #b3261e; font-size: 0.85rem; margin: 0; }
    .success { color: #1a7f37; font-size: 0.85rem; margin: 0; }
  `],
})
export class CustomerCashComponent {
  deposit = { teller_id: null as number | null, customer_account_id: null as number | null, amount: null as number | null, reference: '' };
  withdraw = { teller_id: null as number | null, customer_account_id: null as number | null, amount: null as number | null, reference: '' };

  depositBusy = signal(false);
  depositError = signal<string | null>(null);
  depositSuccess = signal<string | null>(null);

  withdrawBusy = signal(false);
  withdrawError = signal<string | null>(null);
  withdrawSuccess = signal<string | null>(null);

  constructor(private api: CustomerCashApiService, private auth: AuthService) {}

  onDeposit(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.deposit.teller_id || !this.deposit.customer_account_id || !this.deposit.amount) {
      return;
    }

    this.depositBusy.set(true);
    this.depositError.set(null);
    this.depositSuccess.set(null);

    this.api
      .deposit({
        teller_id: this.deposit.teller_id,
        customer_account_id: this.deposit.customer_account_id,
        amount: this.deposit.amount,
        performed_by: userId,
        reference: this.deposit.reference || undefined,
      })
      .subscribe({
        next: (res) => {
          this.depositBusy.set(false);
          this.depositSuccess.set(res.message);
        },
        error: (err) => {
          this.depositBusy.set(false);
          this.depositError.set(err?.error?.message ?? 'Deposit failed.');
        },
      });
  }

  onWithdraw(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.withdraw.teller_id || !this.withdraw.customer_account_id || !this.withdraw.amount) {
      return;
    }

    this.withdrawBusy.set(true);
    this.withdrawError.set(null);
    this.withdrawSuccess.set(null);

    this.api
      .withdraw({
        teller_id: this.withdraw.teller_id,
        customer_account_id: this.withdraw.customer_account_id,
        amount: this.withdraw.amount,
        performed_by: userId,
        reference: this.withdraw.reference || undefined,
      })
      .subscribe({
        next: (res) => {
          this.withdrawBusy.set(false);
          this.withdrawSuccess.set(res.message);
        },
        error: (err) => {
          this.withdrawBusy.set(false);
          this.withdrawError.set(err?.error?.message ?? 'Withdrawal failed.');
        },
      });
  }
}
MBOS_EOF

cat > src/app/features/balancing/balancing.component.ts << 'MBOS_EOF'
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { BalancingApiService } from '../../core/balancing-api.service';

@Component({
  selector: 'app-balancing',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Cash Balancing</h1>

    <div class="forms">
      <form (ngSubmit)="onTellerBalance()">
        <h2>Teller Balance</h2>
        <label>Teller ID <input type="number" name="teller_id" [(ngModel)]="tellerForm.teller_id" required /></label>
        <label>Business Date <input type="date" name="business_date_t" [(ngModel)]="tellerForm.business_date" required /></label>
        <label>Physical Cash Counted <input type="number" name="physical_cash_t" [(ngModel)]="tellerForm.physical_cash" required /></label>
        <label>Note <input type="text" name="note_t" [(ngModel)]="tellerForm.note" /></label>
        @if (tellerError()) {
          <p class="error">{{ tellerError() }}</p>
        }
        @if (tellerSuccess()) {
          <p class="success">{{ tellerSuccess() }}</p>
        }
        <button type="submit" [disabled]="tellerBusy()">
          {{ tellerBusy() ? 'Balancing...' : 'Balance Teller' }}
        </button>
      </form>

      <form (ngSubmit)="onVaultBalance()">
        <h2>Vault Balance</h2>
        <label>Vault ID <input type="number" name="vault_id" [(ngModel)]="vaultForm.vault_id" required /></label>
        <label>Business Date <input type="date" name="business_date_v" [(ngModel)]="vaultForm.business_date" required /></label>
        <label>Physical Cash Counted <input type="number" name="physical_cash_v" [(ngModel)]="vaultForm.physical_cash" required /></label>
        <label>Note <input type="text" name="note_v" [(ngModel)]="vaultForm.note" /></label>
        @if (vaultError()) {
          <p class="error">{{ vaultError() }}</p>
        }
        @if (vaultSuccess()) {
          <p class="success">{{ vaultSuccess() }}</p>
        }
        <button type="submit" [disabled]="vaultBusy()">
          {{ vaultBusy() ? 'Balancing...' : 'Balance Vault' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    h2 { font-size: 1rem; margin-top: 0; }
    .forms { display: flex; gap: 2rem; flex-wrap: wrap; }
    form {
      background: white;
      border-radius: 8px;
      padding: 1.2rem;
      width: 260px;
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    button { padding: 0.5rem; background: #1e2761; color: white; border: none; border-radius: 6px; }
    .error { color: #b3261e; font-size: 0.85rem; margin: 0; }
    .success { color: #1a7f37; font-size: 0.85rem; margin: 0; }
  `],
})
export class BalancingComponent {
  tellerForm = { teller_id: null as number | null, business_date: '', physical_cash: null as number | null, note: '' };
  vaultForm = { vault_id: null as number | null, business_date: '', physical_cash: null as number | null, note: '' };

  tellerBusy = signal(false);
  tellerError = signal<string | null>(null);
  tellerSuccess = signal<string | null>(null);

  vaultBusy = signal(false);
  vaultError = signal<string | null>(null);
  vaultSuccess = signal<string | null>(null);

  constructor(private api: BalancingApiService, private auth: AuthService) {}

  onTellerBalance(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.tellerForm.teller_id || !this.tellerForm.business_date || this.tellerForm.physical_cash === null) {
      return;
    }

    this.tellerBusy.set(true);
    this.tellerError.set(null);
    this.tellerSuccess.set(null);

    this.api
      .tellerBalance({
        teller_id: this.tellerForm.teller_id,
        business_date: this.tellerForm.business_date,
        physical_cash: this.tellerForm.physical_cash,
        balanced_by: userId,
        note: this.tellerForm.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.tellerBusy.set(false);
          this.tellerSuccess.set(res.message);
        },
        error: (err) => {
          this.tellerBusy.set(false);
          this.tellerError.set(err?.error?.message ?? 'Teller balancing failed.');
        },
      });
  }

  onVaultBalance(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.vaultForm.vault_id || !this.vaultForm.business_date || this.vaultForm.physical_cash === null) {
      return;
    }

    this.vaultBusy.set(true);
    this.vaultError.set(null);
    this.vaultSuccess.set(null);

    this.api
      .vaultBalance({
        vault_id: this.vaultForm.vault_id,
        business_date: this.vaultForm.business_date,
        physical_cash: this.vaultForm.physical_cash,
        balanced_by: userId,
        note: this.vaultForm.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.vaultBusy.set(false);
          this.vaultSuccess.set(res.message);
        },
        error: (err) => {
          this.vaultBusy.set(false);
          this.vaultError.set(err?.error?.message ?? 'Vault balancing failed.');
        },
      });
  }
}
MBOS_EOF

cat > src/app/features/branch-eod/branch-eod.component.ts << 'MBOS_EOF'
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { BranchEodApiService } from '../../core/branch-eod-api.service';

@Component({
  selector: 'app-branch-eod',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Branch End of Day</h1>

    <form (ngSubmit)="onClose()">
      <label>Branch ID <input type="number" name="branch_id" [(ngModel)]="form.branch_id" required /></label>
      <label>Business Date <input type="date" name="business_date" [(ngModel)]="form.business_date" required /></label>
      <label>Note <input type="text" name="note" [(ngModel)]="form.note" /></label>
      @if (error()) {
        <p class="error">{{ error() }}</p>
      }
      @if (success()) {
        <p class="success">{{ success() }}</p>
      }
      <button type="submit" [disabled]="busy()">
        {{ busy() ? 'Closing...' : 'Close Branch EOD' }}
      </button>
    </form>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    form {
      background: white;
      border-radius: 8px;
      padding: 1.2rem;
      width: 280px;
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    button { padding: 0.5rem; background: #1e2761; color: white; border: none; border-radius: 6px; }
    .error { color: #b3261e; font-size: 0.85rem; margin: 0; }
    .success { color: #1a7f37; font-size: 0.85rem; margin: 0; }
  `],
})
export class BranchEodComponent {
  form = { branch_id: null as number | null, business_date: '', note: '' };

  busy = signal(false);
  error = signal<string | null>(null);
  success = signal<string | null>(null);

  constructor(private api: BranchEodApiService, private auth: AuthService) {}

  onClose(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.form.branch_id || !this.form.business_date) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.success.set(null);

    this.api
      .close({
        branch_id: this.form.branch_id,
        business_date: this.form.business_date,
        closed_by: userId,
        note: this.form.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.busy.set(false);
          this.success.set(res.message);
        },
        error: (err) => {
          this.busy.set(false);
          this.error.set(err?.error?.message ?? 'Branch EOD close failed.');
        },
      });
  }
}
MBOS_EOF

echo "All 15 files written successfully."