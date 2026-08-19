import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import {
  MerchantTransactionPage,
  MerchantTransactionQuery,
  PortalTransaction,
} from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-transactions',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <section class="transactions-page">
      <header>
        <div><p>Payments and settlements</p><h1>Transactions</h1><span>Search, filter, and review your business activity.</span></div>
        <button class="export-button" type="button" (click)="exportCsv()" [disabled]="exporting()">
          {{ exporting() ? 'Preparing export…' : '↓ Export CSV' }}
        </button>
      </header>

      <section class="filters">
        <label class="search"><span>Search</span><input name="search" [(ngModel)]="query.search" (keyup.enter)="applyFilters()" placeholder="Transaction number, reference, or narration" /></label>
        <label><span>Status</span><select name="status" [(ngModel)]="query.status" (change)="applyFilters()"><option value="">All statuses</option><option value="INITIATED">Initiated</option><option value="PENDING">Pending</option><option value="SUCCESSFUL">Successful</option><option value="FAILED">Failed</option></select></label>
        <label><span>Type</span><select name="type" [(ngModel)]="query.type" (change)="applyFilters()"><option value="">All types</option><option value="QR_COLLECTION">QR collection</option><option value="POS_COLLECTION">POS collection</option><option value="SETTLEMENT">Settlement</option><option value="REVERSAL">Reversal</option><option value="ADJUSTMENT">Adjustment</option></select></label>
        <label><span>From</span><input type="date" name="dateFrom" [(ngModel)]="query.dateFrom" (change)="applyFilters()" /></label>
        <label><span>To</span><input type="date" name="dateTo" [(ngModel)]="query.dateTo" (change)="applyFilters()" /></label>
        <button type="button" class="search-button" (click)="applyFilters()">Search</button>
      </section>

      @if (exportError()) { <div class="page-alert" role="alert">{{ exportError() }}</div> }

      <section class="transaction-card">
        @if (loading()) {
          <div class="loading-state"><span></span><p>Loading transactions…</p></div>
        } @else if (error()) {
          <div class="empty-state"><strong>Transactions unavailable</strong><p>{{ error() }}</p><button (click)="load()">Try again</button></div>
        } @else if (page()?.items?.length === 0) {
          <div class="empty-state"><div>↔</div><strong>No matching transactions</strong><p>Adjust the filters or date range and try again.</p><button (click)="clearFilters()">Clear filters</button></div>
        } @else if (page(); as data) {
          <div class="responsive-table">
            <table>
              <thead><tr><th>Transaction number</th><th>Type</th><th>Reference</th><th>Date & time</th><th>Status</th><th>Posting</th><th class="right">Amount</th><th></th></tr></thead>
              <tbody>
                @for (transaction of data.items; track transaction.id) {
                  <tr [class.selected]="selected()?.id === transaction.id">
                    <td><strong>{{ transaction.transactionNo }}</strong></td>
                    <td><span class="type-icon">{{ typeIcon(transaction.type) }}</span>{{ typeLabel(transaction.type) }}</td>
                    <td><strong>{{ transaction.reference || '—' }}</strong><small>{{ transaction.narration || 'No narration' }}</small></td>
                    <td>{{ transaction.transactionDate | date:'dd MMM yyyy, HH:mm' }}</td>
                    <td><span [class]="transaction.isReversed ? 'status status--reversed' : 'status status--' + transaction.status.toLowerCase()">{{ transaction.isReversed ? 'Reversed' : statusLabel(transaction.status) }}</span></td>
                    <td><span [class]="transaction.posted ? 'posting posting--posted' : 'posting'">{{ transaction.posted ? 'Posted' : 'Unposted' }}</span></td>
                    <td class="right amount">{{ transaction.amount | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</td>
                    <td><button class="details-button" type="button" (click)="toggleDetails(transaction)" [attr.aria-label]="'View ' + transaction.transactionNo">{{ selected()?.id === transaction.id ? '⌃' : '›' }}</button></td>
                  </tr>
                  @if (selected()?.id === transaction.id) {
                    <tr class="detail-row"><td colspan="8">
                      @if (detailLoading()) {
                        <div class="detail-loading">Loading transaction details…</div>
                      } @else if (detailError()) {
                        <div class="detail-error">{{ detailError() }}</div>
                      } @else if (selected(); as detail) {
                        <div class="details">
                          <div><span>Transaction number</span><strong>{{ detail.transactionNo }}</strong></div>
                          <div><span>External reference</span><strong>{{ detail.reference || 'Not supplied' }}</strong></div>
                          <div class="narration"><span>Narration</span><strong>{{ detail.narration || 'No narration supplied' }}</strong></div>
                          <div><span>GL posting</span><strong>{{ detail.posted ? 'Posted' : 'Not posted' }}</strong></div>
                          <div><span>Reversal</span><strong>{{ detail.isReversed ? 'Reversed' : 'Not reversed' }}</strong></div>
                          <div><span>Transaction date</span><strong>{{ detail.transactionDate | date:'medium' }}</strong></div>
                        </div>
                      }
                    </td></tr>
                  }
                }
              </tbody>
            </table>
          </div>
          <footer>
            <span>Showing {{ firstItem }}–{{ lastItem }} of {{ data.total }}</span>
            <label>Rows <select name="perPage" [(ngModel)]="query.perPage" (change)="changePageSize()"><option [ngValue]="10">10</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option></select></label>
            <div><button type="button" (click)="changePage(data.page - 1)" [disabled]="data.page === 1">Previous</button><span>Page {{ data.page }} of {{ data.lastPage }}</span><button type="button" (click)="changePage(data.page + 1)" [disabled]="data.page === data.lastPage">Next</button></div>
          </footer>
        }
      </section>
    </section>
  `,
  styles: [`
    .transactions-page { display: grid; gap: 1.35rem; }
    header { display: flex; justify-content: space-between; align-items: end; gap: 1rem; } header p { text-transform: uppercase; color: #a06e00; letter-spacing: .11em; font-size: .65rem; font-weight: 800; margin: 0; } header h1 { color: #182250; font-size: 2rem; letter-spacing: -.04em; margin: .25rem 0; } header span { color: #7e8497; font-size: .8rem; }
    .export-button { background: #fff; border: 1px solid #dce0e9; color: #303a56; border-radius: .55rem; padding: .67rem .9rem; font-size: .75rem; font-weight: 750; }.export-button:disabled { opacity: .55; }
    .filters { background: #fff; border: 1px solid #e0e4ed; border-radius: .75rem; padding: 1rem; display: grid; grid-template-columns: 1.5fr repeat(4, minmax(120px, .8fr)) auto; gap: .75rem; align-items: end; }.filters label { display: grid; gap: .35rem; color: #747b8e; font-size: .64rem; font-weight: 750; }.filters input, .filters select, footer select { width: 100%; min-width: 0; border: 1px solid #d9dde7; border-radius: .5rem; padding: .62rem .65rem; background: #fff; color: #3b4359; font-size: .72rem; outline: 0; }.filters input:focus, .filters select:focus { border-color: #4057ad; }.search-button { border: 0; border-radius: .5rem; background: #1e2761; color: white; padding: .67rem .85rem; font-size: .72rem; font-weight: 750; }
    .transaction-card { background: #fff; border: 1px solid #e0e4ed; border-radius: .75rem; overflow: hidden; min-height: 260px; }.responsive-table { overflow-x: auto; } table { min-width: 1000px; } th { background: #fafbfc; font-size: .59rem; } td { font-size: .7rem; color: #656c7e; } td strong { color: #303950; } td small { display: block; max-width: 190px; margin-top: .2rem; color: #969cad; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.right { text-align: right; }.amount { color: #27314d; font-weight: 750; }.type-icon { display: inline-grid; width: 1.6rem; height: 1.6rem; place-items: center; border-radius: .45rem; margin-right: .45rem; background: #edf0fb; color: #314998; }
    .status, .posting { display: inline-block; padding: .24rem .48rem; border-radius: 1rem; font-size: .56rem; font-weight: 800; }.status--successful, .posting--posted { color: #23754e; background: #e3f5eb; }.status--pending, .status--initiated { color: #936400; background: #fff2ce; }.status--failed, .status--reversed { color: #aa4232; background: #fde9e5; }.posting { color: #6e7588; background: #eef0f4; }.details-button { border: 0; background: transparent; color: #536078; font-size: 1.2rem; }.selected { background: #fafbff; }.detail-row td { background: #f7f8fc; padding: 1rem 1.5rem; }.details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }.details div { display: grid; gap: .25rem; }.details span { color: #9197a8; font-size: .61rem; }.details strong { font-size: .72rem; }.details .narration { grid-column: span 2; }.detail-loading, .detail-error { padding: .5rem; color: #747b8e; }.detail-error { color: #a43b2b; }
    footer { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 1rem; padding: .9rem 1.1rem; border-top: 1px solid #ebedf2; color: #858b9d; font-size: .66rem; } footer > label { display: flex; align-items: center; gap: .45rem; } footer select { width: auto; padding: .4rem; } footer div { display: flex; justify-content: flex-end; align-items: center; gap: .65rem; } footer button, .empty-state button { border: 1px solid #dce0e9; background: #fff; color: #3c455c; border-radius: .45rem; padding: .45rem .65rem; font-size: .65rem; } footer button:disabled { opacity: .45; cursor: not-allowed; }
    .loading-state, .empty-state { min-height: 260px; display: grid; place-content: center; justify-items: center; text-align: center; color: #7f8698; }.loading-state span { width: 2rem; height: 2rem; border: 3px solid #e4e7ef; border-top-color: #1e2761; border-radius: 50%; animation: spin .75s linear infinite; }.loading-state p, .empty-state p { font-size: .75rem; }.empty-state > div { font-size: 1.6rem; color: #8490b7; margin-bottom: .5rem; }.page-alert { padding: .7rem .8rem; border-radius: .5rem; background: #fff0ee; color: #a43b2b; font-size: .72rem; } @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 1100px) { .filters { grid-template-columns: repeat(3, 1fr); }.search { grid-column: span 2; } }
    @media (max-width: 620px) { header { align-items: start; }.filters { grid-template-columns: 1fr 1fr; }.search { grid-column: 1 / -1; }.search-button { grid-column: 1 / -1; }.details { grid-template-columns: 1fr 1fr; }.details .narration { grid-column: span 2; } footer { grid-template-columns: 1fr; align-items: flex-start; } footer div { justify-content: flex-start; } }
  `],
})
export class MerchantTransactionsComponent implements OnInit {
  query: MerchantTransactionQuery = { search: '', status: '', type: '', dateFrom: '', dateTo: '', page: 1, perPage: 10 };
  page = signal<MerchantTransactionPage | null>(null);
  selected = signal<PortalTransaction | null>(null);
  loading = signal(true);
  detailLoading = signal(false);
  exporting = signal(false);
  error = signal<string | null>(null);
  detailError = signal<string | null>(null);
  exportError = signal<string | null>(null);

  constructor(private readonly api: MerchantPortalApiService) {}

  ngOnInit(): void { this.load(); }
  get firstItem(): number { const page = this.page(); return page?.total ? (page.page - 1) * page.perPage + 1 : 0; }
  get lastItem(): number { const page = this.page(); return page ? Math.min(page.page * page.perPage, page.total) : 0; }

  load(): void {
    this.loading.set(true); this.error.set(null); this.selected.set(null);
    this.api.getTransactions(this.query).subscribe({
      next: (page) => { this.page.set(page); this.loading.set(false); },
      error: (error) => { this.error.set(error?.error?.message ?? 'We could not retrieve your transaction history.'); this.loading.set(false); },
    });
  }

  applyFilters(): void {
    if (this.query.dateFrom && this.query.dateTo && this.query.dateFrom > this.query.dateTo) {
      this.error.set('The start date must be before the end date.');
      return;
    }
    this.query.page = 1;
    this.load();
  }

  clearFilters(): void { this.query = { search: '', status: '', type: '', dateFrom: '', dateTo: '', page: 1, perPage: 10 }; this.load(); }
  changePage(page: number): void { this.query.page = page; this.load(); }
  changePageSize(): void { this.query.page = 1; this.load(); }

  toggleDetails(transaction: PortalTransaction): void {
    if (this.selected()?.id === transaction.id) { this.selected.set(null); return; }
    this.selected.set(transaction); this.detailLoading.set(true); this.detailError.set(null);
    this.api.getTransaction(transaction.id).subscribe({
      next: (detail) => { this.selected.set(detail); this.detailLoading.set(false); },
      error: (error) => { this.detailError.set(error?.error?.message ?? 'Transaction details are unavailable.'); this.detailLoading.set(false); },
    });
  }

  typeLabel(type: PortalTransaction['type']): string { return ({ QR_COLLECTION: 'QR collection', POS_COLLECTION: 'POS collection', SETTLEMENT: 'Settlement', REVERSAL: 'Reversal', ADJUSTMENT: 'Adjustment' })[type]; }
  typeIcon(type: PortalTransaction['type']): string { return type === 'SETTLEMENT' ? '↓' : type === 'REVERSAL' ? '↩' : type === 'ADJUSTMENT' ? '±' : '↗'; }
  statusLabel(status: PortalTransaction['status']): string { return status.charAt(0) + status.slice(1).toLowerCase(); }

  exportCsv(): void {
    this.exporting.set(true); this.exportError.set(null);
    this.api.exportTransactions(this.query).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `microbiz-transactions-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(anchor); anchor.click(); anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 0);
        this.exporting.set(false);
      },
      error: (error) => { this.exportError.set(error?.error?.message ?? 'The transaction export could not be prepared.'); this.exporting.set(false); },
    });
  }
}
