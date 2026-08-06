import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AgentApiService } from '../../core/agent-api.service';
import { Agent, AgentType } from '../../core/models/api.models';

@Component({
  selector: 'app-agent-list',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h1>Agents</h1>

    <div class="onboard-form">
      <select [(ngModel)]="newAgentType" [disabled]="registering()">
        <option value="INDIVIDUAL">Individual</option>
        <option value="BUSINESS">Business</option>
        <option value="CORPORATE">Corporate</option>
      </select>
      <input
        type="text"
        placeholder="Legal name"
        [(ngModel)]="newLegalName"
        [disabled]="registering()"
      />
      <input
        type="text"
        placeholder="Phone"
        [(ngModel)]="newPhone"
        [disabled]="registering()"
      />
      <input
        type="number"
        placeholder="Branch ID"
        [(ngModel)]="newBranchId"
        [disabled]="registering()"
      />
      <button
        (click)="register()"
        [disabled]="registering() || !newLegalName().trim() || !newPhone().trim() || !newBranchId()"
      >
        {{ registering() ? 'Registering...' : '+ Register Agent' }}
      </button>
    </div>

    @if (actionError()) {
      <p class="error">{{ actionError() }}</p>
    }

    @if (loading()) {
      <p>Loading...</p>
    } @else if (loadError()) {
      <p class="error">{{ loadError() }}</p>
    } @else if (agents().length === 0) {
      <p>No agents registered yet.</p>
    } @else {
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Legal Name</th>
            <th>Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @for (agent of agents(); track agent.id) {
            <tr>
              <td>{{ agent.agent_code }}</td>
              <td>{{ agent.legal_name }}</td>
              <td>{{ agent.agent_type }}</td>
              <td><span class="status status-{{ agent.status.toLowerCase() }}">{{ agent.status }}</span></td>
              <td class="actions">
                @if (agent.status === 'DRAFT') {
                  <button (click)="submit(agent)">Submit</button>
                }
                @if (agent.status === 'PENDING_APPROVAL') {
                  <button (click)="approve(agent)">Approve</button>
                  <button (click)="reject(agent)">Reject</button>
                }
                @if (agent.status === 'APPROVED') {
                  <button (click)="activate(agent)">Activate</button>
                }
                @if (agent.status === 'ACTIVE' || agent.status === 'RESTRICTED') {
                  <button (click)="suspend(agent)">Suspend</button>
                }
                @if (agent.status === 'SUSPENDED' || agent.status === 'RESTRICTED') {
                  <button (click)="reactivate(agent)">Reactivate</button>
                }
                @if (agent.status === 'REJECTED' || agent.status === 'TERMINATED') {
                  @if (agent.suspension_reason) {
                    <span class="rejection-reason">{{ agent.suspension_reason }}</span>
                  }
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
    .onboard-form { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .onboard-form input, .onboard-form select { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; }
    .onboard-form input[type="text"] { min-width: 180px; }
    .onboard-form input[type="number"] { width: 100px; }
    .actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .actions button { padding: 4px 10px; }
    .rejection-reason { color: #b3261e; font-size: 0.85em; font-style: italic; }
    .status { font-size: 0.85em; padding: 2px 8px; border-radius: 10px; background: #eee; }
    .status-active { background: #d7f0dd; color: #1f6f5c; }
    .status-draft { background: #eee; color: #555; }
    .status-pending_approval { background: #fbe9c9; color: #8a5d00; }
    .status-approved { background: #d9e8fb; color: #1a4d8f; }
    .status-rejected, .status-suspended, .status-terminated, .status-restricted, .status-blacklisted { background: #f6d9d5; color: #a6432f; }
  `],
})
export class AgentListComponent implements OnInit {
  agents = signal<Agent[]>([]);
  loading = signal(true);
  loadError = signal<string | null>(null);
  actionError = signal<string | null>(null);

  newAgentType = signal<AgentType>('INDIVIDUAL');
  newLegalName = signal('');
  newPhone = signal('');
  newBranchId = signal<number | null>(null);
  registering = signal(false);

  constructor(private api: AgentApiService) {}

  ngOnInit(): void {
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.api.list().subscribe({
      next: (res) => {
        this.agents.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set('Failed to load agents.');
        this.loading.set(false);
      },
    });
  }

  register(): void {
    const legalName = this.newLegalName().trim();
    const phone = this.newPhone().trim();
    const branchId = this.newBranchId();

    if (!legalName || !phone || !branchId) {
      return;
    }

    this.registering.set(true);
    this.actionError.set(null);

    this.api
      .register({
        agent_type: this.newAgentType(),
        legal_name: legalName,
        phone,
        branch_id: branchId,
      })
      .subscribe({
        next: () => {
          this.newLegalName.set('');
          this.newPhone.set('');
          this.newBranchId.set(null);
          this.registering.set(false);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(err?.error?.message ?? 'Failed to register agent.');
          this.registering.set(false);
        },
      });
  }

  submit(agent: Agent): void {
    this.actionError.set(null);

    this.api.submit(agent.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to submit agent.'),
    });
  }

  approve(agent: Agent): void {
    this.actionError.set(null);

    this.api.approve(agent.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to approve agent.'),
    });
  }

  reject(agent: Agent): void {
    const reason = window.prompt('Reason for rejecting this agent:');

    if (!reason || !reason.trim()) {
      return;
    }

    this.actionError.set(null);

    this.api.reject(agent.id, reason.trim()).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to reject agent.'),
    });
  }

  activate(agent: Agent): void {
    this.actionError.set(null);

    this.api.activate(agent.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to activate agent.'),
    });
  }

  suspend(agent: Agent): void {
    const reason = window.prompt('Reason for suspending this agent:');

    if (!reason || !reason.trim()) {
      return;
    }

    this.actionError.set(null);

    this.api.suspend(agent.id, reason.trim()).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to suspend agent.'),
    });
  }

  reactivate(agent: Agent): void {
    this.actionError.set(null);

    this.api.reactivate(agent.id).subscribe({
      next: () => this.reload(),
      error: (err) => this.actionError.set(err?.error?.message ?? 'Failed to reactivate agent.'),
    });
  }
}
