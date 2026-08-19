import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalProfile } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-profile',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="profile-page">
      <header><p>Account settings</p><h1>Business profile</h1><span>Review your merchant details and update supported contact information.</span></header>
      @if (loading()) { <div class="profile-card loading">Loading your business profile…</div> }
      @else if (error() && !profile()) { <div class="profile-card state"><strong>Profile unavailable</strong><p>{{ error() }}</p><button (click)="load()">Try again</button></div> }
      @else if (profile(); as merchant) {
        <div class="profile-summary"><div class="business-mark">{{ merchant.businessName.charAt(0) }}</div><div><h2>{{ merchant.businessName }}</h2><p>{{ merchant.merchantCode }} · {{ merchant.accountNumber }}</p></div><span>{{ merchant.status }}</span></div>
        <form class="profile-card" (ngSubmit)="save()" #profileForm="ngForm">
          <div class="section-heading"><div><h2>Business information</h2><p>Registration details are verified and cannot be edited here.</p></div><span class="verified">✓ Verified</span></div>
          <div class="field-grid">
            <label>Registered business name<input [value]="merchant.businessName" disabled /></label>
            <label>Registration number<input [value]="merchant.registrationNumber" disabled /></label>
            <label>Merchant code<input [value]="merchant.merchantCode" disabled /></label>
            <label>MicroBiz account number<input [value]="merchant.accountNumber" disabled /></label>
          </div>
          <hr />
          <div class="section-heading"><div><h2>Contact information</h2><p>We’ll use these details for important account communication.</p></div></div>
          <div class="field-grid">
            <label>Primary contact<input name="contactName" [(ngModel)]="form.contactName" required /></label>
            <label>Email address<input type="email" name="email" [(ngModel)]="form.email" required email /></label>
            <label>Phone number<input type="tel" name="phone" [(ngModel)]="form.phone" required minlength="10" /></label>
          </div>
          <hr />
          <div class="section-heading"><div><h2>Settlement account</h2><p>Contact merchant support to change your settlement account.</p></div></div>
          <div class="settlement-box"><div><span>Bank</span><strong>{{ merchant.settlementBank }}</strong></div><div><span>Account name</span><strong>{{ merchant.settlementAccountName }}</strong></div><div><span>Account number</span><strong>{{ merchant.settlementAccountNumber }}</strong></div></div>
          @if (success()) { <div class="message success">✓ Your contact information has been updated.</div> }
          @if (error()) { <div class="message error">{{ error() }}</div> }
          <div class="form-actions"><button type="button" class="secondary" (click)="reset()">Cancel</button><button type="submit" [disabled]="profileForm.invalid || saving()">{{ saving() ? 'Saving changes…' : 'Save changes' }}</button></div>
        </form>
      }
    </section>
  `,
  styles: [`
    .profile-page { display: grid; gap: 1.25rem; max-width: 980px; } header p { text-transform: uppercase; color: #a06e00; letter-spacing: .11em; font-size: .65rem; font-weight: 800; margin: 0; } header h1 { color: #182250; font-size: 2rem; letter-spacing: -.04em; margin: .25rem 0; } header span { color: #7e8497; font-size: .8rem; }
    .profile-summary { background: linear-gradient(110deg, #19235a, #273a83); color: white; border-radius: .8rem; padding: 1.2rem 1.4rem; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .9rem; }.business-mark { width: 3.2rem; height: 3.2rem; display: grid; place-items: center; background: #ffcb45; color: #19235a; border-radius: .75rem; font-weight: 850; font-size: 1.2rem; }.profile-summary h2 { margin: 0 0 .25rem; font-size: 1.05rem; }.profile-summary p { margin: 0; color: #b9c3e6; font-size: .7rem; }.profile-summary > span { padding: .3rem .55rem; background: rgba(91,211,145,.16); color: #93efba; border-radius: 1rem; font-size: .58rem; font-weight: 800; }
    .profile-card { background: #fff; border: 1px solid #e0e4ed; border-radius: .8rem; padding: clamp(1.1rem, 3vw, 1.6rem); }.section-heading { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }.section-heading h2 { color: #2d3650; margin: 0 0 .25rem; font-size: .95rem; }.section-heading p { margin: 0; color: #8a90a1; font-size: .68rem; }.verified { color: #247750; background: #e4f5eb; height: fit-content; padding: .3rem .55rem; border-radius: 1rem; font-size: .61rem; font-weight: 800; }
    .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }.field-grid label { color: #6e7588; font-size: .67rem; font-weight: 750; }.field-grid input { display: block; width: 100%; margin-top: .4rem; padding: .7rem .75rem; border: 1px solid #d9dde7; border-radius: .5rem; color: #333c53; font-size: .75rem; outline: 0; }.field-grid input:focus { border-color: #4057ad; }.field-grid input:disabled { background: #f6f7f9; color: #82889a; }
    hr { border: 0; border-top: 1px solid #eaecf1; margin: 1.6rem 0; }.settlement-box { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; background: #f7f8fb; border-radius: .6rem; padding: 1rem; }.settlement-box div { display: grid; gap: .3rem; }.settlement-box span { color: #9298a9; font-size: .61rem; }.settlement-box strong { color: #3b445b; font-size: .73rem; }
    .form-actions { display: flex; justify-content: flex-end; gap: .65rem; margin-top: 1.25rem; }.form-actions button, .state button { border: 0; border-radius: .5rem; background: #1e2761; color: #fff; padding: .65rem .9rem; font-size: .7rem; font-weight: 750; }.form-actions .secondary { background: #fff; color: #485168; border: 1px solid #d9dde7; }.form-actions button:disabled { opacity: .55; }.message { padding: .7rem .8rem; border-radius: .5rem; font-size: .72rem; margin-top: 1rem; }.success { background: #e5f6ec; color: #25764f; }.error { background: #fff0ee; color: #a43b2b; }.loading, .state { min-height: 230px; display: grid; place-content: center; text-align: center; color: #7e8497; font-size: .8rem; }.state p { color: #858b9d; }
    @media (max-width: 620px) { .field-grid, .settlement-box { grid-template-columns: 1fr; }.profile-summary { grid-template-columns: auto 1fr; }.profile-summary > span { grid-column: 2; width: fit-content; }.form-actions button { flex: 1; } }
  `],
})
export class MerchantProfileComponent implements OnInit {
  profile = signal<MerchantPortalProfile | null>(null);
  form = { contactName: '', email: '', phone: '' };
  loading = signal(true); saving = signal(false); error = signal<string | null>(null); success = signal(false);
  constructor(private readonly api: MerchantPortalApiService) {}
  ngOnInit(): void { this.load(); }
  load(): void { this.loading.set(true); this.error.set(null); this.api.getProfile().subscribe({ next: (profile) => { this.profile.set(profile); this.copyForm(profile); this.loading.set(false); }, error: () => { this.error.set('We could not load your business profile.'); this.loading.set(false); } }); }
  save(): void { this.saving.set(true); this.error.set(null); this.success.set(false); this.api.updateProfile(this.form).subscribe({ next: (profile) => { this.profile.set(profile); this.copyForm(profile); this.saving.set(false); this.success.set(true); }, error: () => { this.error.set('Your changes could not be saved.'); this.saving.set(false); } }); }
  reset(): void { const profile = this.profile(); if (profile) { this.copyForm(profile); } this.error.set(null); this.success.set(false); }
  private copyForm(profile: MerchantPortalProfile): void { this.form = { contactName: profile.contactName, email: profile.email, phone: profile.phone }; }
}
