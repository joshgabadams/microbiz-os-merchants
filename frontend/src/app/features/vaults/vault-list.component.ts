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
