import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/auth.service';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';
import { PortalRole } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-portal-login',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <main class="portal-auth">
      <section class="portal-auth__intro">
        <a class="portal-brand portal-brand--light" routerLink="/login/merchants" aria-label="MicroBiz home">
          <span class="portal-brand__mark">M</span>
          <span>MicroBiz</span>
        </a>
        <div>
          <p class="eyebrow">Business banking, simplified</p>
          <h1>Everything your business needs, in one place.</h1>
          <p>Track collections, monitor settlements, and manage your MicroBiz business account securely.</p>
        </div>
        <p class="portal-auth__footer">Secure access protected by MicroBiz OS</p>
      </section>

      <section class="portal-auth__panel">
        <form class="portal-auth__card" (ngSubmit)="submit()" #loginForm="ngForm">
          <div class="portal-auth__mobile-brand">
            <span class="portal-brand__mark">M</span>
            <strong>MicroBiz</strong>
          </div>
          <p class="eyebrow">Welcome back</p>
          <h2>Sign in to your account</h2>
          <p class="muted">Choose how you use MicroBiz, then enter your details.</p>

          <div class="role-switch" role="group" aria-label="Account type">
            <button type="button" [class.active]="role === 'merchant'" (click)="role = 'merchant'">
              Merchant
            </button>
            <button type="button" [class.active]="role === 'agent'" (click)="role = 'agent'">
              Agent
            </button>
          </div>

          <label class="portal-field">
            <span>Email address</span>
            <input type="email" name="email" [(ngModel)]="email" required email autocomplete="email" placeholder="you@business.com" />
          </label>

          <label class="portal-field">
            <span>Password</span>
            <input type="password" name="password" [(ngModel)]="password" required minlength="6" autocomplete="current-password" placeholder="Enter your password" />
          </label>

          <div class="portal-form-row">
            <label class="check-label"><input type="checkbox" name="remember" [(ngModel)]="remember" /> Remember me</label>
            <button class="text-button" type="button">Forgot password?</button>
          </div>

          @if (error()) {
            <div class="portal-alert portal-alert--error" role="alert">{{ error() }}</div>
          }

          <button class="portal-primary-button" type="submit" [disabled]="loginForm.invalid || loading()">
            {{ loading() ? 'Checking your details…' : 'Continue' }}
          </button>

          <p class="portal-auth__register">Merchant access is currently available to existing MicroBiz customers.</p>
          <p class="mock-note">Development access: use the pre-filled local API credentials.</p>
        </form>
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .portal-auth { min-height: 100vh; display: grid; grid-template-columns: minmax(340px, .9fr) minmax(480px, 1.1fr); background: #fff; }
    .portal-auth__intro { padding: 3rem clamp(2rem, 5vw, 5.5rem); background: linear-gradient(145deg, #101b48 0%, #1e2761 58%, #283a83 100%); color: white; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
    .portal-auth__intro::after { content: ''; width: 430px; height: 430px; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; position: absolute; right: -210px; bottom: -80px; box-shadow: 0 0 0 70px rgba(255,255,255,.035), 0 0 0 140px rgba(255,255,255,.025); }
    .portal-brand { display: inline-flex; align-items: center; gap: .7rem; text-decoration: none; font-size: 1.2rem; font-weight: 750; width: fit-content; position: relative; z-index: 1; }
    .portal-brand__mark { width: 2.1rem; height: 2.1rem; border-radius: .65rem; background: #ffcb45; color: #1e2761; display: inline-grid; place-items: center; font-weight: 850; }
    .portal-auth__intro h1 { font-size: clamp(2.25rem, 4.2vw, 4.4rem); line-height: 1.03; letter-spacing: -.055em; max-width: 700px; margin: .8rem 0 1.4rem; position: relative; z-index: 1; }
    .portal-auth__intro p:not(.eyebrow) { color: #d6ddf6; font-size: 1.05rem; line-height: 1.7; max-width: 560px; position: relative; z-index: 1; }
    .eyebrow { text-transform: uppercase; font-size: .74rem; letter-spacing: .13em; font-weight: 800; color: #efac00; margin: 0; }
    .portal-auth__footer { font-size: .78rem !important; margin: 0; }
    .portal-auth__panel { display: grid; place-items: center; padding: 2rem; }
    .portal-auth__card { width: min(100%, 430px); }
    .portal-auth__card h2 { font-size: 2rem; letter-spacing: -.035em; margin: .6rem 0 .55rem; }
    .muted { color: #747b91; margin: 0 0 1.7rem; line-height: 1.55; }
    .role-switch { background: #f1f3f8; padding: .3rem; border-radius: .7rem; display: grid; grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem; }
    .role-switch button { border: 0; background: transparent; padding: .72rem; border-radius: .5rem; color: #697088; font-weight: 700; }
    .role-switch button.active { background: white; color: #1e2761; box-shadow: 0 2px 10px rgba(24,34,80,.1); }
    .portal-field { display: block; font-size: .82rem; font-weight: 700; color: #32384c; margin-bottom: 1rem; }
    .portal-field input { display: block; width: 100%; margin-top: .45rem; padding: .82rem .9rem; border: 1px solid #d9ddea; border-radius: .6rem; outline: 0; font-size: .92rem; }
    .portal-field input:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.12); }
    .portal-form-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin: .25rem 0 1.15rem; font-size: .8rem; }
    .check-label { display: flex; align-items: center; gap: .45rem; color: #60677c; }
    .text-button { border: 0; background: transparent; color: #2c4498; font-weight: 700; padding: 0; }
    .portal-primary-button { width: 100%; border: 0; border-radius: .62rem; padding: .85rem 1rem; background: #1e2761; color: white; font-weight: 750; font-size: .92rem; }
    .portal-primary-button:disabled { opacity: .55; cursor: not-allowed; }
    .portal-alert { border-radius: .55rem; padding: .75rem .85rem; font-size: .82rem; margin-bottom: 1rem; }
    .portal-alert--error { background: #fff0ee; color: #a43b2b; border: 1px solid #ffd4ce; }
    .portal-auth__register { text-align: center; font-size: .85rem; margin: 1.2rem 0 .5rem; color: #71788d; }
    .mock-note { text-align: center; color: #9a9fb0; font-size: .72rem; }
    .portal-auth__mobile-brand { display: none; }
    @media (max-width: 800px) {
      .portal-auth { grid-template-columns: 1fr; }
      .portal-auth__intro { display: none; }
      .portal-auth__panel { padding: 2rem 1.25rem; align-items: start; }
      .portal-auth__mobile-brand { display: flex; align-items: center; gap: .65rem; margin: 1rem 0 3rem; color: #1e2761; }
    }
  `],
})
export class PortalLoginComponent {
  role: PortalRole = 'merchant';
  email = environment.merchantPortalApiMode === 'live' ? 'test@example.com' : 'merchant@microbiz.test';
  password = 'password';
  remember = true;
  loading = signal(false);
  error = signal<string | null>(null);

  constructor(
    private readonly api: MerchantPortalApiService,
    private readonly auth: AuthService,
    private readonly session: MerchantPortalSessionService,
    private readonly router: Router,
  ) {}

  submit(): void {
    this.loading.set(true);
    this.error.set(null);

    if (environment.merchantPortalApiMode === 'live') {
      this.auth.login(this.email, this.password).subscribe({
        next: () => this.completeLogin(),
        error: (error) => {
          this.error.set(error?.error?.message ?? 'The development API credentials are invalid.');
          this.loading.set(false);
        },
      });
      return;
    }

    this.api.beginLogin(this.role, this.email, this.password).subscribe({
      next: () => this.completeLogin(),
      error: (error: Error) => {
        this.error.set(error.message || 'We could not sign you in.');
        this.loading.set(false);
      },
    });
  }

  private completeLogin(): void {
    this.session.setLoginDraft(this.role, this.email.trim());
    this.loading.set(false);
    void this.router.navigate(['/login/merchants/account']);
  }
}
