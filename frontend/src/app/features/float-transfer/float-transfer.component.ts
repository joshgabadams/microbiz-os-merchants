import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApprovalApiService } from '../../core/approval-api.service';

@Component({
  selector: 'app-float-transfer',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Vault ↔ Teller Float Movement</h1>
    <p class="hint">
      Both actions below create a maker-checker request, not an immediate
      transfer -- a checker must approve it on the Approvals page before the
      actual cash movement happens.
    </p>

    <div class="forms">
      <form (ngSubmit)="onAllocate()">
        <h2>Allocate Float</h2>
        <p class="direction">Vault → Teller</p>
        <label>Vault ID <input type="number" name="vault_id_a" [(ngModel)]="allocate.vault_id" required /></label>
        <label>Teller ID <input type="number" name="teller_id_a" [(ngModel)]="allocate.teller_id" required /></label>
        <label>Amount <input type="number" name="amount_a" [(ngModel)]="allocate.amount" required /></label>
        <label>Reference <input type="text" name="ref_a" [(ngModel)]="allocate.reference" /></label>
        <label>Maker Note <input type="text" name="note_a" [(ngModel)]="allocate.maker_note" /></label>
        @if (allocateError()) {
          <p class="error">{{ allocateError() }}</p>
        }
        @if (allocateSuccess()) {
          <p class="success">{{ allocateSuccess() }}</p>
        }
        <button type="submit" [disabled]="allocateBusy()">
          {{ allocateBusy() ? 'Submitting...' : 'Request Float Allocation' }}
        </button>
      </form>

      <form (ngSubmit)="onReturn()">
        <h2>Return Float</h2>
        <p class="direction">Teller → Vault</p>
        <label>Vault ID <input type="number" name="vault_id_r" [(ngModel)]="returnForm.vault_id" required /></label>
        <label>Teller ID <input type="number" name="teller_id_r" [(ngModel)]="returnForm.teller_id" required /></label>
        <label>Amount <input type="number" name="amount_r" [(ngModel)]="returnForm.amount" required /></label>
        <label>Reference <input type="text" name="ref_r" [(ngModel)]="returnForm.reference" /></label>
        <label>Maker Note <input type="text" name="note_r" [(ngModel)]="returnForm.maker_note" /></label>
        @if (returnError()) {
          <p class="error">{{ returnError() }}</p>
        }
        @if (returnSuccess()) {
          <p class="success">{{ returnSuccess() }}</p>
        }
        <button type="submit" [disabled]="returnBusy()">
          {{ returnBusy() ? 'Submitting...' : 'Request Float Return' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    h2 { font-size: 1rem; margin: 0; }
    .direction { color: #6b7290; font-size: 0.8rem; margin: 0 0 0.5rem; }
    .hint { color: #6b7290; font-size: 0.85rem; max-width: 520px; }
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
export class FloatTransferComponent {
  allocate = {
    vault_id: null as number | null,
    teller_id: null as number | null,
    amount: null as number | null,
    reference: '',
    maker_note: '',
  };

  returnForm = {
    vault_id: null as number | null,
    teller_id: null as number | null,
    amount: null as number | null,
    reference: '',
    maker_note: '',
  };

  allocateBusy = signal(false);
  allocateError = signal<string | null>(null);
  allocateSuccess = signal<string | null>(null);

  returnBusy = signal(false);
  returnError = signal<string | null>(null);
  returnSuccess = signal<string | null>(null);

  constructor(private api: ApprovalApiService) {}

  onAllocate(): void {
    if (!this.allocate.vault_id || !this.allocate.teller_id || !this.allocate.amount) {
      return;
    }

    this.allocateBusy.set(true);
    this.allocateError.set(null);
    this.allocateSuccess.set(null);

    this.api
      .requestAllocateFloat({
        vault_id: this.allocate.vault_id,
        teller_id: this.allocate.teller_id,
        amount: this.allocate.amount,
        reference: this.allocate.reference || undefined,
        maker_note: this.allocate.maker_note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.allocateBusy.set(false);
          this.allocateSuccess.set(`${res.message} Awaiting approval on the Approvals page.`);
        },
        error: (err) => {
          this.allocateBusy.set(false);
          this.allocateError.set(err?.error?.message ?? 'Float allocation request failed.');
        },
      });
  }

  onReturn(): void {
    if (!this.returnForm.vault_id || !this.returnForm.teller_id || !this.returnForm.amount) {
      return;
    }

    this.returnBusy.set(true);
    this.returnError.set(null);
    this.returnSuccess.set(null);

    this.api
      .requestReturnFloat({
        vault_id: this.returnForm.vault_id,
        teller_id: this.returnForm.teller_id,
        amount: this.returnForm.amount,
        reference: this.returnForm.reference || undefined,
        maker_note: this.returnForm.maker_note || undefined,
      })
      .subscribe({
        next: (res) => {
          this.returnBusy.set(false);
          this.returnSuccess.set(`${res.message} Awaiting approval on the Approvals page.`);
        },
        error: (err) => {
          this.returnBusy.set(false);
          this.returnError.set(err?.error?.message ?? 'Float return request failed.');
        },
      });
  }
}
