import { Component, OnInit, signal } from '@angular/core';
import { MerchantApiService } from '../../core/merchant-api.service';
import { Merchant } from '../../core/models/api.models';

@Component({
  selector: 'app-merchant-list',
  standalone: true,
  template: `
    <h1>Merchants</h1>

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
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
            <th>Available Balance</th>
          </tr>
        </thead>
        <tbody>
          @for (merchant of merchants(); track merchant.id) {
            <tr>
              <td>{{ merchant.merchant_code }}</td>
              <td>{{ merchant.business_name }}</td>
              <td>{{ merchant.status }}</td>
              <td>{{ merchant.balance?.ledger_balance ?? '-' }}</td>
              <td>{{ merchant.balance?.available_balance ?? '-' }}</td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
  styles: [`
    h1 { margin-top: 0; font-size: 1.3rem; }
    .error { color: #b3261e; }
  `],
})
export class MerchantListComponent implements OnInit {
  merchants = signal<Merchant[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);

  constructor(private api: MerchantApiService) {}

  ngOnInit(): void {
    this.api.list().subscribe({
      next: (res) => {
        this.merchants.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load merchants.');
        this.loading.set(false);
      },
    });
  }
}
