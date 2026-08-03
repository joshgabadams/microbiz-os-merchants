#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os/frontend

cat > src/app/core/models/api.models.ts << 'MBOS_EOF'
export interface BranchSummary {
  id: number;
  name: string;
  code: string;
}

export interface GlAccountSummary {
  id: number;
  name: string;
  gl_code: string;
  type: string;
  usage: string;
}

export interface BalanceSummary {
  id: number;
  currency: string;
  ledger_balance: string;
  available_balance: string;
  locked_balance: string;
}

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
  branch?: BranchSummary;
  glAccount?: GlAccountSummary;
  balance?: BalanceSummary;
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
  branch?: BranchSummary;
  vault?: Vault;
  glAccount?: GlAccountSummary;
  balance?: BalanceSummary;
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

  create(payload: {
    branch_id: number;
    vault_id: number;
    gl_account_id: number;
    teller_code: string;
    display_name: string;
    daily_limit: number;
    opening_cash_limit: number;
    minimum_cash: number;
    maximum_cash: number;
    active: boolean;
    status: string;
  }): Observable<ApiResponse<TellerModel>> {
    return this.http.post<ApiResponse<TellerModel>>(this.base, payload);
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
            <th>Branch</th>
            <th>GL Account</th>
            <th>Type</th>
            <th>Currency</th>
            <th>Min / Max Balance</th>
            <th>Ledger Balance</th>
            <th>Available Balance</th>
            <th>Active</th>
          </tr>
        </thead>
        <tbody>
          @for (vault of vaults(); track vault.id) {
            <tr>
              <td>{{ vault.code }}</td>
              <td>{{ vault.name }}</td>
              <td>{{ vault.branch?.name ?? ('Branch #' + vault.branch_id) }}</td>
              <td>{{ vault.glAccount?.name ?? ('GL #' + vault.gl_account_id) }}</td>
              <td>{{ vault.type }}</td>
              <td>{{ vault.currency }}</td>
              <td>{{ vault.minimum_balance }} / {{ vault.maximum_balance }}</td>
              <td>{{ vault.balance?.ledger_balance ?? '-' }}</td>
              <td>{{ vault.balance?.available_balance ?? '-' }}</td>
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
    table { font-size: 0.85rem; }
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

    <details class="create-form">
      <summary>+ New Teller</summary>
      <form (ngSubmit)="onCreate()">
        <label>Branch ID <input type="number" name="branch_id" [(ngModel)]="form.branch_id" required /></label>
        <label>Vault ID <input type="number" name="vault_id" [(ngModel)]="form.vault_id" required /></label>
        <label>GL Account ID <input type="number" name="gl_account_id" [(ngModel)]="form.gl_account_id" required /></label>
        <label>Teller Code <input type="text" name="teller_code" [(ngModel)]="form.teller_code" required /></label>
        <label>Display Name <input type="text" name="display_name" [(ngModel)]="form.display_name" required /></label>
        <label>Daily Limit <input type="number" name="daily_limit" [(ngModel)]="form.daily_limit" required /></label>
        <label>Opening Cash Limit <input type="number" name="opening_cash_limit" [(ngModel)]="form.opening_cash_limit" required /></label>
        <label>Minimum Cash <input type="number" name="minimum_cash" [(ngModel)]="form.minimum_cash" required /></label>
        <label>Maximum Cash <input type="number" name="maximum_cash" [(ngModel)]="form.maximum_cash" required /></label>
        <label>
          Status
          <select name="status" [(ngModel)]="form.status" required>
            <option value="CLOSED">CLOSED</option>
            <option value="OPEN">OPEN</option>
            <option value="SUSPENDED">SUSPENDED</option>
          </select>
        </label>
        @if (createError()) {
          <p class="error">{{ createError() }}</p>
        }
        <button type="submit" [disabled]="creating()">
          {{ creating() ? 'Creating...' : 'Create Teller' }}
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
            <th>Branch</th>
            <th>Vault</th>
            <th>GL Account</th>
            <th>Status</th>
            <th>Daily Limit</th>
            <th>Cash Range</th>
            <th>Ledger Balance</th>
            <th>Available Balance</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (teller of tellers(); track teller.id) {
            <tr>
              <td>{{ teller.teller_code }}</td>
              <td>{{ teller.display_name }}</td>
              <td>{{ teller.branch?.name ?? ('Branch #' + teller.branch_id) }}</td>
              <td>{{ teller.vault?.code ?? (teller.vault_id ? ('Vault #' + teller.vault_id) : '-') }}</td>
              <td>{{ teller.glAccount?.name ?? (teller.gl_account_id ? ('GL #' + teller.gl_account_id) : '-') }}</td>
              <td>{{ teller.status }}</td>
              <td>{{ teller.daily_limit }}</td>
              <td>{{ teller.minimum_cash }} / {{ teller.maximum_cash }}</td>
              <td>{{ teller.balance?.ledger_balance ?? '-' }}</td>
              <td>{{ teller.balance?.available_balance ?? '-' }}</td>
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
    table { font-size: 0.85rem; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border: 1px solid #1e2761;
      background: white;
      color: #1e2761;
      border-radius: 6px;
      font-size: 0.8rem;
    }
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
    .create-form input, .create-form select { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
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
export class TellerListComponent implements OnInit {
  tellers = signal<TellerModel[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);
  actionMessage = signal<string | null>(null);

  creating = signal(false);
  createError = signal<string | null>(null);

  form = {
    branch_id: null as number | null,
    vault_id: null as number | null,
    gl_account_id: null as number | null,
    teller_code: '',
    display_name: '',
    daily_limit: null as number | null,
    opening_cash_limit: null as number | null,
    minimum_cash: null as number | null,
    maximum_cash: null as number | null,
    status: 'CLOSED',
  };

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

  onCreate(): void {
    const f = this.form;
    if (
      !f.branch_id || !f.vault_id || !f.gl_account_id || !f.teller_code || !f.display_name ||
      f.daily_limit === null || f.opening_cash_limit === null || f.minimum_cash === null || f.maximum_cash === null
    ) {
      return;
    }

    this.creating.set(true);
    this.createError.set(null);

    this.api
      .create({
        branch_id: f.branch_id,
        vault_id: f.vault_id,
        gl_account_id: f.gl_account_id,
        teller_code: f.teller_code,
        display_name: f.display_name,
        daily_limit: f.daily_limit,
        opening_cash_limit: f.opening_cash_limit,
        minimum_cash: f.minimum_cash,
        maximum_cash: f.maximum_cash,
        active: true,
        status: f.status,
      })
      .subscribe({
        next: () => {
          this.creating.set(false);
          this.form = {
            branch_id: null, vault_id: null, gl_account_id: null,
            teller_code: '', display_name: '',
            daily_limit: null, opening_cash_limit: null, minimum_cash: null, maximum_cash: null,
            status: 'CLOSED',
          };
          this.load();
        },
        error: (err) => {
          this.creating.set(false);
          this.createError.set(err?.error?.message ?? 'Failed to create teller.');
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

echo "Dashboard expansion complete."