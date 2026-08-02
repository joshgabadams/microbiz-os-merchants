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
