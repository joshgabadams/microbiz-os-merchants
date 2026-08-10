import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AgentApiService } from '../../core/agent-api.service';
import {
  Agent,
  AgentType,
} from '../../core/models/api.models';

@Component({
  selector: 'app-agent-list',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <div>
        <h1>Agents</h1>
        <p class="subtitle">
          Register, review and manage MicroBiz agency banking agents.
        </p>
      </div>
    </div>

    <section class="card">
      <h2>Register Agent</h2>

      <div class="onboard-form">
        <select
          [(ngModel)]="newAgentType"
          [disabled]="registering()"
        >
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
          [disabled]="
            registering() ||
            !newLegalName().trim() ||
            !newPhone().trim() ||
            !newBranchId()
          "
        >
          {{
            registering()
              ? 'Registering...'
              : '+ Register Agent'
          }}
        </button>
      </div>
    </section>

    @if (actionError()) {
      <div class="error-box">
        {{ actionError() }}
      </div>
    }

    <section class="card">
      <div class="section-header">
        <div>
          <h2>Agent Registry</h2>
          <p>
            {{ agents().length }} agent(s) currently displayed.
          </p>
        </div>

        <button
          class="secondary-button"
          (click)="reload()"
          [disabled]="loading()"
        >
          Refresh
        </button>
      </div>

      @if (loading()) {
        <p>Loading agents...</p>
      } @else if (loadError()) {
        <div class="error-box">
          {{ loadError() }}
        </div>
      } @else if (agents().length === 0) {
        <p>No agents registered yet.</p>
      } @else {
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Code</th>
                <th>Legal Name</th>
                <th>Type</th>
                <th>KYC</th>
                <th>Risk</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              @for (agent of agents(); track agent.id) {
                <tr>
                  <td>
                    <strong>{{ agent.agent_code }}</strong>
                  </td>

                  <td>
                    <div class="agent-name">
                      <strong>{{ agent.legal_name }}</strong>

                      @if (agent.trading_name) {
                        <span>{{ agent.trading_name }}</span>
                      }
                    </div>
                  </td>

                  <td>
                    {{ agent.agent_type }}
                  </td>

                  <td>
                    <span class="meta-badge">
                      {{ agent.kyc_status || 'NOT STARTED' }}
                    </span>
                  </td>

                  <td>
                    <span class="meta-badge">
                      {{ agent.risk_rating || 'UNRATED' }}
                    </span>
                  </td>

                  <td>
                    <span
                      class="status"
                      [class]="statusClass(agent.status)"
                    >
                      {{ displayStatus(agent.status) }}
                    </span>
                  </td>

                  <td class="actions">
                    <a
                      class="open-link"
                      [routerLink]="['/agents', agent.id]"
                    >
                      Open Agent
                    </a>

                    @if (agent.status === 'DRAFT') {
                      <button
                        (click)="submit(agent)"
                        [disabled]="workingAgentId() === agent.id"
                      >
                        Submit
                      </button>
                    }

                    @if (
                      agent.status === 'PENDING_APPROVAL'
                    ) {
                      <button
                        (click)="approve(agent)"
                        [disabled]="workingAgentId() === agent.id"
                      >
                        Approve
                      </button>

                      <button
                        class="danger"
                        (click)="reject(agent)"
                        [disabled]="workingAgentId() === agent.id"
                      >
                        Reject
                      </button>
                    }

                    @if (
                      agent.status === 'ACTIVE' ||
                      agent.status === 'RESTRICTED'
                    ) {
                      <button
                        class="danger"
                        (click)="suspend(agent)"
                        [disabled]="workingAgentId() === agent.id"
                      >
                        Suspend
                      </button>
                    }

                    @if (
                      agent.status === 'SUSPENDED' ||
                      agent.status === 'RESTRICTED'
                    ) {
                      <button
                        (click)="reactivate(agent)"
                        [disabled]="workingAgentId() === agent.id"
                      >
                        Reactivate
                      </button>
                    }

                    @if (
                      agent.status === 'REJECTED' ||
                      agent.status === 'TERMINATED'
                    ) {
                      @if (agent.suspension_reason) {
                        <span class="rejection-reason">
                          {{ agent.suspension_reason }}
                        </span>
                      }
                    }

                    @if (
                      agent.status ===
                        'PENDING_LOCATION_VERIFICATION' ||
                      agent.status ===
                        'PENDING_COMPLIANCE_REVIEW' ||
                      agent.status === 'AGREEMENT_PENDING' ||
                      agent.status === 'TRAINING_PENDING' ||
                      agent.status === 'TERMINAL_PENDING'
                    ) {
                      <span class="continue-note">
                        Continue in Agent Detail
                      </span>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </section>
  `,
  styles: [`
    :host {
      display: block;
    }

    .page-header {
      margin-bottom: 18px;
    }

    h1 {
      margin: 0;
      font-size: 1.5rem;
      color: #1e2761;
    }

    h2 {
      margin: 0 0 12px;
      font-size: 1.05rem;
      color: #1e2761;
    }

    .subtitle {
      margin: 5px 0 0;
      color: #666;
      font-size: 0.9rem;
    }

    .card {
      background: white;
      border: 1px solid #e1e5ee;
      border-radius: 10px;
      padding: 18px;
      margin-bottom: 18px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      gap: 15px;
      align-items: flex-start;
      margin-bottom: 14px;
    }

    .section-header h2 {
      margin-bottom: 4px;
    }

    .section-header p {
      margin: 0;
      color: #777;
      font-size: 0.85rem;
    }

    .onboard-form {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .onboard-form input,
    .onboard-form select {
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      background: white;
    }

    .onboard-form input[type="text"] {
      min-width: 180px;
    }

    .onboard-form input[type="number"] {
      width: 110px;
    }

    button,
    .open-link {
      padding: 6px 10px;
      border: 0;
      border-radius: 5px;
      font-size: 0.82rem;
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;
    }

    button {
      background: #1e2761;
      color: white;
    }

    button:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    button.danger {
      background: #a6432f;
    }

    .secondary-button {
      background: #eef1f8;
      color: #1e2761;
      border: 1px solid #ccd3e5;
    }

    .open-link {
      display: inline-block;
      background: #1e2761;
      color: white;
    }

    .error-box {
      margin-bottom: 15px;
      padding: 10px 12px;
      background: #f6d9d5;
      color: #a6432f;
      border-radius: 6px;
    }

    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px 8px;
      border-bottom: 1px solid #eee;
      text-align: left;
      vertical-align: middle;
      font-size: 0.84rem;
    }

    th {
      color: #555;
      font-weight: 600;
      background: #fafbfe;
    }

    tbody tr:hover {
      background: #fafbfe;
    }

    .agent-name {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .agent-name span {
      color: #777;
      font-size: 0.78rem;
    }

    .actions {
      display: flex;
      gap: 6px;
      align-items: center;
      flex-wrap: wrap;
    }

    .status,
    .meta-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.74rem;
      white-space: nowrap;
    }

    .meta-badge {
      background: #eef1f8;
      color: #4d5875;
    }

    .status-draft {
      background: #eeeeee;
      color: #555;
    }

    .status-pending {
      background: #fbe9c9;
      color: #8a5d00;
    }

    .status-approved {
      background: #d9e8fb;
      color: #1a4d8f;
    }

    .status-active {
      background: #d7f0dd;
      color: #1f6f5c;
    }

    .status-danger {
      background: #f6d9d5;
      color: #a6432f;
    }

    .status-neutral {
      background: #eef1f8;
      color: #4d5875;
    }

    .rejection-reason {
      color: #b3261e;
      font-size: 0.78rem;
      font-style: italic;
    }

    .continue-note {
      color: #6f5b00;
      background: #fff3d5;
      border-radius: 10px;
      padding: 3px 7px;
      font-size: 0.72rem;
      white-space: nowrap;
    }
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

  workingAgentId = signal<number | null>(null);

  constructor(
    private api: AgentApiService
  ) {}

  ngOnInit(): void {
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.loadError.set(null);

    this.api.list().subscribe({
      next: (res) => {
        this.agents.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.loadError.set(
          err?.error?.message ??
            'Failed to load agents.'
        );

        this.loading.set(false);
      },
    });
  }

  register(): void {
    const legalName =
      this.newLegalName().trim();

    const phone =
      this.newPhone().trim();

    const branchId =
      this.newBranchId();

    if (
      !legalName ||
      !phone ||
      !branchId
    ) {
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
          this.actionError.set(
            err?.error?.message ??
              'Failed to register agent.'
          );

          this.registering.set(false);
        },
      });
  }

  submit(agent: Agent): void {
    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .submit(agent.id)
      .subscribe({
        next: () => {
          this.workingAgentId.set(null);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(
            err?.error?.message ??
              'Failed to submit agent.'
          );

          this.workingAgentId.set(null);
        },
      });
  }

  approve(agent: Agent): void {
    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .approve(agent.id)
      .subscribe({
        next: () => {
          this.workingAgentId.set(null);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(
            err?.error?.message ??
              'Failed to approve agent.'
          );

          this.workingAgentId.set(null);
        },
      });
  }

  reject(agent: Agent): void {
    const reason =
      window.prompt(
        'Reason for rejecting this agent:'
      );

    if (!reason?.trim()) {
      return;
    }

    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .reject(
        agent.id,
        reason.trim()
      )
      .subscribe({
        next: () => {
          this.workingAgentId.set(null);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(
            err?.error?.message ??
              'Failed to reject agent.'
          );

          this.workingAgentId.set(null);
        },
      });
  }

  suspend(agent: Agent): void {
    const reason =
      window.prompt(
        'Reason for suspending this agent:'
      );

    if (!reason?.trim()) {
      return;
    }

    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .suspend(
        agent.id,
        reason.trim()
      )
      .subscribe({
        next: () => {
          this.workingAgentId.set(null);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(
            err?.error?.message ??
              'Failed to suspend agent.'
          );

          this.workingAgentId.set(null);
        },
      });
  }

  reactivate(agent: Agent): void {
    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .reactivate(agent.id)
      .subscribe({
        next: () => {
          this.workingAgentId.set(null);
          this.reload();
        },
        error: (err) => {
          this.actionError.set(
            err?.error?.message ??
              'Failed to reactivate agent.'
          );

          this.workingAgentId.set(null);
        },
      });
  }

  displayStatus(status: string): string {
    return status
      .replaceAll('_', ' ')
      .toLowerCase()
      .replace(/\b\w/g, (character) =>
        character.toUpperCase()
      );
  }

  statusClass(status: string): string {
    switch (status) {
      case 'DRAFT':
        return 'status status-draft';

      case 'ACTIVE':
        return 'status status-active';

      case 'APPROVED':
        return 'status status-approved';

      case 'REJECTED':
      case 'RESTRICTED':
      case 'SUSPENDED':
      case 'TERMINATED':
      case 'BLACKLISTED':
      case 'EXPIRED':
        return 'status status-danger';

      case 'PENDING_KYC':
      case 'PENDING_LOCATION_VERIFICATION':
      case 'PENDING_COMPLIANCE_REVIEW':
      case 'PENDING_APPROVAL':
      case 'AGREEMENT_PENDING':
      case 'TRAINING_PENDING':
      case 'TERMINAL_PENDING':
        return 'status status-pending';

      default:
        return 'status status-neutral';
    }
  }
}
