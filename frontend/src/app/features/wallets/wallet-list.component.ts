import { Component, OnInit, signal } from '@angular/core';
import { WalletApiService } from '../../core/wallet-api.service';
import { Wallet } from '../../core/models/api.models';

@Component({
  selector: 'app-wallet-list',
  standalone: true,
  template: `
    <h1>Wallets</h1>

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (wallets().length === 0) {
      <p>No wallets onboarded yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Wallet No</th>
            <th>Owner</th>
            <th>Tier</th>
            <th>Status</th>
            <th>Ledger Balance</th>
            <th>Available Balance</th>
          </tr>
        </thead>
        <tbody>
          @for (wallet of wallets(); track wallet.id) {
            <tr>
              <td>{{ wallet.wallet_no }}</td>
              <td>{{ wallet.owner_name }}</td>
              <td>Tier {{ wallet.kyc_tier }}</td>
              <td>{{ wallet.status }}</td>
              <td>{{ wallet.balance?.ledger_balance ?? '-' }}</td>
              <td>{{ wallet.balance?.available_balance ?? '-' }}</td>
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
export class WalletListComponent implements OnInit {
  wallets = signal<Wallet[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);

  constructor(private api: WalletApiService) {}

  ngOnInit(): void {
    this.api.list().subscribe({
      next: (res) => {
        this.wallets.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load wallets.');
        this.loading.set(false);
      },
    });
  }
}
