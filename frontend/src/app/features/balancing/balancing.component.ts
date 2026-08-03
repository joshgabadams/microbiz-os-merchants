import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { BalancingApiService } from '../../core/balancing-api.service';

@Component({
  selector: 'app-balancing',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Cash Balancing</h1>

    <div class="forms">
      <form (ngSubmit)="onTellerBalance()">
        <h2>Teller Balance</h2>
        <label>Teller ID <input type="number" name="teller_id" [(ngModel)]="tellerForm.teller_id" required /></label>
        <label>Business Date <input type="date" name="business_date_t" [(ngModel)]="tellerForm.business_date" required /></label>
        <label>Physical Cash Counted <input type="number" name="physical_cash_t" [(ngModel)]="tellerForm.physical_cash" required /></label>
        <label>Note <input type="text" name="note_t" [(ngModel)]="tellerForm.note" /></label>
        @if (tellerError()) {
          <p class="error">{{ tellerError() }}</p>
        }
        @if (tellerSuccess()) {
          <p class="success">{{ tellerSuccess() }}</p>
        }
        <button type="submit" [disabled]="tellerBusy()">
          {{ tellerBusy() ? 'Balancing...' : 'Balance Teller' }}
        </button>
      </form>

      <form (ngSubmit)="onVaultBalance()">
        <h2>Vault Balance</h2>
        <label>Vault ID <input type="number" name="vault_id" [(ngModel)]="vaultForm.vault_id" required /></label>
        <label>Business Date <input type="date" name="business_date_v" [(ngModel)]="vaultForm.business_date" required /></label>
        <label>Physical Cash Counted <input type="number" name="physical_cash_v" [(ngModel)]="vaultForm.physical_cash" required /></label>
        <label>Note <input type="text" name="note_v" [(ngModel)]="vaultForm.note" /></label>
        @if (vaultError()) {
          <p class="error">{{ vaultError() }}</p>
        }
        @if (vaultSuccess()) {
          <p class="success">{{ vaultSuccess() }}</p>
        }
        <button type="submit" [disabled]="vaultBusy()">
          {{ vaultBusy() ? 'Balancing...' : 'Balance Vault' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    h2 { font-size: 1rem; margin-top: 0; }
    .forms { display: flex; gap: 2rem; flex-wrap: wrap; }
    form {
      background: white;
      border-radius: 8px;
      padding: 1.2rem;
      width: 260px;
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
export class BalancingComponent {
  tellerForm = { teller_id: null as number | null, business_date: '', physical_cash: null as number | null, note: '' };
  vaultForm = { vault_id: null as number | null, business_date: '', physical_cash: null as number | null, note: '' };

  tellerBusy = signal(false);
  tellerError = signal<string | null>(null);
  tellerSuccess = signal<string | null>(null);

  vaultBusy = signal(false);
  vaultError = signal<string | null>(null);
  vaultSuccess = signal<string | null>(null);

  constructor(private api: BalancingApiService, private auth: AuthService) {}

  onTellerBalance(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.tellerForm.teller_id || !this.tellerForm.business_date || this.tellerForm.physical_cash === null) {
      return;
    }

    this.tellerBusy.set(true);
    this.tellerError.set(null);
    this.tellerSuccess.set(null);

    this.api
      .tellerBalance({
        teller_id: this.tellerForm.teller_id,
        business_date: this.tellerForm.business_date,
        physical_cash: this.tellerForm.physical_cash,
        balanced_by: userId,
        note: this.tellerForm.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.tellerBusy.set(false);
          this.tellerSuccess.set(res.message);
        },
        error: (err) => {
          this.tellerBusy.set(false);
          this.tellerError.set(err?.error?.message ?? 'Teller balancing failed.');
        },
      });
  }

  onVaultBalance(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.vaultForm.vault_id || !this.vaultForm.business_date || this.vaultForm.physical_cash === null) {
      return;
    }

    this.vaultBusy.set(true);
    this.vaultError.set(null);
    this.vaultSuccess.set(null);

    this.api
      .vaultBalance({
        vault_id: this.vaultForm.vault_id,
        business_date: this.vaultForm.business_date,
        physical_cash: this.vaultForm.physical_cash,
        balanced_by: userId,
        note: this.vaultForm.note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.vaultBusy.set(false);
          this.vaultSuccess.set(res.message);
        },
        error: (err) => {
          this.vaultBusy.set(false);
          this.vaultError.set(err?.error?.message ?? 'Vault balancing failed.');
        },
      });
  }
}
