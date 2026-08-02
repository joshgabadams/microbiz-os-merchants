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
