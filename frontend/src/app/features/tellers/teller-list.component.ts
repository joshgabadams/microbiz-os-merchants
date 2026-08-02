import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TellerApiService } from '../../core/teller-api.service';
import { TellerModel } from '../../core/models/api.models';

@Component({
  selector: 'app-teller-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Tellers</h1>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }
    @if (actionMessage()) {
      <p class="success">{{ actionMessage() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (tellers().length === 0) {
      <p>No tellers configured yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Display Name</th>
            <th>Status</th>
            <th>Daily Limit</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (teller of tellers(); track teller.id) {
            <tr>
              <td>{{ teller.teller_code }}</td>
              <td>{{ teller.display_name }}</td>
              <td>{{ teller.status }}</td>
              <td>{{ teller.daily_limit }}</td>
              <td class="actions">
                @if (teller.status !== 'OPEN') {
                  <button (click)="openTeller(teller.id)" [disabled]="busyId() === teller.id">Open</button>
                }
                @if (teller.status === 'OPEN') {
                  <button (click)="closeTeller(teller.id)" [disabled]="busyId() === teller.id">Close</button>
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
    .success { color: #1a7f37; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border: 1px solid #1e2761;
      background: white;
      color: #1e2761;
      border-radius: 6px;
      font-size: 0.8rem;
    }
  `],
})
export class TellerListComponent implements OnInit {
  tellers = signal<TellerModel[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);
  actionMessage = signal<string | null>(null);

  constructor(private api: TellerApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (res) => {
        this.tellers.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load tellers.');
        this.loading.set(false);
      },
    });
  }

  openTeller(tellerId: number): void {
    this.runAction(tellerId, this.api.open({ teller_id: tellerId }));
  }

  closeTeller(tellerId: number): void {
    this.runAction(tellerId, this.api.close({ teller_id: tellerId }));
  }

  private runAction(tellerId: number, obs: ReturnType<TellerApiService['open']>): void {
    this.actionError.set(null);
    this.actionMessage.set(null);
    this.busyId.set(tellerId);

    obs.subscribe({
      next: (res) => {
        this.busyId.set(null);
        this.actionMessage.set(res.message);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Action failed.');
      },
    });
  }
}
