import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import {
  MerchantMoneyOperationResult,
  MerchantSettlementSummary,
  MerchantTransactionPage,
  MerchantTransactionQuery,
  PortalTransaction,
} from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-settlements',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <section class="settlements-page">
      <header><div><p>Merchant payouts</p><h1>Settlements</h1><span>Move collected funds to your linked MicroBiz account.</span></div></header>

      @if (loading()) {
        <div class="summary-grid">@for (item of [1,2,3]; track item) { <div class="summary-card skeleton"></div> }</div>
      } @else if (error() && !summary()) {
        <div class="state-card"><strong>Settlements unavailable</strong><p>{{ error() }}</p><button (click)="load()">Try again</button></div>
      } @else if (summary(); as data) {
        <div class="summary-grid">
          <article class="summary-card primary-card"><span>Available to settle</span><strong>{{ data.availableToSettle | currency:data.currency:'symbol-narrow':'1.2-2' }}</strong><small>Funds collected and awaiting settlement</small></article>
          <article class="summary-card"><span>Merchant ledger</span><strong>{{ data.ledgerBalance | currency:data.currency:'symbol-narrow':'1.2-2' }}</strong><small>Total merchant liability balance</small></article>
          <article class="summary-card"><span>Settlement account</span><strong class="account-number">{{ data.settlementAccount }}</strong><small>{{ frequencyLabel(data.settlementFrequency) }} · MicroBiz MFB</small></article>
        </div>

        @if (result(); as completed) {
          <div class="success-banner"><span>✓</span><div><strong>Settlement completed</strong><p>{{ completed.transaction.amount | currency:completed.transaction.currency:'symbol-narrow':'1.2-2' }} was settled under {{ completed.transaction.transactionNo }}.</p></div><button type="button" (click)="result.set(null)">Dismiss</button></div>
        }

        <div class="settlement-layout">
          <section class="request-card">
            <div class="section-heading"><div><h2>Request a settlement</h2><p>Funds will be credited to your linked account.</p></div><span>Secure transfer</span></div>
            <form (ngSubmit)="reviewSettlement()" #settlementForm="ngForm">
              <label class="amount-field">Settlement amount
                <div><span>₦</span><input type="number" name="amount" [(ngModel)]="amount" required min="1" [max]="data.availableToSettle" step="0.01" placeholder="0.00" /></div>
              </label>
              <button class="max-button" type="button" (click)="useMaximum()" [disabled]="data.availableToSettle <= 0">Use full settlement balance</button>
              <label>Reference <small>Optional</small><input name="reference" [(ngModel)]="reference" maxlength="255" placeholder="Your settlement reference" /></label>
              <label>Narration <small>Optional</small><textarea name="narration" [(ngModel)]="narration" maxlength="500" rows="3" placeholder="Settlement note"></textarea></label>
              @if (formError()) { <div class="alert" role="alert">{{ formError() }}</div> }
              <button class="primary-button" type="submit" [disabled]="settlementForm.invalid || data.availableToSettle <= 0">Review settlement</button>
            </form>
          </section>

          <aside class="account-card"><div class="bank-mark">M</div><p>Destination account</p><h2>{{ data.settlementAccount }}</h2><span>MicroBiz MFB</span><hr /><div><small>Settlement schedule</small><strong>{{ frequencyLabel(data.settlementFrequency) }}</strong></div><p class="notice">Settlement destinations cannot be changed from this screen. Contact merchant support if these details are incorrect.</p></aside>
        </div>

        <section class="history-card">
          <div class="history-heading"><div><h2>Settlement history</h2><p>Previous payouts to your linked account.</p></div><div class="history-filters"><select name="historyStatus" [(ngModel)]="query.status" (change)="applyFilters()"><option value="">All statuses</option><option value="INITIATED">Initiated</option><option value="PENDING">Pending</option><option value="SUCCESSFUL">Successful</option><option value="FAILED">Failed</option></select><input type="date" name="historyFrom" [(ngModel)]="query.dateFrom" (change)="applyFilters()" /><input type="date" name="historyTo" [(ngModel)]="query.dateTo" (change)="applyFilters()" /></div></div>
          @if (historyLoading()) { <div class="history-state">Loading settlement history…</div> }
          @else if (historyError()) { <div class="history-state error-text">{{ historyError() }}</div> }
          @else if (history()?.items?.length === 0) { <div class="history-state"><strong>No settlements yet</strong><p>Your completed settlement requests will appear here.</p></div> }
          @else if (history(); as page) {
            <div class="responsive-table"><table><thead><tr><th>Transaction number</th><th>Reference</th><th>Date</th><th>Status</th><th class="right">Amount</th><th></th></tr></thead><tbody>
              @for (settlement of page.items; track settlement.id) {
                <tr><td><strong>{{ settlement.transactionNo }}</strong></td><td>{{ settlement.reference || '—' }}</td><td>{{ settlement.transactionDate | date:'dd MMM yyyy, HH:mm' }}</td><td><span [class]="'status status--' + settlement.status.toLowerCase()">{{ statusLabel(settlement.status) }}</span></td><td class="right amount">{{ settlement.amount | currency:settlement.currency:'symbol-narrow':'1.2-2' }}</td><td><button class="detail-button" type="button" (click)="toggleDetail(settlement)">{{ selected()?.id === settlement.id ? '⌃' : '›' }}</button></td></tr>
                @if (selected()?.id === settlement.id) { <tr class="detail-row"><td colspan="6">@if (detailLoading()) { Loading details… } @else if (detailError()) { <span class="error-text">{{ detailError() }}</span> } @else if (selected(); as detail) { <div class="detail-grid"><div><span>Narration</span><strong>{{ detail.narration || 'Not supplied' }}</strong></div><div><span>GL posting</span><strong>{{ detail.posted ? 'Posted' : 'Not posted' }}</strong></div><div><span>Reversal status</span><strong>{{ detail.isReversed ? 'Reversed' : 'Not reversed' }}</strong></div></div> }</td></tr> }
              }
            </tbody></table></div>
            <footer><span>Showing {{ firstItem }}–{{ lastItem }} of {{ page.total }}</span><div><button (click)="changePage(page.page - 1)" [disabled]="page.page === 1">Previous</button><span>Page {{ page.page }} of {{ page.lastPage }}</span><button (click)="changePage(page.page + 1)" [disabled]="page.page === page.lastPage">Next</button></div></footer>
          }
        </section>
      }

      @if (reviewing() && summary(); as data) {
        <div class="modal-backdrop" (click)="cancelReview()"><section class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="settlement-confirm-title" (click)="$event.stopPropagation()"><div class="confirm-icon">↓</div><p class="eyebrow">Confirm settlement</p><h2 id="settlement-confirm-title">Settle {{ amount || 0 | currency:data.currency:'symbol-narrow':'1.2-2' }}?</h2><p>The money will be credited to MicroBiz account <strong>{{ data.settlementAccount }}</strong>. This request cannot be cancelled after processing begins.</p><div class="confirm-summary"><span>Settlement amount</span><strong>{{ amount || 0 | currency:data.currency:'symbol-narrow':'1.2-2' }}</strong><span>Balance after settlement</span><strong>{{ data.availableToSettle - (amount || 0) | currency:data.currency:'symbol-narrow':'1.2-2' }}</strong></div>@if (formError()) { <div class="alert">{{ formError() }}</div> }<div class="modal-actions"><button class="secondary" type="button" (click)="cancelReview()" [disabled]="submitting()">Go back</button><button type="button" (click)="confirmSettlement()" [disabled]="submitting()">{{ submitting() ? 'Processing settlement…' : 'Confirm settlement' }}</button></div></section></div>
      }
    </section>
  `,
  styles: [`
    .settlements-page { display: grid; gap: 1.35rem; }header p, .eyebrow { text-transform: uppercase; color: #a06e00; letter-spacing: .11em; font-size: .65rem; font-weight: 800; margin: 0; }header h1 { color: #182250; font-size: 2rem; letter-spacing: -.04em; margin: .25rem 0; }header span { color: #7e8497; font-size: .8rem; }.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }.summary-card { min-height: 125px; display: grid; align-content: center; gap: .45rem; padding: 1.1rem; border: 1px solid #e0e4ed; border-radius: .75rem; background: #fff; }.summary-card span { color: #868c9e; font-size: .67rem; }.summary-card strong { color: #27314d; font-size: 1.35rem; }.summary-card small { color: #9a9faf; font-size: .62rem; }.primary-card { background: linear-gradient(120deg, #19235a, #273a83); }.primary-card span, .primary-card small { color: #bfc8e8; }.primary-card strong { color: #fff; }.account-number { font-size: 1.05rem !important; letter-spacing: .06em; }
    .settlement-layout { display: grid; grid-template-columns: 1.2fr .8fr; gap: 1rem; align-items: start; }.request-card, .account-card, .history-card { background: #fff; border: 1px solid #e0e4ed; border-radius: .8rem; padding: 1.25rem; }.section-heading { display: flex; justify-content: space-between; gap: 1rem; }.section-heading h2, .history-heading h2 { margin: 0 0 .25rem; color: #2d3650; font-size: .95rem; }.section-heading p, .history-heading p { margin: 0; color: #8a90a1; font-size: .68rem; }.section-heading > span { color: #247750; background: #e4f5eb; padding: .3rem .5rem; border-radius: 1rem; height: fit-content; font-size: .58rem; font-weight: 800; }.request-card form { display: grid; gap: 1rem; margin-top: 1.2rem; }label { color: #555d72; font-size: .68rem; font-weight: 750; }label small { color: #9a9faf; font-weight: 500; margin-left: .25rem; }input, textarea, select { display: block; width: 100%; border: 1px solid #d9dde7; border-radius: .52rem; padding: .7rem; margin-top: .4rem; color: #343d54; background: #fff; outline: 0; }.amount-field > div { display: flex; align-items: center; overflow: hidden; border: 1px solid #d9dde7; border-radius: .6rem; margin-top: .4rem; }.amount-field div span { padding-left: .85rem; color: #1e2761; font-size: 1.25rem; font-weight: 800; }.amount-field input { border: 0; margin: 0; font-size: 1.15rem; font-weight: 750; }.max-button { width: fit-content; border: 0; background: transparent; color: #2e4798; padding: 0; font-size: .65rem; font-weight: 750; }.primary-button, .modal-actions button { border: 0; border-radius: .55rem; padding: .77rem 1rem; background: #1e2761; color: #fff; font-weight: 750; }.primary-button:disabled, button:disabled { opacity: .5; }
    .account-card { background: #f8f9fc; }.bank-mark { width: 2.7rem; height: 2.7rem; display: grid; place-items: center; background: #ffcb45; color: #172052; border-radius: .65rem; font-weight: 850; }.account-card > p:first-of-type { color: #9298a8; font-size: .63rem; margin: 1rem 0 .25rem; }.account-card h2 { color: #283149; margin: 0; letter-spacing: .08em; }.account-card > span { color: #747c91; font-size: .68rem; }.account-card hr { border: 0; border-top: 1px solid #e1e4eb; margin: 1rem 0; }.account-card div:not(.bank-mark) { display: grid; gap: .25rem; }.account-card small { color: #9298a8; font-size: .6rem; }.account-card strong { color: #3c455b; font-size: .72rem; }.account-card .notice { color: #858b9d; font-size: .62rem; line-height: 1.6; margin: 1rem 0 0; }
    .success-banner { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .8rem; padding: .85rem 1rem; background: #e5f6ec; border: 1px solid #ccebd9; border-radius: .65rem; color: #23754e; }.success-banner > span { width: 2rem; height: 2rem; display: grid; place-items: center; border-radius: 50%; background: #fff; }.success-banner p { margin: .2rem 0 0; font-size: .65rem; }.success-banner button { border: 0; background: transparent; color: #23754e; font-size: .65rem; font-weight: 750; }
    .history-card { padding: 0; overflow: hidden; }.history-heading { display: flex; justify-content: space-between; align-items: end; gap: 1rem; padding: 1rem 1.15rem; border-bottom: 1px solid #e9ecf2; }.history-filters { display: flex; gap: .5rem; }.history-filters input, .history-filters select { margin: 0; padding: .5rem; font-size: .65rem; }.responsive-table { overflow-x: auto; }table { min-width: 760px; }th { background: #fafbfc; font-size: .6rem; }td { color: #687084; font-size: .7rem; }td strong { color: #303950; }.right { text-align: right; }.amount { color: #27314d; font-weight: 750; }.status { display: inline-block; padding: .23rem .45rem; border-radius: 1rem; font-size: .55rem; font-weight: 800; }.status--successful { color: #23754e; background: #e3f5eb; }.status--pending, .status--initiated { color: #936400; background: #fff2ce; }.status--failed { color: #aa4232; background: #fde9e5; }.detail-button { border: 0; background: transparent; font-size: 1.1rem; }.detail-row td { padding: 1rem 1.2rem; background: #f7f8fc; }.detail-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; }.detail-grid div { display: grid; gap: .25rem; }.detail-grid span { color: #9298a9; font-size: .6rem; }.detail-grid strong { font-size: .7rem; }.history-state, .state-card { min-height: 190px; display: grid; place-content: center; text-align: center; color: #7f8698; font-size: .72rem; }.history-state p, .state-card p { font-size: .68rem; }.error-text { color: #a43b2b; }.state-card { background: white; border: 1px solid #e0e4ed; border-radius: .75rem; }.state-card button { border: 0; background: #1e2761; color: white; padding: .6rem .8rem; border-radius: .5rem; }footer { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .85rem 1rem; border-top: 1px solid #ebedf2; color: #858b9d; font-size: .65rem; }footer div { display: flex; align-items: center; gap: .55rem; }footer button { border: 1px solid #dce0e9; background: #fff; border-radius: .45rem; padding: .4rem .6rem; font-size: .63rem; }
    .modal-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 1rem; background: rgba(12,18,48,.58); }.confirm-modal { width: min(100%, 470px); background: white; border-radius: .9rem; padding: 1.5rem; }.confirm-icon { width: 2.8rem; height: 2.8rem; display: grid; place-items: center; border-radius: 50%; background: #fff2ce; color: #966600; font-weight: 850; margin-bottom: 1rem; }.confirm-modal h2 { margin: .45rem 0; color: #25304d; }.confirm-modal > p:not(.eyebrow) { color: #777e92; font-size: .72rem; line-height: 1.6; }.confirm-summary { display: grid; grid-template-columns: 1fr auto; gap: .65rem; padding: .85rem; margin: 1rem 0; background: #f6f7fa; border-radius: .6rem; }.confirm-summary span { color: #8d93a4; font-size: .64rem; }.confirm-summary strong { color: #394259; font-size: .68rem; }.modal-actions { display: flex; justify-content: flex-end; gap: .65rem; }.secondary { background: #fff !important; color: #424b63 !important; border: 1px solid #d8dce6 !important; }.alert { padding: .7rem; border-radius: .5rem; background: #fff0ee; color: #a43b2b; font-size: .68rem; }.skeleton { background: linear-gradient(90deg, #eef0f4 25%, #f7f8fa 50%, #eef0f4 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }@keyframes shimmer { to { background-position: -200% 0; } }
    @media (max-width: 850px) { .summary-grid { grid-template-columns: 1fr 1fr; }.summary-card:last-child { grid-column: 1 / -1; }.settlement-layout { grid-template-columns: 1fr; } }
    @media (max-width: 620px) { .summary-grid { grid-template-columns: 1fr; }.summary-card:last-child { grid-column: auto; }.history-heading { align-items: start; flex-direction: column; }.history-filters { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }.history-filters select { grid-column: 1 / -1; }.detail-grid { grid-template-columns: 1fr; }footer { align-items: start; flex-direction: column; }.modal-actions { flex-direction: column-reverse; }.modal-actions button { width: 100%; } }
  `],
})
export class MerchantSettlementsComponent implements OnInit {
  summary = signal<MerchantSettlementSummary | null>(null);
  history = signal<MerchantTransactionPage | null>(null);
  selected = signal<PortalTransaction | null>(null);
  result = signal<MerchantMoneyOperationResult | null>(null);
  loading = signal(true); historyLoading = signal(true); detailLoading = signal(false); reviewing = signal(false); submitting = signal(false);
  error = signal<string | null>(null); historyError = signal<string | null>(null); detailError = signal<string | null>(null); formError = signal<string | null>(null);
  query: MerchantTransactionQuery = { search: '', status: '', type: 'SETTLEMENT', dateFrom: '', dateTo: '', page: 1, perPage: 5 };
  amount: number | null = null; reference = ''; narration = '';
  private idempotencyKey: string | null = null;

  constructor(private readonly api: MerchantPortalApiService) {}
  ngOnInit(): void { this.load(); }
  get firstItem(): number { const page = this.history(); return page?.total ? (page.page - 1) * page.perPage + 1 : 0; }
  get lastItem(): number { const page = this.history(); return page ? Math.min(page.page * page.perPage, page.total) : 0; }

  load(): void {
    this.loading.set(true); this.historyLoading.set(true); this.error.set(null); this.historyError.set(null);
    forkJoin({ summary: this.api.getSettlementSummary(), history: this.api.getSettlements(this.query) }).subscribe({
      next: ({ summary, history }) => { this.summary.set(summary); this.history.set(history); this.loading.set(false); this.historyLoading.set(false); },
      error: (error) => { this.error.set(error?.error?.message ?? 'We could not load settlement information.'); this.loading.set(false); this.historyLoading.set(false); },
    });
  }

  loadHistory(): void { this.historyLoading.set(true); this.historyError.set(null); this.selected.set(null); this.api.getSettlements(this.query).subscribe({ next: (history) => { this.history.set(history); this.historyLoading.set(false); }, error: (error) => { this.historyError.set(error?.error?.message ?? 'Settlement history is unavailable.'); this.historyLoading.set(false); } }); }
  useMaximum(): void { this.amount = this.summary()?.availableToSettle ?? null; }
  applyFilters(): void { if (this.query.dateFrom && this.query.dateTo && this.query.dateFrom > this.query.dateTo) { this.historyError.set('The start date must be before the end date.'); return; } this.query.page = 1; this.loadHistory(); }
  changePage(page: number): void { this.query.page = page; this.loadHistory(); }
  reviewSettlement(): void { const maximum = this.summary()?.availableToSettle ?? 0; if (!this.amount || this.amount <= 0) return; if (this.amount > maximum) { this.formError.set('The amount exceeds your available settlement balance.'); return; } this.formError.set(null); this.idempotencyKey = this.newIdempotencyKey(); this.reviewing.set(true); }
  cancelReview(): void { if (!this.submitting()) { this.reviewing.set(false); this.formError.set(null); this.idempotencyKey = null; } }

  confirmSettlement(): void {
    if (!this.amount || !this.idempotencyKey) return;
    this.submitting.set(true); this.formError.set(null);
    this.api.requestSettlement({ amount: this.amount.toFixed(2), idempotency_key: this.idempotencyKey, reference: this.reference.trim() || undefined, narration: this.narration.trim() || undefined }).subscribe({
      next: (result) => {
        const current = this.summary();
        if (current) this.summary.set({ ...current, ledgerBalance: result.balance.ledgerBalance, lockedBalance: result.balance.lockedBalance, availableToSettle: result.balance.lockedBalance });
        this.result.set(result); this.reviewing.set(false); this.submitting.set(false); this.amount = null; this.reference = ''; this.narration = ''; this.idempotencyKey = null; this.query.page = 1; this.loadHistory();
      },
      error: (error) => { this.formError.set(error?.error?.message ?? error?.message ?? 'The settlement could not be completed.'); this.submitting.set(false); },
    });
  }

  toggleDetail(settlement: PortalTransaction): void { if (this.selected()?.id === settlement.id) { this.selected.set(null); return; } this.selected.set(settlement); this.detailLoading.set(true); this.detailError.set(null); this.api.getSettlement(settlement.id).subscribe({ next: (detail) => { this.selected.set(detail); this.detailLoading.set(false); }, error: (error) => { this.detailError.set(error?.error?.message ?? 'Settlement details are unavailable.'); this.detailLoading.set(false); } }); }
  frequencyLabel(value: string): string { return ({ SAME_DAY: 'Same day', T_PLUS_1: 'Next business day', SCHEDULED: 'Scheduled', MANUAL: 'Manual' } as Record<string, string>)[value] ?? value; }
  statusLabel(status: PortalTransaction['status']): string { return status.charAt(0) + status.slice(1).toLowerCase(); }
  private newIdempotencyKey(): string { return globalThis.crypto?.randomUUID?.() ?? `settlement-${Date.now()}-${Math.random().toString(16).slice(2)}`; }
}
