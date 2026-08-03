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
