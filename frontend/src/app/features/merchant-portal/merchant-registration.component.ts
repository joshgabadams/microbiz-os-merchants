import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { map, switchMap } from 'rxjs';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';
import {
  ConfirmMerchantRegistrationPayload,
  MerchantAccountPreview,
  MerchantEligibleAccount,
  MerchantRegistrationConfirmation,
} from '../../core/models/merchant-portal.models';

type RegistrationForm = Omit<ConfirmMerchantRegistrationPayload, 'branch_id'> & {
  branch_id: number | null;
};

@Component({
  selector: 'app-merchant-registration',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <main class="registration-page">
      <header>
        <a routerLink="/login/merchants" class="brand"><span>M</span> MicroBiz</a>
        <span class="step">Step 6 of 6</span>
      </header>

      <section class="registration-wrap">
        <div class="registration-copy">
          <p class="eyebrow">Complete merchant registration</p>
          <h1>One final step for your business.</h1>
          <p>We have verified the MicroBiz account and linked phone number. Confirm the remaining business information to create your merchant workspace.</p>
          <div class="progress-list">
            <div class="complete"><span>✓</span><p><strong>Account located</strong><small>Existing MicroBiz account confirmed</small></p></div>
            <div class="complete"><span>✓</span><p><strong>Phone verified</strong><small>OTP verification completed</small></p></div>
            <div class="current"><span>3</span><p><strong>Business details</strong><small>Create the merchant profile</small></p></div>
          </div>
        </div>

        <div class="registration-card">
          @if (result(); as registration) {
            <div class="success-state">
              <div class="success-icon">✓</div>
              <p class="eyebrow">Registration complete</p>
              <h2>Your merchant workspace is ready.</h2>
              <p><strong>{{ registration.business_name }}</strong> has been registered as <strong>{{ registration.merchant_code }}</strong>.</p>
              <button type="button" (click)="continueToDashboard()">Continue to dashboard</button>
            </div>
          } @else if (preview(); as account) {
            <div class="account-summary">
              <span>{{ account.display_name?.charAt(0) || 'M' }}</span>
              <div><small>Verified merchant account</small><strong>{{ account.display_name }}</strong><em>{{ selected()?.product_name }} · {{ selected()?.account_no }}</em></div>
              <b>{{ account.status }}</b>
            </div>

            <h2>Confirm your business details</h2>
            <p class="subtitle">Verified account details are locked. Complete the remaining required information.</p>

            <form (ngSubmit)="submit()" #registrationForm="ngForm">
              <div class="field-grid">
                <label class="wide">Legal business name
                  <input name="legal_name" [(ngModel)]="form.legal_name" required maxlength="255" />
                </label>
                <label>Trading name <small>Optional</small>
                  <input name="trading_name" [(ngModel)]="form.trading_name" maxlength="255" />
                </label>
                <label>Business type <small>Optional</small>
                  <select name="business_type" [(ngModel)]="form.business_type">
                    <option value="">Select business type</option>
                    <option value="SOLE_PROPRIETORSHIP">Sole proprietorship</option>
                    <option value="PARTNERSHIP">Partnership</option>
                    <option value="LIMITED_COMPANY">Limited company</option>
                    <option value="OTHER">Other</option>
                  </select>
                </label>
                <label>Registration number
                  <input name="registration_number" [(ngModel)]="form.registration_number" required maxlength="255" placeholder="RC or business registration number" />
                </label>
                <label>Primary contact
                  <input name="contact_name" [(ngModel)]="form.contact_name" required maxlength="255" />
                </label>
                <label>Phone number
                  <input name="phone" [(ngModel)]="form.phone" required maxlength="20" readonly />
                </label>
                <label>Email address <small>Optional</small>
                  <input type="email" name="email" [(ngModel)]="form.email" email maxlength="255" />
                </label>
                <label class="wide">MicroBiz branch ID
                  <input type="number" name="branch_id" [(ngModel)]="form.branch_id" required min="1" placeholder="Temporary until branch is derived from Fincore" />
                  <small class="field-hint">The backend currently requires a local branch ID. This will be replaced when branch mapping is finalized.</small>
                </label>
              </div>

              <label class="terms"><input type="checkbox" name="terms" [(ngModel)]="acceptedTerms" required /> I confirm that these business details are correct and agree to the MicroBiz merchant terms.</label>
              @if (error()) { <div class="form-error" role="alert">{{ error() }}</div> }
              <button class="submit-button" type="submit" [disabled]="registrationForm.invalid || loading()">
                {{ loading() ? 'Creating merchant workspace…' : 'Complete registration' }}
              </button>
            </form>
          }
        </div>
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .registration-page { min-height: 100vh; background: #f5f7fb; }
    header { height: 74px; padding: 0 clamp(1.25rem, 5vw, 5rem); display: flex; align-items: center; justify-content: space-between; background: #fff; border-bottom: 1px solid #e5e8f0; }
    .brand { display: flex; align-items: center; gap: .6rem; font-weight: 800; color: #1e2761; text-decoration: none; }.brand span { width: 2rem; height: 2rem; display: grid; place-items: center; background: #ffcb45; border-radius: .6rem; }.step { color: #8a91a4; font-size: .72rem; }
    .registration-wrap { width: min(1180px, calc(100% - 2.5rem)); margin: 0 auto; padding: 3.5rem 0; display: grid; grid-template-columns: .75fr 1.25fr; gap: clamp(2rem, 7vw, 6rem); align-items: start; }.registration-copy { padding-top: 2rem; }.eyebrow { color: #9a6a00; text-transform: uppercase; letter-spacing: .13em; font-weight: 800; font-size: .68rem; }.registration-copy h1 { color: #172152; font-size: clamp(2.2rem, 4vw, 3.8rem); line-height: 1.06; letter-spacing: -.05em; margin: .8rem 0 1.2rem; }.registration-copy > p:not(.eyebrow) { color: #6e758b; line-height: 1.7; font-size: .9rem; }
    .progress-list { display: grid; gap: 1rem; margin-top: 2rem; }.progress-list > div { display: flex; gap: .75rem; align-items: center; }.progress-list > div > span { width: 1.9rem; height: 1.9rem; border-radius: 50%; display: grid; place-items: center; font-size: .68rem; font-weight: 850; }.progress-list .complete > span { background: #def4e8; color: #23754e; }.progress-list .current > span { background: #1e2761; color: white; }.progress-list p { display: grid; gap: .15rem; margin: 0; }.progress-list strong { color: #3b435a; font-size: .77rem; }.progress-list small { color: #9197a8; font-size: .65rem; }
    .registration-card { background: #fff; padding: clamp(1.4rem, 4vw, 2.4rem); border: 1px solid #e1e5ef; border-radius: 1rem; box-shadow: 0 18px 50px rgba(30,39,97,.07); }.account-summary { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; padding: .85rem; background: #f7f8fc; border: 1px solid #e5e8f0; border-radius: .7rem; margin-bottom: 1.5rem; }.account-summary > span { width: 2.7rem; height: 2.7rem; border-radius: .65rem; display: grid; place-items: center; color: white; background: #1e2761; font-weight: 850; }.account-summary div { display: grid; gap: .12rem; }.account-summary small { color: #9298a9; font-size: .58rem; }.account-summary strong { color: #343d55; font-size: .78rem; }.account-summary em { color: #737a8f; font-size: .65rem; font-style: normal; }.account-summary b { color: #24774f; background: #e2f4e9; border-radius: 1rem; padding: .28rem .5rem; font-size: .57rem; }
    h2 { margin: 0 0 .4rem; font-size: 1.4rem; letter-spacing: -.025em; color: #283149; }.subtitle { color: #7b8297; font-size: .77rem; margin: 0 0 1.5rem; }.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }label { font-size: .7rem; color: #4b5369; font-weight: 750; }label > small { color: #9a9fb0; font-weight: 500; margin-left: .25rem; }label.wide { grid-column: 1 / -1; }input, select { display: block; width: 100%; margin-top: .4rem; padding: .76rem .8rem; border: 1px solid #d8dce8; border-radius: .55rem; outline: 0; background: white; color: #303950; font-size: .78rem; }input:focus, select:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.1); }input[readonly] { background: #f5f6f8; color: #777e91; }.field-hint { display: block; margin: .4rem 0 0 !important; color: #9a6a00 !important; font-size: .61rem; }
    .terms { display: flex; gap: .55rem; align-items: flex-start; margin: 1.2rem 0; font-weight: 500; line-height: 1.5; color: #656c80; }.terms input { width: auto; margin: .15rem 0 0; }.submit-button, .success-state button { width: 100%; border: 0; border-radius: .6rem; padding: .85rem; background: #1e2761; color: #fff; font-weight: 750; }.submit-button:disabled { opacity: .55; cursor: not-allowed; }.form-error { background: #fff0ee; color: #a43b2b; padding: .75rem; border-radius: .55rem; margin-bottom: 1rem; font-size: .73rem; }
    .success-state { text-align: center; padding: 2.5rem 1rem; }.success-icon { width: 4rem; height: 4rem; display: grid; place-items: center; margin: 0 auto 1.4rem; background: #def4e8; color: #20754c; border-radius: 50%; font-size: 1.5rem; }.success-state h2 { margin: .55rem 0; }.success-state p:not(.eyebrow) { color: #6e758b; line-height: 1.65; margin-bottom: 1.5rem; font-size: .82rem; }
    @media (max-width: 800px) { .registration-wrap { grid-template-columns: 1fr; padding: 2rem 0; gap: 1.5rem; }.registration-copy { padding: 0; }.progress-list { display: none; } }
    @media (max-width: 540px) { .field-grid { grid-template-columns: 1fr; }label.wide { grid-column: auto; }.account-summary { grid-template-columns: auto 1fr; }.account-summary b { grid-column: 2; width: fit-content; } }
  `],
})
export class MerchantRegistrationComponent implements OnInit {
  preview = signal<MerchantAccountPreview | null>(null);
  selected = signal<MerchantEligibleAccount | null>(null);
  result = signal<MerchantRegistrationConfirmation | null>(null);
  form: RegistrationForm = {
    fincore_client_id: 0,
    fincore_account_id: 0,
    legal_name: '',
    trading_name: '',
    business_type: '',
    registration_number: '',
    contact_name: '',
    phone: '',
    email: '',
    branch_id: null,
  };
  acceptedTerms = false;
  loading = signal(false);
  error = signal<string | null>(null);

  constructor(
    private readonly api: MerchantPortalApiService,
    private readonly session: MerchantPortalSessionService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    const preview = this.session.accountPreview();
    const selected = this.session.selectedAccount();
    if (!preview || !selected || this.session.onboardingStep() !== 'registration') {
      void this.router.navigate(['/login/merchants']);
      return;
    }

    this.preview.set(preview);
    this.selected.set(selected);
    this.form = {
      fincore_client_id: preview.fincore_client_id,
      fincore_account_id: selected.fincore_account_id,
      legal_name: preview.display_name ?? '',
      trading_name: preview.display_name ?? '',
      business_type: '',
      registration_number: '',
      contact_name: preview.display_name ?? '',
      phone: preview.mobile_no ?? '',
      email: preview.email ?? '',
      branch_id: null,
    };
  }

  submit(): void {
    const preview = this.preview();
    if (!preview || !this.form.branch_id) return;

    const payload: ConfirmMerchantRegistrationPayload = {
      ...this.form,
      branch_id: this.form.branch_id,
      trading_name: this.form.trading_name || undefined,
      business_type: this.form.business_type || undefined,
      email: this.form.email || undefined,
      verification_token: this.session.otpVerification()?.verification_token,
    };

    this.loading.set(true);
    this.error.set(null);
    const loginEmail = this.session.loginDraft()?.email ?? this.form.email ?? '';
    this.api.confirmRegistration(payload, preview, this.selected() ?? undefined).pipe(
      switchMap((registration) => this.api.createMerchantSession(
        registration,
        loginEmail,
        this.session.otpVerification()?.verification_token,
      ).pipe(map((merchantSession) => ({ registration, merchantSession })))),
    ).subscribe({
      next: ({ registration, merchantSession }) => {
        this.session.startSession(merchantSession);
        this.result.set(registration);
        this.loading.set(false);
      },
      error: (error) => {
        this.error.set(error?.error?.message ?? error?.message ?? 'Registration could not be completed.');
        this.loading.set(false);
      },
    });
  }

  continueToDashboard(): void {
    void this.router.navigate(['/merchant/dashboard']);
  }
}
