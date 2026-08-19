import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';

@Component({
  selector: 'app-otp-verification',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <main class="otp-page">
      <a class="brand" routerLink="/login/merchants"><span>M</span> MicroBiz</a>
      <section class="otp-card">
        <div class="step">Step 4 of 4</div>
        <div class="phone-icon">✦</div>
        <p class="eyebrow">Security verification</p>
        <h1>Enter the code we sent</h1>
        <p class="subtitle">A 4-digit verification code was sent to <strong>{{ session.otpChallenge()?.masked_phone }}</strong>, the phone linked to your account.</p>

        <form (ngSubmit)="verify()" #otpForm="ngForm">
          <label>Verification code
            <input name="otp" [(ngModel)]="otp" required pattern="[0-9]{4}" maxlength="4" inputmode="numeric" autocomplete="one-time-code" placeholder="0000" autofocus />
          </label>
          <p class="preview-note">For this preview, use code <strong>0000</strong>.</p>
          @if (error()) { <div class="error" role="alert">{{ error() }}</div> }
          <button class="primary" type="submit" [disabled]="otpForm.invalid || loading()">{{ loading() ? 'Verifying code…' : 'Verify and continue' }}</button>
        </form>

        @if (secondsRemaining() > 0) {
          <p class="resend">You can request another code in <strong>{{ formattedTime }}</strong></p>
        } @else {
          <button class="resend-button" type="button" (click)="resend()" [disabled]="resending()">{{ resending() ? 'Sending…' : 'Resend verification code' }}</button>
        }
        <a class="back" routerLink="/login/merchants/verify">← Back to account details</a>
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }.otp-page { min-height: 100vh; background: #f5f7fb; padding: 2.2rem 1.25rem; }.brand { display: flex; align-items: center; gap: .6rem; width: fit-content; margin: 0 auto; color: #1e2761; font-weight: 800; text-decoration: none; }.brand span { width: 2rem; height: 2rem; display: grid; place-items: center; background: #ffcb45; border-radius: .6rem; }.otp-card { width: min(100%, 480px); background: white; margin: 4rem auto 0; padding: clamp(1.5rem, 5vw, 2.8rem); border: 1px solid #e2e6ef; border-radius: 1rem; box-shadow: 0 18px 55px rgba(30,39,97,.08); text-align: center; position: relative; }.step { position: absolute; top: 1.2rem; right: 1.4rem; color: #9298a9; font-size: .7rem; }.phone-icon { width: 3.3rem; height: 3.3rem; margin: 0 auto 1.3rem; display: grid; place-items: center; border-radius: .8rem; background: #fff2cc; color: #9a6a00; font-size: 1.15rem; }.eyebrow { margin: 0; color: #9a6a00; text-transform: uppercase; letter-spacing: .12em; font-size: .66rem; font-weight: 850; }h1 { margin: .5rem 0 .7rem; color: #192250; font-size: 1.7rem; letter-spacing: -.035em; }.subtitle { margin: 0 auto 1.7rem; max-width: 370px; color: #777e92; font-size: .8rem; line-height: 1.6; }.subtitle strong { color: #414a61; }label { display: block; color: #51596f; font-size: .7rem; font-weight: 750; text-align: left; }input { display: block; width: 100%; margin-top: .5rem; border: 1px solid #d7dce8; border-radius: .65rem; padding: .85rem; text-align: center; letter-spacing: .65em; font-size: 1.5rem; font-weight: 800; color: #1e2761; outline: 0; }input:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.12); }.preview-note { color: #8b91a3; font-size: .68rem; margin: .55rem 0 1rem; }.primary { width: 100%; border: 0; border-radius: .6rem; padding: .86rem; background: #1e2761; color: white; font-weight: 750; }.primary:disabled { opacity: .55; cursor: not-allowed; }.error { background: #fff0ee; color: #a43b2b; padding: .7rem; border-radius: .5rem; font-size: .73rem; margin-bottom: 1rem; }.resend { color: #8b91a3; font-size: .7rem; margin: 1.1rem 0 .5rem; }.resend-button { border: 0; background: transparent; color: #2d4597; font-weight: 750; font-size: .72rem; margin-top: 1rem; }.back { display: block; margin-top: 1rem; color: #697187; font-size: .7rem; text-decoration: none; }
  `],
})
export class OtpVerificationComponent implements OnInit, OnDestroy {
  otp = '';
  loading = signal(false); resending = signal(false); error = signal<string | null>(null); secondsRemaining = signal(60);
  private timerId: ReturnType<typeof setInterval> | null = null;

  constructor(public readonly session: MerchantPortalSessionService, private readonly api: MerchantPortalApiService, private readonly router: Router) {}
  get formattedTime(): string { return `0:${this.secondsRemaining().toString().padStart(2, '0')}`; }
  ngOnInit(): void { if (!this.session.accountPreview() || !this.session.otpChallenge()) { void this.router.navigate(['/login/merchants/account']); return; } this.startTimer(); }
  ngOnDestroy(): void { if (this.timerId) clearInterval(this.timerId); }
  verify(): void {
    const challenge = this.session.otpChallenge(); const preview = this.session.accountPreview(); const draft = this.session.loginDraft();
    if (!challenge || !preview || !draft) { void this.router.navigate(['/login/merchants']); return; }
    this.loading.set(true); this.error.set(null);
    this.api.verifyOtp(challenge.challenge_id, this.otp).subscribe({
      next: () => { this.session.startSession(this.api.createPreviewSession(preview, draft.email)); this.loading.set(false); void this.router.navigate(['/merchant/dashboard']); },
      error: (error: Error) => { this.error.set(error.message || 'The verification code is incorrect.'); this.loading.set(false); },
    });
  }
  resend(): void {
    const preview = this.session.accountPreview(); if (!preview) return;
    this.resending.set(true); this.error.set(null);
    this.api.requestOtp(preview).subscribe({ next: (challenge) => { this.session.setOtpChallenge(challenge); this.resending.set(false); this.secondsRemaining.set(60); this.startTimer(); }, error: () => { this.error.set('We could not resend the code.'); this.resending.set(false); } });
  }
  private startTimer(): void { if (this.timerId) clearInterval(this.timerId); this.timerId = setInterval(() => { const next = this.secondsRemaining() - 1; this.secondsRemaining.set(Math.max(0, next)); if (next <= 0 && this.timerId) { clearInterval(this.timerId); this.timerId = null; } }, 1000); }
}
