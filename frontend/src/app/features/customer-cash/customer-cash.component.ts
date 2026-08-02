import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { CustomerCashApiService } from '../../core/customer-cash-api.service';

@Component({
  selector: 'app-customer-cash',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Customer Cash</h1>
    <p class="hint">
      No list endpoint exists yet for customers/customer accounts, so teller
      and account IDs are entered directly here rather than picked from a
      dropdown -- check Tinker or add a listing endpoint if you want that.
    </p>

    <div class="forms">
      <form (ngSubmit)="onDeposit()">
        <h2>Deposit</h2>
        <label>Teller ID <input type="number" name="teller_id_d" [(ngModel)]="deposit.teller_id" required /></label>
        <label>Customer Account ID <input type="number" name="account_id_d" [(ngModel)]="deposit.customer_account_id" required /></label>
        <label>Amount <input type="number" name="amount_d" [(ngModel)]="deposit.amount" required /></label>
        <label>Reference <input type="text" name="ref_d" [(ngModel)]="deposit.reference" /></label>
        @if (depositError()) {
          <p class="error">{{ depositError() }}</p>
        }
        @if (depositSuccess()) {
          <p class="success">{{ depositSuccess() }}</p>
        }
        <button type="submit" [disabled]="depositBusy()">
          {{ depositBusy() ? 'Processing...' : 'Deposit' }}
        </button>
      </form>

      <form (ngSubmit)="onWithdraw()">
        <h2>Withdraw</h2>
        <label>Teller ID <input type="number" name="teller_id_w" [(ngModel)]="withdraw.teller_id" required /></label>
        <label>Customer Account ID <input type="number" name="account_id_w" [(ngModel)]="withdraw.customer_account_id" required /></label>
        <label>Amount <input type="number" name="amount_w" [(ngModel)]="withdraw.amount" required /></label>
        <label>Reference <input type="text" name="ref_w" [(ngModel)]="withdraw.reference" /></label>
        @if (withdrawError()) {
          <p class="error">{{ withdrawError() }}</p>
        }
        @if (withdrawSuccess()) {
          <p class="success">{{ withdrawSuccess() }}</p>
        }
        <button type="submit" [disabled]="withdrawBusy()">
          {{ withdrawBusy() ? 'Processing...' : 'Withdraw' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    h2 { font-size: 1rem; margin-top: 0; }
    .hint { color: #6b7290; font-size: 0.85rem; max-width: 480px; }
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
export class CustomerCashComponent {
  deposit = { teller_id: null as number | null, customer_account_id: null as number | null, amount: null as number | null, reference: '' };
  withdraw = { teller_id: null as number | null, customer_account_id: null as number | null, amount: null as number | null, reference: '' };

  depositBusy = signal(false);
  depositError = signal<string | null>(null);
  depositSuccess = signal<string | null>(null);

  withdrawBusy = signal(false);
  withdrawError = signal<string | null>(null);
  withdrawSuccess = signal<string | null>(null);

  constructor(private api: CustomerCashApiService, private auth: AuthService) {}

  onDeposit(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.deposit.teller_id || !this.deposit.customer_account_id || !this.deposit.amount) {
      return;
    }

    this.depositBusy.set(true);
    this.depositError.set(null);
    this.depositSuccess.set(null);

    this.api
      .deposit({
        teller_id: this.deposit.teller_id,
        customer_account_id: this.deposit.customer_account_id,
        amount: this.deposit.amount,
        performed_by: userId,
        reference: this.deposit.reference || undefined,
      })
      .subscribe({
        next: (res) => {
          this.depositBusy.set(false);
          this.depositSuccess.set(res.message);
        },
        error: (err) => {
          this.depositBusy.set(false);
          this.depositError.set(err?.error?.message ?? 'Deposit failed.');
        },
      });
  }

  onWithdraw(): void {
    const userId = this.auth.user()?.id;
    if (!userId || !this.withdraw.teller_id || !this.withdraw.customer_account_id || !this.withdraw.amount) {
      return;
    }

    this.withdrawBusy.set(true);
    this.withdrawError.set(null);
    this.withdrawSuccess.set(null);

    this.api
      .withdraw({
        teller_id: this.withdraw.teller_id,
        customer_account_id: this.withdraw.customer_account_id,
        amount: this.withdraw.amount,
        performed_by: userId,
        reference: this.withdraw.reference || undefined,
      })
      .subscribe({
        next: (res) => {
          this.withdrawBusy.set(false);
          this.withdrawSuccess.set(res.message);
        },
        error: (err) => {
          this.withdrawBusy.set(false);
          this.withdrawError.set(err?.error?.message ?? 'Withdrawal failed.');
        },
      });
  }
}
