import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MerchantApiService } from '../../core/merchant-api.service';
import { Merchant } from '../../core/models/api.models';

@Component({
  selector: 'app-merchant-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Merchants</h1>

    <div class="onboard-form">
      <input
        type="text"
        placeholder="Business name"
        [(ngModel)]="newBusinessName"
        [disabled]="onboarding()"
      />
      <input
        type="number"
        placeholder="Branch ID"
        [(ngModel)]="newBranchId"
        [disabled]="onboarding()"
      />
      <button (click)="onboard()" [disabled]="onboarding() || !newBusinessName().trim() || !newBranchId()">
        {{ onboarding() ? 'Onboarding...' : '+ Onboard Merchant' }}
      </button>
    </div>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (loadError()) {
      <p class="error">{{ loadError() }}</p>
    } @else if (merchants().length === 0) {
      <p>No merchants onboarded yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Business Name</th>
            <th>Status</th>
            <th>Ledger Balance</th>
            <th>Locked Balance</th>
            <th>Available Balance</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (merchant of merchants(); track merchant.id) {
            <tr>
              <td>{{ merchant.merchant_code }}</td>
              <td>{{ merchant.business_name }}</td>
              <td><span class="status status-{{ merchant.status.toLowerCase() }}">{{ merchant.status }}</span></td>
              <td>{{ merchant.balance?.ledger_balance ?? '-' }}</td>
              <td>{{ merchant.balance?.locked_balance ?? '-' }}</td>
              <td>{{ merchant.balance?.available_balance ?? '-' }}</td>
              <td class="actions">
                @if (merchant.status === 'DRAFT') {
                  <button (click)="submit(merchant)">Submit</button>
                }
                @if (merchant.status === 'PENDING_REVIEW') {
                  <button (click)="approve(merchant)">Approve</button>
                  <button (click)="reject(merchant)">Reject</button>
                }
                @if (merchant.status === 'APPROVED') {
                  <button (click)="activate(merchant)">Activate</button>
                }
                @if (merchant.status === 'ACTIVE') {
                  <button (click)="collectQr(merchant)">Collect QR</button>
                  <button (click)="collectPos(merchant)">Collect POS</button>
                  <button (click)="settle(merchant)">Settle</button>
                }
                @if (merchant.status === 'REJECTED' && merchant.rejection_reason) {
                  <span class="rejection-reason">{{ merchant.rejection_reason }}</span>
                }
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
    .onboard-form { display: flex; gap: 8px; margin-bottom: 16px; }
    .onboard-form input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 220px; }
    .actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .actions button { padding: 4px 10px; }
    .rejection-reason { color: #b3261e; font-size: 0.85em; font-style: italic; }
    .status { font-size: 0.85em; padding: 2px 8px; border-radius: 10px; background: #eee; }
    .status-active { background: #d7f0dd; color: #1f6f5c; }
    .status-draft { background: #eee; color: #555; }
    .status-pending_review { background: #fbe9c9; color: #8a5d00; }
    .status-approved { background: #d9e8fb; color: #1a4d8f; }
    .status-rejected, .status-suspended { background: #f6d9d5; color: #a6432f; }
  `],
})
export class MerchantListComponent implements OnInit {
  merchants = signal<Merchant[]>([]);
  loading = signal(true);
  loadError = signal<string | null>(null);
  actionError = signal<string | null>(null);

  newBusinessName = signal('');
  newBranchId = signal<number | null>(null);
  onboarding = signal(false);

  constructor(private api: MerchantApiService) {}

  ngOnInit(): void {
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (res) => {
        this.merchants.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set('Failed to load merchants.');
        this.loading.set(false);
      },
    });
  }

  onboard(): void {
    const businessName = this.newBusinessName().trim();
    const branchId = this.newBranchId();

    if (!businessName || !branchId) {
      return;
    }

    this.onboarding.set(true);
    this.actionError.set(null);

    this.api.onboard({ business_name: businessName, branch_id: branchId }).subscribe({
      next: () => {
        this.newBusinessName.set('');
        this.newBranchId.set(null);
        this.onboarding.set(false);
        this.reload();
      },
      error: (err) => {
        this.actionError.set(err?.error?.message ?? 'Failed to onboard merchant.');
        this.onboarding.set(false);
      },
    });
  }

  submit(merchant: Merchant): void {
    this.actionError.set(null);

    this.api.submit(merchant.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to submit merchant.'),
    });
  }

  approve(merchant: Merchant): void {
    this.actionError.set(null);

    this.api.approve(merchant.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to approve merchant.'),
    });
  }

  reject(merchant: Merchant): void {
    const reason = window.prompt('Reason for rejecting this merchant:');

    if (!reason || !reason.trim()) {
      return;
    }

    this.actionError.set(null);

    this.api.reject(merchant.id, reason.trim()).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to reject merchant.'),
    });
  }

  activate(merchant: Merchant): void {
    this.actionError.set(null);

    this.api.activate(merchant.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to activate merchant.'),
    });
  }

  collectQr(merchant: Merchant): void {
    const amount = this.promptAmount(`Amount to collect via QR for ${merchant.business_name}:`);

    if (amount === null) {
      return;
    }

    this.actionError.set(null);

    this.api
      .collectQr({
        merchant_id: merchant.id,
        amount,
        idempotency_key: crypto.randomUUID(),
        reference: `qr-${Date.now()}`,
      })
      .subscribe({
        next: () => this.reload(),
        error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to collect QR payment.'),
      });
  }

  collectPos(merchant: Merchant): void {
    const amount = this.promptAmount(`Amount to collect via POS for ${merchant.business_name}:`);

    if (amount === null) {
      return;
    }

    this.actionError.set(null);

    this.api
      .collectPos({
        merchant_id: merchant.id,
        amount,
        idempotency_key: crypto.randomUUID(),
        reference: `pos-${Date.now()}`,
      })
      .subscribe({
        next: () => this.reload(),
        error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to collect POS payment.'),
      });
  }

  settle(merchant: Merchant): void {
    const amount = this.promptAmount(`Amount to settle out for ${merchant.business_name}:`);

    if (amount === null) {
      return;
    }

    this.actionError.set(null);

    this.api
      .settle({
        merchant_id: merchant.id,
        amount,
        idempotency_key: crypto.randomUUID(),
        reference: `settle-${Date.now()}`,
      })
      .subscribe({
        next: () => this.reload(),
        error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to settle merchant.'),
      });
  }

  private promptAmount(message: string): number | null {
    const raw = window.prompt(message);

    if (!raw) {
      return null;
    }

    const amount = Number(raw);

    return Number.isFinite(amount) && amount > 0 ? amount : null;
  }
}
