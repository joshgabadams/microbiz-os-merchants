import { Component, OnInit, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';

@Component({
  selector: 'app-account-verification',
  standalone: true,
  imports: [RouterLink],
  template: `
    <main class="verification-page">
      <a class="brand" routerLink="/login/merchants"><span>M</span> MicroBiz</a>
      @if (session.accountPreview(); as account) {
        <section class="verification-card">
          <div class="step">Step 3 of 4</div>
          <div class="success-mark">✓</div>
          <p class="eyebrow">Account found</p>
          <h1>Confirm your information</h1>
          <p class="subtitle">Make sure these details match your existing MicroBiz account before we send a verification code.</p>

          <div class="business-name">
            <span>{{ account.display_name?.charAt(0) || 'M' }}</span>
            <div><small>Account name</small><strong>{{ account.display_name || 'Not available' }}</strong></div>
            <em [class.inactive]="!account.active">{{ account.status || (account.active ? 'Active' : 'Inactive') }}</em>
          </div>

          <dl>
            <div><dt>Account number</dt><dd>{{ account.account_no }}</dd></div>
            <div><dt>Phone number</dt><dd>{{ maskedPhone }}</dd></div>
            <div><dt>Email address</dt><dd>{{ maskedEmail }}</dd></div>
            <div><dt>Home branch</dt><dd>{{ account.office_name || 'Not available' }}</dd></div>
            <div><dt>Customer type</dt><dd>{{ account.legal_form || 'Business' }}</dd></div>
            <div><dt>Customer reference</dt><dd>{{ account.external_id || '—' }}</dd></div>
          </dl>

          @if (error()) { <div class="error" role="alert">{{ error() }}</div> }
          <button class="primary" type="button" (click)="confirm()" [disabled]="loading() || !account.active">
            {{ loading() ? 'Sending verification code…' : 'Yes, these details are correct' }}
          </button>
          <a class="back" routerLink="/login/merchants/account">← This is not my account</a>
        </section>
      }
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .verification-page { min-height: 100vh; background: #f5f7fb; padding: 2.2rem 1.25rem; }
    .brand { display: flex; align-items: center; gap: .6rem; width: fit-content; margin: 0 auto; color: #1e2761; font-weight: 800; text-decoration: none; }.brand span { width: 2rem; height: 2rem; display: grid; place-items: center; background: #ffcb45; border-radius: .6rem; }
    .verification-card { background: #fff; width: min(100%, 570px); margin: 3rem auto 0; padding: clamp(1.5rem, 5vw, 2.7rem); border: 1px solid #e2e6ef; border-radius: 1rem; box-shadow: 0 18px 55px rgba(30,39,97,.08); position: relative; }.step { position: absolute; top: 1.2rem; right: 1.4rem; color: #9298a9; font-size: .7rem; }.success-mark { width: 3rem; height: 3rem; display: grid; place-items: center; border-radius: 50%; background: #def4e8; color: #24774f; font-weight: 850; font-size: 1.15rem; margin-bottom: 1.35rem; }.eyebrow { margin: 0; color: #24774f; text-transform: uppercase; letter-spacing: .12em; font-size: .68rem; font-weight: 850; }h1 { color: #192250; font-size: 1.75rem; letter-spacing: -.035em; margin: .45rem 0 .65rem; }.subtitle { color: #777e92; font-size: .82rem; line-height: 1.6; margin: 0 0 1.4rem; }
    .business-name { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; background: #f7f8fc; border: 1px solid #e6e9f1; border-radius: .7rem; padding: .9rem; }.business-name > span { width: 2.7rem; height: 2.7rem; display: grid; place-items: center; border-radius: .65rem; background: #1e2761; color: white; font-weight: 850; }.business-name div { display: grid; gap: .18rem; }.business-name small { color: #9298a9; font-size: .59rem; }.business-name strong { color: #303950; font-size: .8rem; }.business-name em { font-style: normal; color: #24774f; background: #e1f4e9; padding: .27rem .5rem; border-radius: 1rem; font-size: .57rem; font-weight: 800; }.business-name em.inactive { color: #a43b2b; background: #fde9e5; }
    dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 1.25rem 0; border: 1px solid #e6e9f1; border-radius: .7rem; overflow: hidden; }dl div { padding: .8rem .9rem; display: grid; gap: .24rem; border-bottom: 1px solid #edf0f4; }dl div:nth-child(odd) { border-right: 1px solid #edf0f4; }dl div:nth-last-child(-n+2) { border-bottom: 0; }dt { color: #9298a9; font-size: .6rem; }dd { margin: 0; color: #384158; font-size: .73rem; font-weight: 750; }
    .primary { width: 100%; border: 0; border-radius: .6rem; padding: .86rem; background: #1e2761; color: #fff; font-size: .8rem; font-weight: 750; }.primary:disabled { opacity: .55; cursor: not-allowed; }.back { display: block; text-align: center; margin-top: 1rem; color: #667087; font-size: .72rem; text-decoration: none; }.error { padding: .72rem; margin-bottom: 1rem; border-radius: .5rem; background: #fff0ee; color: #a43b2b; font-size: .74rem; }
    @media (max-width: 520px) { dl { grid-template-columns: 1fr; }dl div, dl div:nth-child(odd), dl div:nth-last-child(-n+2) { border-right: 0; border-bottom: 1px solid #edf0f4; }dl div:last-child { border-bottom: 0; }.business-name { grid-template-columns: auto 1fr; }.business-name em { grid-column: 2; width: fit-content; } }
  `],
})
export class AccountVerificationComponent implements OnInit {
  loading = signal(false);
  error = signal<string | null>(null);

  constructor(
    public readonly session: MerchantPortalSessionService,
    private readonly api: MerchantPortalApiService,
    private readonly router: Router,
  ) {}

  get maskedPhone(): string {
    const phone = this.session.accountPreview()?.mobile_no ?? '';
    const digits = phone.replace(/\D/g, '');
    return digits.length > 4 ? `******${digits.slice(-4)}` : 'Not available';
  }

  get maskedEmail(): string {
    const email = this.session.accountPreview()?.email;
    if (!email?.includes('@')) return 'Not available';
    const [name, domain] = email.split('@');
    return `${name.slice(0, 2)}***@${domain}`;
  }

  ngOnInit(): void {
    if (!this.session.accountPreview()) void this.router.navigate(['/login/merchants/account']);
  }

  confirm(): void {
    const preview = this.session.accountPreview();
    if (!preview) return;
    this.loading.set(true); this.error.set(null);
    this.api.requestOtp(preview).subscribe({
      next: (challenge) => { this.session.setOtpChallenge(challenge); this.loading.set(false); void this.router.navigate(['/login/merchants/otp']); },
      error: (error) => { this.error.set(error?.error?.message ?? error?.message ?? 'We could not send the verification code.'); this.loading.set(false); },
    });
  }
}

