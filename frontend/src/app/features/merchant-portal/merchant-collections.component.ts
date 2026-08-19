import { CommonModule } from '@angular/common';
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantMoneyOperationResult } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-collections',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <section class="collection-page">
      <header><p>Payment collection</p><h1>Accept a payment</h1><span>Record a QR or POS collection securely.</span></header>

      @if (result(); as receipt) {
        <section class="receipt-card">
          <div class="success-mark">✓</div>
          <p class="eyebrow">Payment successful</p>
          <h2>{{ receipt.transaction.amount | currency:receipt.transaction.currency:'symbol-narrow':'1.2-2' }}</h2>
          <span class="receipt-subtitle">The collection has been posted to your merchant ledger.</span>
          <dl>
            <div><dt>Transaction number</dt><dd>{{ receipt.transaction.transactionNo }}</dd></div>
            <div><dt>Payment method</dt><dd>{{ methodLabel(receipt.transaction.type) }}</dd></div>
            <div><dt>Reference</dt><dd>{{ receipt.transaction.reference || 'Not supplied' }}</dd></div>
            <div><dt>Status</dt><dd><span class="success-status">Successful</span></dd></div>
            <div><dt>Date and time</dt><dd>{{ receipt.transaction.transactionDate | date:'medium' }}</dd></div>
            <div><dt>Pending settlement</dt><dd>{{ receipt.balance.lockedBalance | currency:receipt.balance.currency:'symbol-narrow':'1.2-2' }}</dd></div>
          </dl>
          <div class="receipt-actions"><button class="secondary" type="button" (click)="printReceipt()">Print receipt</button><button type="button" (click)="startAnother()">Accept another payment</button></div>
        </section>
      } @else {
        <div class="collection-layout">
          <section class="form-card">
            <div class="section-heading"><div><h2>Payment details</h2><p>Select how the customer is paying.</p></div><span>NGN</span></div>

            <div class="method-switch" role="group" aria-label="Collection method">
              <button type="button" [class.active]="method === 'qr'" (click)="selectMethod('qr')"><b>▦</b><span><strong>QR payment</strong><small>Collect through a QR channel</small></span></button>
              <button type="button" [class.active]="method === 'pos'" (click)="selectMethod('pos')"><b>▣</b><span><strong>POS payment</strong><small>Record a terminal collection</small></span></button>
            </div>

            <form (ngSubmit)="review()" #collectionForm="ngForm">
              <label class="amount-field">Amount
                <div><span>₦</span><input type="number" name="amount" [(ngModel)]="amount" required min="1" step="0.01" placeholder="0.00" /></div>
              </label>
              <label>Reference <small>Optional</small><input name="reference" [(ngModel)]="reference" maxlength="255" placeholder="Order or invoice reference" /></label>
              <label>Narration <small>Optional</small><textarea name="narration" [(ngModel)]="narration" maxlength="500" rows="3" placeholder="What is this payment for?"></textarea></label>
              @if (error()) { <div class="alert" role="alert">{{ error() }}</div> }
              <button class="primary" type="submit" [disabled]="collectionForm.invalid">Review payment</button>
            </form>
          </section>

          <aside class="guide-card">
            <div class="guide-icon">{{ method === 'qr' ? '▦' : '▣' }}</div>
            <h2>{{ method === 'qr' ? 'QR collection' : 'POS collection' }}</h2>
            <p>{{ method === 'qr' ? 'Use this when a payment has been initiated through the merchant QR channel.' : 'Use this to record a payment received through an assigned POS terminal.' }}</p>
            <ul><li>Confirm the amount with the customer</li><li>Use a unique order reference where available</li><li>Never refresh while a payment is processing</li></ul>
          </aside>
        </div>
      }

      @if (reviewing()) {
        <div class="modal-backdrop" role="presentation" (click)="cancelReview()">
          <section class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" (click)="$event.stopPropagation()">
            <div class="confirm-icon">!</div><p class="eyebrow">Confirm collection</p><h2 id="confirm-title">Collect {{ amount || 0 | currency:'NGN':'symbol-narrow':'1.2-2' }}?</h2>
            <p>This will record a {{ method === 'qr' ? 'QR' : 'POS' }} collection and update your merchant balance.</p>
            <div class="confirm-summary"><span>Reference</span><strong>{{ reference || 'Not supplied' }}</strong><span>Payment method</span><strong>{{ method === 'qr' ? 'QR collection' : 'POS collection' }}</strong></div>
            @if (error()) { <div class="alert" role="alert">{{ error() }}</div> }
            <div class="modal-actions"><button class="secondary" type="button" (click)="cancelReview()" [disabled]="submitting()">Go back</button><button type="button" (click)="confirmCollection()" [disabled]="submitting()">{{ submitting() ? 'Processing payment…' : 'Confirm payment' }}</button></div>
          </section>
        </div>
      }
    </section>
  `,
  styles: [`
    .collection-page { display: grid; gap: 1.35rem; max-width: 1050px; } header p, .eyebrow { text-transform: uppercase; color: #a06e00; letter-spacing: .11em; font-size: .65rem; font-weight: 800; margin: 0; } header h1 { color: #182250; font-size: 2rem; letter-spacing: -.04em; margin: .25rem 0; } header span { color: #7e8497; font-size: .8rem; }
    .collection-layout { display: grid; grid-template-columns: 1.35fr .65fr; gap: 1.2rem; align-items: start; }.form-card, .guide-card, .receipt-card { background: #fff; border: 1px solid #e0e4ed; border-radius: .85rem; padding: clamp(1.2rem, 3vw, 1.7rem); }.section-heading { display: flex; justify-content: space-between; gap: 1rem; }.section-heading h2, .guide-card h2 { margin: 0 0 .25rem; color: #2d3650; font-size: 1rem; }.section-heading p, .guide-card p { margin: 0; color: #8a90a1; font-size: .7rem; line-height: 1.6; }.section-heading > span { color: #1e2761; background: #edf0fb; padding: .3rem .5rem; border-radius: 1rem; font-size: .62rem; font-weight: 800; }
    .method-switch { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin: 1.2rem 0; }.method-switch button { display: flex; gap: .65rem; text-align: left; border: 1px solid #dde1ea; background: #fff; border-radius: .65rem; padding: .8rem; color: #51596e; }.method-switch button.active { border-color: #2c4290; background: #f6f7fc; box-shadow: 0 0 0 2px rgba(44,66,144,.08); }.method-switch b { width: 2rem; height: 2rem; display: grid; place-items: center; border-radius: .5rem; background: #edf0fb; color: #2c4498; }.method-switch span { display: grid; gap: .2rem; }.method-switch strong { font-size: .72rem; }.method-switch small { color: #9298a9; font-size: .6rem; }
    form { display: grid; gap: 1rem; }label { color: #555d72; font-size: .7rem; font-weight: 750; }label > small { color: #9a9faf; font-weight: 500; margin-left: .25rem; }input, textarea { display: block; width: 100%; border: 1px solid #d9dde7; border-radius: .55rem; padding: .75rem; margin-top: .4rem; color: #323b52; font: inherit; outline: 0; }input:focus, textarea:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.1); }.amount-field > div { display: flex; align-items: center; border: 1px solid #d9dde7; border-radius: .6rem; margin-top: .4rem; overflow: hidden; }.amount-field > div:focus-within { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.1); }.amount-field div span { color: #1e2761; font-size: 1.3rem; font-weight: 800; padding-left: .85rem; }.amount-field input { border: 0; box-shadow: none; margin: 0; font-size: 1.2rem; font-weight: 750; }.primary, .receipt-actions button, .modal-actions button { border: 0; border-radius: .55rem; background: #1e2761; color: white; padding: .78rem 1rem; font-weight: 750; }.primary:disabled, button:disabled { opacity: .55; }
    .guide-card { background: linear-gradient(145deg, #172052, #283a83); color: white; }.guide-card h2 { color: white; }.guide-card p { color: #c0c8e5; }.guide-icon { width: 3rem; height: 3rem; display: grid; place-items: center; border-radius: .7rem; background: #ffcb45; color: #172052; font-size: 1.25rem; margin-bottom: 1rem; }.guide-card ul { padding-left: 1rem; color: #cbd2e8; font-size: .68rem; line-height: 1.8; }
    .modal-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 1rem; background: rgba(12,18,48,.58); }.confirm-modal { width: min(100%, 470px); background: white; border-radius: .9rem; padding: 1.5rem; box-shadow: 0 25px 70px rgba(8,14,42,.3); }.confirm-icon { width: 2.8rem; height: 2.8rem; display: grid; place-items: center; border-radius: 50%; background: #fff2ce; color: #966600; font-weight: 850; margin-bottom: 1rem; }.confirm-modal h2 { color: #25304d; margin: .45rem 0; }.confirm-modal > p:not(.eyebrow) { color: #7a8195; font-size: .75rem; line-height: 1.6; }.confirm-summary { display: grid; grid-template-columns: 1fr auto; gap: .65rem; background: #f6f7fa; border-radius: .6rem; padding: .85rem; margin: 1rem 0; }.confirm-summary span { color: #8b91a2; font-size: .65rem; }.confirm-summary strong { color: #3b445b; font-size: .68rem; }.modal-actions, .receipt-actions { display: flex; justify-content: flex-end; gap: .65rem; }.secondary { background: #fff !important; color: #424b63 !important; border: 1px solid #d8dce6 !important; }.alert { padding: .7rem; border-radius: .5rem; background: #fff0ee; color: #a43b2b; font-size: .7rem; }
    .receipt-card { width: min(100%, 650px); text-align: center; margin: 0 auto; }.success-mark { width: 4rem; height: 4rem; display: grid; place-items: center; border-radius: 50%; background: #def4e8; color: #23754e; font-size: 1.5rem; margin: .5rem auto 1rem; }.receipt-card h2 { color: #182250; font-size: 2.4rem; margin: .5rem 0; }.receipt-subtitle { color: #7d8497; font-size: .75rem; }.receipt-card dl { display: grid; grid-template-columns: 1fr 1fr; text-align: left; margin: 1.5rem 0; border: 1px solid #e4e7ee; border-radius: .65rem; overflow: hidden; }.receipt-card dl div { padding: .8rem; border-bottom: 1px solid #eceef3; }.receipt-card dl div:nth-child(odd) { border-right: 1px solid #eceef3; }.receipt-card dt { color: #9298a8; font-size: .6rem; }.receipt-card dd { margin: .25rem 0 0; color: #354057; font-size: .72rem; font-weight: 750; }.success-status { color: #23754e; }.receipt-actions { justify-content: center; }
    @media (max-width: 760px) { .collection-layout { grid-template-columns: 1fr; }.guide-card { order: -1; }.method-switch { grid-template-columns: 1fr; } }
    @media (max-width: 520px) { .receipt-card dl { grid-template-columns: 1fr; }.receipt-card dl div:nth-child(odd) { border-right: 0; }.receipt-actions, .modal-actions { flex-direction: column-reverse; }.receipt-actions button, .modal-actions button { width: 100%; } }
  `],
})
export class MerchantCollectionsComponent {
  method: 'qr' | 'pos' = 'qr';
  amount: number | null = null;
  reference = '';
  narration = '';
  reviewing = signal(false);
  submitting = signal(false);
  error = signal<string | null>(null);
  result = signal<MerchantMoneyOperationResult | null>(null);
  private idempotencyKey: string | null = null;

  constructor(private readonly api: MerchantPortalApiService) {}

  selectMethod(method: 'qr' | 'pos'): void { this.method = method; this.error.set(null); }
  review(): void { if (!this.amount || this.amount <= 0) return; this.idempotencyKey = this.newIdempotencyKey(); this.error.set(null); this.reviewing.set(true); }
  cancelReview(): void { if (!this.submitting()) { this.reviewing.set(false); this.idempotencyKey = null; this.error.set(null); } }

  confirmCollection(): void {
    if (!this.amount || !this.idempotencyKey) return;
    this.submitting.set(true); this.error.set(null);
    this.api.collectPayment(this.method, {
      amount: this.amount.toFixed(2),
      idempotency_key: this.idempotencyKey,
      reference: this.reference.trim() || undefined,
      narration: this.narration.trim() || undefined,
    }).subscribe({
      next: (result) => { this.result.set(result); this.reviewing.set(false); this.submitting.set(false); },
      error: (error) => { this.error.set(error?.error?.message ?? error?.message ?? 'The payment could not be completed.'); this.submitting.set(false); },
    });
  }

  startAnother(): void { this.amount = null; this.reference = ''; this.narration = ''; this.idempotencyKey = null; this.error.set(null); this.result.set(null); }
  printReceipt(): void { window.print(); }
  methodLabel(type: MerchantMoneyOperationResult['transaction']['type']): string { return type === 'QR_COLLECTION' ? 'QR collection' : type === 'POS_COLLECTION' ? 'POS collection' : 'Collection'; }
  private newIdempotencyKey(): string { return globalThis.crypto?.randomUUID?.() ?? `collection-${Date.now()}-${Math.random().toString(16).slice(2)}`; }
}
