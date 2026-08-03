import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApprovalApiService } from '../../core/approval-api.service';
import { ApprovalRequestModel } from '../../core/models/api.models';

@Component({
  selector: 'app-approval-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Pending Approvals</h1>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (error()) {
      <p class="error">{{ error() }}</p>
    } @else if (requests().length === 0) {
      <p>No pending approvals.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Request No</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Maker Note</th>
            <th>Checker Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (request of requests(); track request.id) {
            <tr>
              <td>{{ request.request_no }}</td>
              <td>{{ request.request_type }}</td>
              <td>{{ request.amount }} {{ request.currency }}</td>
              <td>{{ request.maker_note ?? '-' }}</td>
              <td>
                <input
                  type="text"
                  placeholder="Optional note"
                  [(ngModel)]="checkerNotes[request.id]"
                  name="note-{{ request.id }}"
                />
              </td>
              <td class="actions">
                <button (click)="approve(request.id)" [disabled]="busyId() === request.id" class="approve">Approve</button>
                <button (click)="reject(request.id)" [disabled]="busyId() === request.id" class="reject">Reject</button>
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
    .actions { display: flex; gap: 0.4rem; }
    .actions button {
      padding: 0.3rem 0.7rem;
      border-radius: 6px;
      font-size: 0.8rem;
      border: 1px solid transparent;
    }
    .approve { background: #1a7f37; color: white; }
    .reject { background: #b3261e; color: white; }
    input[type="text"] {
      padding: 0.3rem 0.5rem;
      border: 1px solid #d5d9e6;
      border-radius: 6px;
      font-size: 0.85rem;
      width: 140px;
    }
  `],
})
export class ApprovalListComponent implements OnInit {
  requests = signal<ApprovalRequestModel[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);
  busyId = signal<number | null>(null);
  actionError = signal<string | null>(null);

  checkerNotes: Record<number, string> = {};

  constructor(private api: ApprovalApiService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api.pending().subscribe({
      next: (res) => {
        this.requests.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Failed to load pending approvals.');
        this.loading.set(false);
      },
    });
  }

  approve(id: number): void {
    this.busyId.set(id);
    this.actionError.set(null);

    this.api.approve(id, this.checkerNotes[id]).subscribe({
      next: () => {
        this.busyId.set(null);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Approval failed.');
      },
    });
  }

  reject(id: number): void {
    this.busyId.set(id);
    this.actionError.set(null);

    this.api.reject(id, this.checkerNotes[id]).subscribe({
      next: () => {
        this.busyId.set(null);
        this.load();
      },
      error: (err) => {
        this.busyId.set(null);
        this.actionError.set(err?.error?.message ?? 'Rejection failed.');
      },
    });
  }
}
