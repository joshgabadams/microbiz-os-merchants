import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { BranchEodApiService } from '../../core/branch-eod-api.service';

@Component({
  selector: 'app-branch-eod',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Branch End of Day</h1>

    <form (ngSubmit)="onClose()">
      <label>Branch ID <input type="number" name="branch_id" [(ngModel)]="form.branch_id" required /></label>
      <label>Business Date <input type="date" name="business_date" [(ngModel)]="form.business_date" required /></label>
      <label>Note <input type="text" name="note" [(ngModel)]="form.note" /></label>
      @if (error()) {
        <p class="error">{{ error() }}</p>
      }
      @if (success()) {
        <p class="success">{{ success() }}</p>
      }
      <button type="submit" [disabled]="busy()">
        {{ busy() ? 'Closing...' : 'Close Branch EOD' }}
      </button>
    </form>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    form {
      background: white;
      border-radius: 8px;
      padding: 1.2rem;
      width: 280px;
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    label { font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem; }
    input { padding: 0.4rem; border: 1px solid #d5d9e6; border-radius: 6px; }
    button { padding: 0.5rem; background: #1e2761; color: white; border: none; border-radius: 6px; }
    .error { color: #b3261e; font-size: 0.85rem; margin: 0; }
    .success { color: #1a7f37; font-size: 0.85rem; margin: 0; }
  `],
})
export class BranchEodComponent {
  form = { branch_id: null as number | null, business_date: '', note: '' };

  busy = signal(false);
  error = signal<string | null>(null);
  success = signal<string | null>(null);

  constructor(private api: BranchEodApiService, private auth: AuthService) {}

  onClose(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.form.branch_id || !this.form.business_date) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.success.set(null);

    this.api
      .close({
        branch_id: this.form.branch_id,
        business_date: this.form.business_date,
        closed_by: userId,
        note: this.form.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.busy.set(false);
          this.success.set(res.message);
        },
        error: (err) => {
          this.busy.set(false);
          this.error.set(err?.error?.message ?? 'Branch EOD close failed.');
        },
      });
  }
}
