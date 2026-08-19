import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';
import { MerchantDashboardSummary, PortalTransaction } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <section class="merchant-page">
      <header class="page-heading">
        <div><p class="page-kicker">{{ greeting }}</p><h1>{{ session.session()?.businessName }}</h1><p>Here’s how your business is performing today.</p></div>
        <div class="heading-actions"><div class="account-chip"><span>Account</span><strong>{{ session.session()?.accountNumber }}</strong></div><button type="button" (click)="load()" [disabled]="loading()">↻ Refresh</button><small>Updated {{ lastUpdated() | date:'HH:mm:ss' }}</small></div>
      </header>

      @if (loading()) {
        <div class="summary-grid" aria-label="Loading dashboard">
          @for (item of [1,2,3,4]; track item) { <div class="summary-card skeleton"></div> }
        </div>
      } @else if (error()) {
        <div class="state-card"><strong>Dashboard unavailable</strong><p>{{ error() }}</p><button (click)="load()">Try again</button></div>
      } @else if (summary(); as data) {
        <div class="balance-banner">
          <div><span>Available balance</span><strong>{{ data.availableBalance | currency:data.currency:'symbol-narrow':'1.2-2' }}</strong><small>Ledger balance {{ data.ledgerBalance | currency:data.currency:'symbol-narrow':'1.2-2' }}</small></div>
          <div class="balance-decoration"></div>
          <a routerLink="/merchant/transactions">View transaction history →</a>
        </div>

        <div class="summary-grid">
          <article class="summary-card"><div class="card-icon card-icon--green">↗</div><span>Sales today</span><strong>{{ data.transactionValueToday | currency:data.currency:'symbol-narrow':'1.0-0' }}</strong><small>{{ data.transactionCountToday }} successful transactions</small></article>
          <article class="summary-card"><div class="card-icon card-icon--amber">◷</div><span>Pending settlement</span><strong>{{ data.lockedBalance | currency:data.currency:'symbol-narrow':'1.0-0' }}</strong><small>{{ data.pendingTransactionCount }} transaction{{ data.pendingTransactionCount === 1 ? '' : 's' }} processing today</small></article>
          <article class="summary-card"><div class="card-icon card-icon--blue">#</div><span>Settlement account</span><strong class="account-value">{{ data.settlementAccount }}</strong><small>MicroBiz MFB</small></article>
        </div>

        <section class="table-card">
          <div class="table-card__header"><div><h2>Recent transactions</h2><p>Your latest merchant account activity</p></div><a routerLink="/merchant/transactions">View all</a></div>
          @if (recent().length === 0) {
            <div class="empty-state"><strong>No transactions yet</strong><p>Your latest transactions will appear here.</p></div>
          } @else {
            <div class="responsive-table"><table><thead><tr><th>Transaction number</th><th>Type</th><th>Reference</th><th>Date</th><th>Status</th><th class="right">Amount</th></tr></thead><tbody>
              @for (transaction of recent(); track transaction.id) {
                <tr><td><strong>{{ transaction.transactionNo }}</strong></td><td>{{ typeLabel(transaction.type) }}</td><td>{{ transaction.reference || '—' }}</td><td>{{ transaction.transactionDate | date:'dd MMM, HH:mm' }}</td><td><span class="status" [class]="transaction.isReversed ? 'status status--reversed' : 'status status--' + transaction.status.toLowerCase()">{{ transaction.isReversed ? 'Reversed' : statusLabel(transaction.status) }}</span></td><td class="right amount">{{ transaction.amount | currency:transaction.currency:'symbol-narrow':'1.2-2' }}</td></tr>
              }
            </tbody></table></div>
          }
        </section>
      }
    </section>
  `,
  styles: [`
    .merchant-page { display: grid; gap: 1.4rem; }
    .page-heading { display: flex; justify-content: space-between; gap: 1rem; align-items: end; }
    .page-kicker { color: #a06e00 !important; font-weight: 800; font-size: .72rem !important; text-transform: uppercase; letter-spacing: .1em; }
    .page-heading h1 { margin: .28rem 0; font-size: clamp(1.5rem, 3vw, 2.15rem); letter-spacing: -.04em; color: #182250; }
    .page-heading p { margin: 0; color: #7a8195; font-size: .85rem; }
    .account-chip { background: #fff; border: 1px solid #e0e4ed; border-radius: .65rem; padding: .65rem .85rem; display: grid; gap: .15rem; min-width: 150px; }
    .account-chip span { color: #9298a9; font-size: .63rem; }
    .account-chip strong { color: #30394f; font-size: .78rem; letter-spacing: .06em; }
    .heading-actions { display: grid; grid-template-columns: 1fr auto; gap: .25rem .45rem; align-items: center; }.heading-actions button { border: 1px solid #dce0e9; border-radius: .5rem; background: #fff; color: #35415f; padding: .55rem; font-weight: 750; }.heading-actions small { grid-column: 1 / -1; text-align: right; color: #989eaf; font-size: .56rem; }
    .balance-banner { min-height: 180px; background: linear-gradient(120deg, #19235a, #273a83); color: #fff; border-radius: .9rem; padding: clamp(1.4rem, 4vw, 2.2rem); position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: flex-end; }
    .balance-banner > div:first-child { display: grid; position: relative; z-index: 1; }
    .balance-banner span { color: #bcc6e8; font-size: .75rem; margin-bottom: .6rem; }
    .balance-banner strong { font-size: clamp(2rem, 5vw, 3rem); letter-spacing: -.04em; }
    .balance-banner small { color: #aeb8db; margin-top: .55rem; }
    .balance-banner a { position: relative; z-index: 1; color: #ffdc7a; text-decoration: none; font-size: .76rem; font-weight: 750; }
    .balance-decoration { position: absolute; width: 310px; height: 310px; border-radius: 50%; border: 1px solid rgba(255,255,255,.12); right: 10%; top: -190px; box-shadow: 0 0 0 55px rgba(255,255,255,.025), 0 0 0 110px rgba(255,255,255,.02); }
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
    .summary-card { background: #fff; border: 1px solid #e1e5ed; border-radius: .75rem; padding: 1.1rem; display: grid; grid-template-columns: auto 1fr; column-gap: .75rem; min-height: 125px; }
    .summary-card > span { color: #7d8497; font-size: .7rem; align-self: center; }
    .summary-card > strong { grid-column: 2; font-size: 1.3rem; color: #26304d; letter-spacing: -.025em; }
    .summary-card > small { grid-column: 2; color: #969bad; font-size: .65rem; margin-top: .25rem; }
    .card-icon { grid-row: 1 / 4; width: 2.2rem; height: 2.2rem; border-radius: .6rem; display: grid; place-items: center; font-weight: 800; }
    .card-icon--green { background: #def4e8; color: #23754e; }.card-icon--amber { background: #fff2ce; color: #996900; }.card-icon--blue { background: #e6ebff; color: #304b9c; }
    .account-value { font-size: 1.08rem !important; letter-spacing: .06em !important; }
    .table-card { background: #fff; border: 1px solid #e1e5ed; border-radius: .8rem; overflow: hidden; }
    .table-card__header { padding: 1.15rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eceef3; }
    .table-card__header h2 { font-size: 1rem; margin: 0 0 .25rem; }.table-card__header p { margin: 0; color: #8a90a2; font-size: .68rem; }.table-card__header a { color: #2f4798; font-size: .72rem; font-weight: 750; text-decoration: none; }
    .responsive-table { overflow-x: auto; } table { min-width: 720px; } th { font-size: .62rem; background: #fafbfc; } td { font-size: .74rem; color: #62697d; } td strong { color: #313a52; font-size: .7rem; }.right { text-align: right; }.amount { color: #27314d; font-weight: 750; }
    .status { display: inline-block; padding: .24rem .48rem; border-radius: 1rem; font-size: .57rem; font-weight: 800; }.status--successful { color: #23754e; background: #e3f5eb; }.status--pending, .status--initiated { color: #936400; background: #fff2ce; }.status--failed, .status--reversed { color: #aa4232; background: #fde9e5; }
    .skeleton { min-height: 130px; background: linear-gradient(90deg, #eef0f4 25%, #f7f8fa 50%, #eef0f4 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; }.summary-grid .skeleton:last-child { display: none; } @keyframes shimmer { to { background-position: -200% 0; } }
    .state-card, .empty-state { background: #fff; border: 1px solid #e1e5ed; border-radius: .75rem; padding: 2rem; text-align: center; }.state-card p, .empty-state p { color: #7c8397; font-size: .8rem; }.state-card button { border: 0; background: #1e2761; color: #fff; padding: .6rem 1rem; border-radius: .5rem; }
    @media (max-width: 900px) { .summary-grid { grid-template-columns: 1fr 1fr; }.summary-card:last-child { grid-column: 1 / -1; } }
    @media (max-width: 600px) { .page-heading { align-items: start; }.heading-actions { display: none; }.summary-grid { grid-template-columns: 1fr; }.summary-card:last-child { grid-column: auto; }.balance-banner { align-items: start; flex-direction: column; }.balance-banner a { margin-top: 1rem; } }
  `],
})
export class MerchantDashboardComponent implements OnInit {
  summary = signal<MerchantDashboardSummary | null>(null);
  recent = signal<PortalTransaction[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  lastUpdated = signal(new Date());

  constructor(
    private readonly api: MerchantPortalApiService,
    public readonly session: MerchantPortalSessionService,
  ) {}

  get greeting(): string {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
  }

  ngOnInit(): void { this.load(); }

  load(): void {
    this.loading.set(true);
    this.error.set(null);
    this.api.getDashboard().subscribe({
      next: ({ summary, recentTransactions }) => { this.summary.set(summary); this.recent.set(recentTransactions); this.lastUpdated.set(new Date()); this.loading.set(false); },
      error: () => { this.error.set('We could not load your account summary.'); this.loading.set(false); },
    });
  }

  typeLabel(type: PortalTransaction['type']): string {
    return ({ QR_COLLECTION: 'QR collection', POS_COLLECTION: 'POS collection', SETTLEMENT: 'Settlement', REVERSAL: 'Reversal', ADJUSTMENT: 'Adjustment' })[type];
  }

  statusLabel(status: PortalTransaction['status']): string {
    return status.charAt(0) + status.slice(1).toLowerCase();
  }
}
