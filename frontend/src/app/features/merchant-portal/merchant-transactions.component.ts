import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantTransactionPage, MerchantTransactionQuery, PortalTransaction } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-transactions',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <section class="transactions-page">
      <header><div><p>Payments and settlements</p><h1>Transactions</h1><span>Search, filter, and review your business activity.</span></div><button class="export-button" type="button" (click)="exportCsv()">↓ Export CSV</button></header>

      <section class="filters">
        <label class="search"><span>Search</span><input name="search" [(ngModel)]="query.search" (keyup.enter)="applyFilters()" placeholder="Reference or customer" /></label>
        <label><span>Status</span><select name="status" [(ngModel)]="query.status" (change)="applyFilters()"><option value="">All statuses</option><option value="SUCCESSFUL">Successful</option><option value="PENDING">Pending</option><option value="FAILED">Failed</option></select></label>
        <label><span>Type</span><select name="type" [(ngModel)]="query.type" (change)="applyFilters()"><option value="">All types</option><option value="QR_PAYMENT">QR payment</option><option value="POS_PAYMENT">POS payment</option><option value="SETTLEMENT">Settlement</option></select></label>
        <label><span>From</span><input type="date" name="dateFrom" [(ngModel)]="query.dateFrom" (change)="applyFilters()" /></label>
        <label><span>To</span><input type="date" name="dateTo" [(ngModel)]="query.dateTo" (change)="applyFilters()" /></label>
        <button type="button" class="search-button" (click)="applyFilters()">Search</button>
      </section>

      <section class="transaction-card">
        @if (loading()) {
          <div class="loading-state"><span></span><p>Loading transactions…</p></div>
        } @else if (error()) {
          <div class="empty-state"><strong>Transactions unavailable</strong><p>{{ error() }}</p><button (click)="load()">Try again</button></div>
        } @else if (page()?.items?.length === 0) {
          <div class="empty-state"><div>↔</div><strong>No matching transactions</strong><p>Adjust the filters or date range and try again.</p><button (click)="clearFilters()">Clear filters</button></div>
        } @else if (page(); as data) {
          <div class="responsive-table"><table><thead><tr><th>Reference</th><th>Transaction</th><th>Customer</th><th>Date & time</th><th>Status</th><th>Fee</th><th class="right">Amount</th><th></th></tr></thead><tbody>
            @for (transaction of data.items; track transaction.id) {
              <tr [class.selected]="selected()?.id === transaction.id"><td><strong>{{ transaction.reference }}</strong></td><td><span class="type-icon">{{ transaction.type === 'SETTLEMENT' ? '↓' : '↗' }}</span>{{ typeLabel(transaction.type) }}</td><td>{{ transaction.customer }}</td><td>{{ transaction.createdAt | date:'dd MMM yyyy, HH:mm' }}</td><td><span [class]="'status status--' + transaction.status.toLowerCase()">{{ transaction.status }}</span></td><td>{{ transaction.fee | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</td><td class="right amount">{{ transaction.amount | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</td><td><button class="details-button" type="button" (click)="toggleDetails(transaction)">›</button></td></tr>
              @if (selected()?.id === transaction.id) {
                <tr class="detail-row"><td colspan="8"><div class="details"><div><span>Reference</span><strong>{{ transaction.reference }}</strong></div><div><span>Gross amount</span><strong>{{ transaction.amount | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</strong></div><div><span>Processing fee</span><strong>{{ transaction.fee | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</strong></div><div><span>Net amount</span><strong>{{ transaction.amount - transaction.fee | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</strong></div></div></td></tr>
              }
            }
          </tbody></table></div>
          <footer><span>Showing {{ firstItem }}–{{ lastItem }} of {{ data.total }}</span><div><button type="button" (click)="changePage(data.page - 1)" [disabled]="data.page === 1">Previous</button><span>Page {{ data.page }} of {{ data.lastPage }}</span><button type="button" (click)="changePage(data.page + 1)" [disabled]="data.page === data.lastPage">Next</button></div></footer>
        }
      </section>
    </section>
  `,
  styles: [`
    .transactions-page { display: grid; gap: 1.35rem; }
    header { display: flex; justify-content: space-between; align-items: end; gap: 1rem; } header p { text-transform: uppercase; color: #a06e00; letter-spacing: .11em; font-size: .65rem; font-weight: 800; margin: 0; } header h1 { color: #182250; font-size: 2rem; letter-spacing: -.04em; margin: .25rem 0; } header span { color: #7e8497; font-size: .8rem; }
    .export-button { background: #fff; border: 1px solid #dce0e9; color: #303a56; border-radius: .55rem; padding: .67rem .9rem; font-size: .75rem; font-weight: 750; }
    .filters { background: #fff; border: 1px solid #e0e4ed; border-radius: .75rem; padding: 1rem; display: grid; grid-template-columns: 1.5fr repeat(4, minmax(120px, .8fr)) auto; gap: .75rem; align-items: end; }
    .filters label { display: grid; gap: .35rem; color: #747b8e; font-size: .64rem; font-weight: 750; }.filters input, .filters select { width: 100%; min-width: 0; border: 1px solid #d9dde7; border-radius: .5rem; padding: .62rem .65rem; background: #fff; color: #3b4359; font-size: .72rem; outline: 0; }.filters input:focus, .filters select:focus { border-color: #4057ad; }.search-button { border: 0; border-radius: .5rem; background: #1e2761; color: white; padding: .67rem .85rem; font-size: .72rem; font-weight: 750; }
    .transaction-card { background: #fff; border: 1px solid #e0e4ed; border-radius: .75rem; overflow: hidden; min-height: 260px; }.responsive-table { overflow-x: auto; } table { min-width: 950px; } th { background: #fafbfc; font-size: .59rem; } td { font-size: .7rem; color: #656c7e; } td strong { color: #303950; }.right { text-align: right; }.amount { color: #27314d; font-weight: 750; }.type-icon { display: inline-grid; width: 1.6rem; height: 1.6rem; place-items: center; border-radius: .45rem; margin-right: .45rem; background: #edf0fb; color: #314998; }
    .status { padding: .24rem .48rem; border-radius: 1rem; font-size: .56rem; font-weight: 800; }.status--successful { color: #23754e; background: #e3f5eb; }.status--pending { color: #936400; background: #fff2ce; }.status--failed { color: #aa4232; background: #fde9e5; }.details-button { border: 0; background: transparent; color: #536078; font-size: 1.2rem; }.selected { background: #fafbff; }.detail-row td { background: #f7f8fc; padding: 1rem 1.5rem; }.details { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }.details div { display: grid; gap: .25rem; }.details span { color: #9197a8; font-size: .61rem; }.details strong { font-size: .72rem; }
    footer { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .9rem 1.1rem; border-top: 1px solid #ebedf2; color: #858b9d; font-size: .66rem; } footer div { display: flex; align-items: center; gap: .65rem; } footer button, .empty-state button { border: 1px solid #dce0e9; background: #fff; color: #3c455c; border-radius: .45rem; padding: .45rem .65rem; font-size: .65rem; } footer button:disabled { opacity: .45; cursor: not-allowed; }
    .loading-state, .empty-state { min-height: 260px; display: grid; place-content: center; justify-items: center; text-align: center; color: #7f8698; }.loading-state span { width: 2rem; height: 2rem; border: 3px solid #e4e7ef; border-top-color: #1e2761; border-radius: 50%; animation: spin .75s linear infinite; }.loading-state p, .empty-state p { font-size: .75rem; }.empty-state > div { font-size: 1.6rem; color: #8490b7; margin-bottom: .5rem; } @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 1100px) { .filters { grid-template-columns: repeat(3, 1fr); }.search { grid-column: span 2; } }
    @media (max-width: 620px) { header { align-items: start; }.filters { grid-template-columns: 1fr 1fr; }.search { grid-column: 1 / -1; }.search-button { grid-column: 1 / -1; }.details { grid-template-columns: 1fr 1fr; } footer { align-items: flex-start; flex-direction: column; } }
  `],
})
export class MerchantTransactionsComponent implements OnInit {
  query: MerchantTransactionQuery = { search: '', status: '', type: '', dateFrom: '', dateTo: '', page: 1, perPage: 6 };
  page = signal<MerchantTransactionPage | null>(null);
  selected = signal<PortalTransaction | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  constructor(private readonly api: MerchantPortalApiService) {}
  ngOnInit(): void { this.load(); }
  get firstItem(): number { const page = this.page(); return page?.total ? (page.page - 1) * page.perPage + 1 : 0; }
  get lastItem(): number { const page = this.page(); return page ? Math.min(page.page * page.perPage, page.total) : 0; }

  load(): void {
    this.loading.set(true); this.error.set(null); this.selected.set(null);
    this.api.getTransactions(this.query).subscribe({
      next: (page) => { this.page.set(page); this.loading.set(false); },
      error: () => { this.error.set('We could not retrieve your transaction history.'); this.loading.set(false); },
    });
  }
  applyFilters(): void { this.query.page = 1; this.load(); }
  clearFilters(): void { this.query = { search: '', status: '', type: '', dateFrom: '', dateTo: '', page: 1, perPage: 6 }; this.load(); }
  changePage(page: number): void { this.query.page = page; this.load(); }
  toggleDetails(transaction: PortalTransaction): void { this.selected.set(this.selected()?.id === transaction.id ? null : transaction); }
  typeLabel(type: PortalTransaction['type']): string { return ({ QR_PAYMENT: 'QR payment', POS_PAYMENT: 'POS payment', SETTLEMENT: 'Settlement' })[type]; }
  exportCsv(): void {
    const rows = this.page()?.items ?? [];
    const csv = ['Reference,Type,Customer,Amount,Fee,Currency,Status,Created At', ...rows.map((item) => [item.reference, item.type, item.customer, item.amount, item.fee, item.currency, item.status, item.createdAt].join(','))].join('\n');
    const anchor = document.createElement('a');
    anchor.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    anchor.download = 'microbiz-transactions.csv';
    anchor.click();
    URL.revokeObjectURL(anchor.href);
  }
}

