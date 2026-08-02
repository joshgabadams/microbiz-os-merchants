import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="login-wrap">
      <form class="login-card" (ngSubmit)="onSubmit()">
        <h1>MicroBiz OS</h1>
        <p class="subtitle">Sign in to continue</p>

        <label>
          Email
          <input type="email" name="email" [(ngModel)]="email" required autocomplete="email" />
        </label>

        <label>
          Password
          <input type="password" name="password" [(ngModel)]="password" required autocomplete="current-password" />
        </label>

        @if (error()) {
          <p class="error">{{ error() }}</p>
        }

        <button type="submit" [disabled]="loading()">
          {{ loading() ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    .login-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #1e2761;
    }
    .login-card {
      background: white;
      padding: 2.5rem;
      border-radius: 12px;
      width: 320px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    h1 {
      margin: 0 0 0.25rem;
      font-size: 1.4rem;
    }
    .subtitle {
      margin: 0 0 1.5rem;
      color: #6b7290;
      font-size: 0.9rem;
    }
    label {
      display: block;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }
    input {
      display: block;
      width: 100%;
      margin-top: 0.3rem;
      padding: 0.5rem 0.6rem;
      border: 1px solid #d5d9e6;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    button {
      width: 100%;
      padding: 0.6rem;
      background: #1e2761;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 0.95rem;
      margin-top: 0.5rem;
    }
    button:disabled {
      opacity: 0.6;
    }
    .error {
      color: #b3261e;
      font-size: 0.85rem;
      margin: -0.5rem 0 1rem;
    }
  `],
})
export class LoginComponent {
  email = '';
  password = '';
  loading = signal(false);
  error = signal<string | null>(null);

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit(): void {
    this.loading.set(true);
    this.error.set(null);

    this.auth.login(this.email, this.password).subscribe({
      next: () => {
        this.loading.set(false);
        this.router.navigate(['/']);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err?.error?.message ?? 'Invalid credentials.');
      },
    });
  }
}
