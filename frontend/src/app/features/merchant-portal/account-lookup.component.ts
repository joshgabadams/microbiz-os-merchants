import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantPortalSessionService } from '../../core/merchant-portal-session.service';

@Component({
  selector: 'app-account-lookup',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <main class="account-page">
      <a class="account-brand" routerLink="/login/merchants"><span>M</span> MicroBiz</a>
      <section class="account-card">
        <div class="step">Step 2 of 6</div>
        <div class="account-icon">#</div>
        <p class="eyebrow">Connect your account</p>
        <h1>Enter your MicroBiz account number</h1>
        <p class="subtitle">We’ll use this to securely find the business profile linked to {{ draftEmail }}.</p>

        <form (ngSubmit)="continueToPortal()" #accountForm="ngForm">
          <label>
            MicroBiz account number
            <input name="accountNumber" [(ngModel)]="accountNumber" required pattern="[0-9]{1,50}" maxlength="50" inputmode="numeric" autocomplete="off" placeholder="Enter account number" />
          </label>
          <p class="hint">Your account number can be found on your MicroBiz statement.</p>

          @if (error()) {
            <div class="error" role="alert">{{ error() }}</div>
          }

          <button type="submit" [disabled]="accountForm.invalid || loading()">
            {{ loading() ? 'Finding your account…' : 'Find my account' }}
          </button>
        </form>
        <a class="back-link" routerLink="/login/merchants">← Back to sign in</a>
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .account-page { min-height: 100vh; background: #f5f7fb; padding: 2.2rem 1.25rem; }
    .account-brand { display: flex; align-items: center; gap: .6rem; width: fit-content; margin: 0 auto; color: #1e2761; font-weight: 800; text-decoration: none; }
    .account-brand span { width: 2rem; height: 2rem; display: grid; place-items: center; background: #ffcb45; border-radius: .6rem; }
    .account-card { background: white; width: min(100%, 500px); margin: 4rem auto 0; padding: clamp(1.5rem, 5vw, 3rem); border: 1px solid #e4e7f0; border-radius: 1rem; box-shadow: 0 18px 55px rgba(30,39,97,.08); position: relative; }
    .step { position: absolute; top: 1.2rem; right: 1.4rem; font-size: .72rem; color: #8b91a4; }
    .account-icon { width: 3rem; height: 3rem; display: grid; place-items: center; border-radius: .8rem; background: #fff4d3; color: #9a6a00; font-size: 1.25rem; font-weight: 850; margin-bottom: 1.5rem; }
    .eyebrow { color: #9a6a00; text-transform: uppercase; font-size: .7rem; letter-spacing: .12em; font-weight: 800; }
    h1 { font-size: 1.75rem; line-height: 1.2; letter-spacing: -.035em; margin: .5rem 0 .75rem; }
    .subtitle { color: #747b91; line-height: 1.55; font-size: .88rem; margin-bottom: 1.7rem; }
    label { display: block; font-size: .82rem; font-weight: 750; }
    input { display: block; width: 100%; margin-top: .5rem; padding: .9rem; border: 1px solid #d6dbe8; border-radius: .6rem; letter-spacing: .12em; font-size: 1rem; outline: 0; }
    input:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.12); }
    .hint { color: #8b91a4; font-size: .74rem; margin: .45rem 0 1.25rem; }
    button { width: 100%; padding: .86rem; border: 0; border-radius: .6rem; background: #1e2761; color: #fff; font-weight: 750; }
    button:disabled { opacity: .55; cursor: not-allowed; }
    .error { padding: .75rem; background: #fff0ee; color: #a43b2b; border-radius: .55rem; font-size: .8rem; margin-bottom: 1rem; }
    .back-link { display: block; text-align: center; margin-top: 1.25rem; color: #5f6880; font-size: .8rem; text-decoration: none; }
  `],
})
export class AccountLookupComponent implements OnInit {
  accountNumber = '000000002';
  draftEmail = '';
  loading = signal(false);
  error = signal<string | null>(null);

  constructor(
    private readonly api: MerchantPortalApiService,
    private readonly session: MerchantPortalSessionService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    const draft = this.session.loginDraft();
    if (!draft) {
      void this.router.navigate(['/login/merchants']);
      return;
    }
    this.draftEmail = draft.email;
  }

  continueToPortal(): void {
    const draft = this.session.loginDraft();
    if (!draft) {
      void this.router.navigate(['/login/merchants']);
      return;
    }

    this.loading.set(true);
    this.error.set(null);
    this.api.previewAccount(this.accountNumber, draft).subscribe({
      next: (preview) => {
        this.session.setAccountPreview(preview);
        this.loading.set(false);
        void this.router.navigate(['/login/merchants/verify']);
      },
      error: (error) => {
        this.error.set(error?.error?.message ?? error?.message ?? 'We could not find this account.');
        this.loading.set(false);
      },
    });
  }
}
