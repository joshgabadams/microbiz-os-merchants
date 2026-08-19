import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { MerchantPortalApiService } from '../../core/merchant-portal-api.service';
import { MerchantRegistrationRequest, MerchantRegistrationResult } from '../../core/models/merchant-portal.models';

@Component({
  selector: 'app-merchant-registration',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <main class="registration-page">
      <header>
        <a routerLink="/login/merchants" class="brand"><span>M</span> MicroBiz</a>
        <a routerLink="/login/merchants" class="signin">Already registered? Sign in</a>
      </header>

      <section class="registration-wrap">
        <div class="registration-copy">
          <p class="eyebrow">Merchant registration</p>
          <h1>Grow your business with MicroBiz.</h1>
          <p>Register your business to collect payments, track transactions, and manage settlements from one secure workspace.</p>
          <ul>
            <li><span>✓</span> Fast digital payment collection</li>
            <li><span>✓</span> Clear transaction reporting</li>
            <li><span>✓</span> Reliable settlement visibility</li>
          </ul>
        </div>

        <div class="registration-card">
          @if (result()) {
            <div class="success-state">
              <div class="success-icon">✓</div>
              <p class="eyebrow">Application received</p>
              <h2>Thanks for registering.</h2>
              <p>Your reference is <strong>{{ result()?.applicationReference }}</strong>. We’ll contact you when your merchant profile is ready.</p>
              <a routerLink="/login/merchants">Return to sign in</a>
            </div>
          } @else {
            <h2>Tell us about your business</h2>
            <p class="subtitle">All fields are required unless marked optional.</p>
            <form (ngSubmit)="submit()" #registrationForm="ngForm">
              <div class="field-grid">
                <label class="wide">Business name<input name="businessName" [(ngModel)]="form.businessName" required minlength="2" /></label>
                <label>Contact name<input name="contactName" [(ngModel)]="form.contactName" required /></label>
                <label>Registration number<input name="registrationNumber" [(ngModel)]="form.registrationNumber" required /></label>
                <label>Email address<input type="email" name="email" [(ngModel)]="form.email" required email /></label>
                <label>Phone number<input type="tel" name="phone" [(ngModel)]="form.phone" required minlength="10" /></label>
                <label>Password<input type="password" name="password" [(ngModel)]="form.password" required minlength="8" autocomplete="new-password" /></label>
                <label>Confirm password<input type="password" name="confirmPassword" [(ngModel)]="confirmPassword" required minlength="8" autocomplete="new-password" /></label>
              </div>
              <label class="terms"><input type="checkbox" name="terms" [(ngModel)]="acceptedTerms" required /> I confirm that the information provided is accurate and I accept the MicroBiz terms.</label>

              @if (error()) { <div class="form-error" role="alert">{{ error() }}</div> }
              <button type="submit" [disabled]="registrationForm.invalid || loading()">
                {{ loading() ? 'Submitting application…' : 'Register business' }}
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
    .brand { display: flex; align-items: center; gap: .6rem; font-weight: 800; color: #1e2761; text-decoration: none; }
    .brand span { width: 2rem; height: 2rem; display: grid; place-items: center; background: #ffcb45; border-radius: .6rem; }
    .signin { color: #2e4698; font-size: .82rem; font-weight: 750; text-decoration: none; }
    .registration-wrap { width: min(1180px, calc(100% - 2.5rem)); margin: 0 auto; padding: 4rem 0; display: grid; grid-template-columns: .8fr 1.2fr; gap: clamp(2rem, 7vw, 7rem); align-items: start; }
    .registration-copy { padding-top: 2.5rem; }
    .eyebrow { color: #9a6a00; text-transform: uppercase; letter-spacing: .13em; font-weight: 800; font-size: .7rem; }
    .registration-copy h1 { color: #172152; font-size: clamp(2.3rem, 4vw, 4rem); line-height: 1.05; letter-spacing: -.05em; margin: .8rem 0 1.2rem; }
    .registration-copy > p:not(.eyebrow) { color: #6e758b; line-height: 1.7; }
    ul { list-style: none; padding: 0; margin: 2rem 0; display: grid; gap: .9rem; color: #42495f; font-size: .9rem; }
    li span { display: inline-grid; place-items: center; width: 1.35rem; height: 1.35rem; background: #def4e8; color: #20754c; border-radius: 50%; margin-right: .55rem; font-size: .75rem; }
    .registration-card { background: #fff; padding: clamp(1.4rem, 4vw, 2.5rem); border: 1px solid #e1e5ef; border-radius: 1rem; box-shadow: 0 18px 50px rgba(30,39,97,.07); }
    h2 { margin: 0 0 .45rem; font-size: 1.5rem; letter-spacing: -.025em; }
    .subtitle { color: #7b8297; font-size: .82rem; margin: 0 0 1.6rem; }
    .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    label { font-size: .77rem; color: #3c4358; font-weight: 750; }
    label.wide { grid-column: 1 / -1; }
    label input:not([type='checkbox']) { display: block; width: 100%; margin-top: .42rem; padding: .78rem .8rem; border: 1px solid #d8dce8; border-radius: .55rem; outline: 0; font-size: .88rem; }
    label input:focus { border-color: #4057ad; box-shadow: 0 0 0 3px rgba(64,87,173,.1); }
    .terms { display: flex; gap: .55rem; align-items: flex-start; margin: 1.2rem 0; font-weight: 500; line-height: 1.5; color: #656c80; }
    .terms input { margin-top: .18rem; }
    form > button, .success-state a { display: block; width: 100%; text-align: center; border: 0; border-radius: .6rem; padding: .85rem; background: #1e2761; color: #fff; font-weight: 750; text-decoration: none; }
    button:disabled { opacity: .55; cursor: not-allowed; }
    .form-error { background: #fff0ee; color: #a43b2b; padding: .75rem; border-radius: .55rem; margin-bottom: 1rem; font-size: .8rem; }
    .success-state { text-align: center; padding: 2.5rem 1rem; }
    .success-icon { width: 4rem; height: 4rem; display: grid; place-items: center; margin: 0 auto 1.4rem; background: #def4e8; color: #20754c; border-radius: 50%; font-size: 1.5rem; }
    .success-state p:not(.eyebrow) { color: #6e758b; line-height: 1.65; margin-bottom: 1.5rem; }
    @media (max-width: 800px) {
      .registration-wrap { grid-template-columns: 1fr; padding: 2rem 0; gap: 1.5rem; }
      .registration-copy { padding: 0; }
      .registration-copy ul { display: none; }
    }
    @media (max-width: 540px) { .field-grid { grid-template-columns: 1fr; } label.wide { grid-column: auto; } .signin { font-size: 0; } .signin::after { content: 'Sign in'; font-size: .82rem; } }
  `],
})
export class MerchantRegistrationComponent {
  form: MerchantRegistrationRequest = {
    businessName: '', contactName: '', email: '', phone: '', registrationNumber: '', password: '',
  };
  confirmPassword = '';
  acceptedTerms = false;
  loading = signal(false);
  error = signal<string | null>(null);
  result = signal<MerchantRegistrationResult | null>(null);

  constructor(private readonly api: MerchantPortalApiService) {}

  submit(): void {
    if (this.form.password !== this.confirmPassword) {
      this.error.set('The password confirmation does not match.');
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    this.api.register(this.form).subscribe({
      next: (result) => {
        this.result.set(result);
        this.loading.set(false);
      },
      error: (error: Error) => {
        this.error.set(error.message || 'Registration could not be completed.');
        this.loading.set(false);
      },
    });
  }
}
