import { Component, OnInit, computed, signal } from '@angular/core';
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
      <h2>
        <svg class="section-icon" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Register Agent
      </h2>

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

    <div class="stat-grid">
      <div class="stat-card">
        <span class="stat-value">{{ agents().length }}</span>
        <span class="stat-label">Total</span>
      </div>
      <div class="stat-card">
        <span class="stat-value">{{ activeCount() }}</span>
        <span class="stat-label">Active</span>
      </div>
      <div class="stat-card" [class.stat-warning]="pendingCount() > 0">
        <span class="stat-value">{{ pendingCount() }}</span>
        <span class="stat-label">Pending Onboarding</span>
      </div>
      <div class="stat-card" [class.stat-danger]="issueCount() > 0">
        <span class="stat-value">{{ issueCount() }}</span>
        <span class="stat-label">Suspended / Restricted</span>
      </div>
    </div>

    <section class="card">
      <div class="section-header">
        <div>
          <h2>
            <svg class="section-icon" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 11a3 3 0 1 0 0-6M21 20c0-2.8-1.9-5.1-4.5-5.8"/></svg>
            Agent Registry
          </h2>
          <p>
            {{ filteredAgents().length }} of {{ agents().length }} agent(s) shown.
          </p>
        </div>

        <button
          class="secondary-button"
          (click)="reload()"
          [disabled]="loading()"
        >
          <svg class="btn-icon" viewBox="0 0 24 24"><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M4 4v5h5M20 20v-5h-5"/></svg>
          Refresh
        </button>
      </div>

      <div class="filters">
        <div class="search-field">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input
            type="text"
            placeholder="Search by name or agent code..."
            [(ngModel)]="searchTerm"
          />
        </div>

        <select [(ngModel)]="statusFilter">
          <option value="">All statuses</option>
          <option value="DRAFT">Draft</option>
          <option value="PENDING_KYC">Pending KYC</option>
          <option value="PENDING_LOCATION_VERIFICATION">Pending Location Verification</option>
          <option value="PENDING_COMPLIANCE_REVIEW">Pending Compliance Review</option>
          <option value="PENDING_APPROVAL">Pending Approval</option>
          <option value="APPROVED">Approved</option>
          <option value="AGREEMENT_PENDING">Agreement Pending</option>
          <option value="TRAINING_PENDING">Training Pending</option>
          <option value="TERMINAL_PENDING">Terminal Pending</option>
          <option value="ACTIVE">Active</option>
          <option value="RESTRICTED">Restricted</option>
          <option value="SUSPENDED">Suspended</option>
          <option value="REJECTED">Rejected</option>
          <option value="TERMINATED">Terminated</option>
        </select>

        @if (searchTerm() || statusFilter()) {
          <button class="btn-outline" (click)="clearFilters()">Clear</button>
        }
      </div>

      @if (loading()) {
        <div class="empty-state">Loading agents...</div>
      } @else if (loadError()) {
        <div class="error-box">
          {{ loadError() }}
        </div>
      } @else if (agents().length === 0) {
        <div class="empty-state">No agents registered yet.</div>
      } @else if (filteredAgents().length === 0) {
        <div class="empty-state">No agents match these filters.</div>
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
              @for (agent of filteredAgents(); track agent.id) {
                <tr>
                  <td class="mono">
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
                        (click)="toggleRejectForm(agent.id)"
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
                        (click)="toggleSuspendForm(agent.id)"
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

                @if (rejectFormId() === agent.id) {
                  <tr class="expanded-row">
                    <td colspan="7">
                      <div class="inline-form">
                        <label>
                          Reason for rejecting this agent
                          <textarea
                            rows="2"
                            [(ngModel)]="rejectReason"
                            [ngModelOptions]="{ standalone: true }"
                          ></textarea>
                        </label>
                        <div class="inline-form-actions">
                          <button class="btn-outline" (click)="rejectFormId.set(null)">
                            Cancel
                          </button>
                          <button
                            class="danger"
                            (click)="reject(agent)"
                            [disabled]="!rejectReason.trim()"
                          >
                            Confirm Rejection
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                }

                @if (suspendFormId() === agent.id) {
                  <tr class="expanded-row">
                    <td colspan="7">
                      <div class="inline-form">
                        <label>
                          Reason for suspending this agent
                          <textarea
                            rows="2"
                            [(ngModel)]="suspendReason"
                            [ngModelOptions]="{ standalone: true }"
                          ></textarea>
                        </label>
                        <div class="inline-form-actions">
                          <button class="btn-outline" (click)="suspendFormId.set(null)">
                            Cancel
                          </button>
                          <button
                            class="danger"
                            (click)="suspend(agent)"
                            [disabled]="!suspendReason.trim()"
                          >
                            Confirm Suspension
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                }
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
      font-family: var(--font-sans);
      color: var(--color-foreground);
    }

    .page-header {
      margin-bottom: var(--space-5);
    }

    h1 {
      margin: 0;
      font-size: var(--font-size-2xl);
      font-weight: 700;
      color: var(--color-primary);
    }

    h2 {
      margin: 0 0 8px;
      font-size: var(--font-size-lg);
      font-weight: 600;
      color: var(--color-primary);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .section-icon {
      width: 18px;
      height: 18px;
      flex: 0 0 18px;
      fill: none;
      stroke: var(--color-accent);
      stroke-width: 1.7;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .subtitle {
      margin: 5px 0 0;
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-5);
      margin-bottom: var(--space-4);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      gap: var(--space-4);
      align-items: flex-start;
      margin-bottom: var(--space-4);
    }

    .section-header h2 {
      margin-bottom: 4px;
    }

    .section-header p {
      margin: 0;
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    .stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: var(--space-3);
      margin-bottom: var(--space-4);
    }

    .stat-card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-4);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .stat-value {
      font-size: var(--font-size-2xl);
      font-weight: 700;
      color: var(--color-primary);
      line-height: 1.1;
    }

    .stat-label {
      font-size: var(--font-size-xs);
      color: var(--color-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-weight: 600;
    }

    .stat-card.stat-warning .stat-value { color: var(--color-warning); }
    .stat-card.stat-danger .stat-value { color: var(--color-danger); }

    .onboard-form {
      display: flex;
      gap: var(--space-2);
      flex-wrap: wrap;
      align-items: center;
    }

    .onboard-form input,
    .onboard-form select {
      padding: 8px 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: var(--color-surface);
      font-size: var(--font-size-sm);
      transition: border-color var(--transition-fast);
    }

    .onboard-form input:focus-visible,
    .onboard-form select:focus-visible {
      border-color: var(--color-primary);
    }

    .onboard-form input[type="text"] {
      min-width: 180px;
    }

    .onboard-form input[type="number"] {
      width: 110px;
    }

    .filters {
      display: flex;
      gap: var(--space-3);
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: var(--space-4);
    }

    .search-field {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: var(--color-surface);
      flex: 1 1 260px;
      max-width: 360px;
      transition: border-color var(--transition-fast);
    }

    .search-field:focus-within {
      border-color: var(--color-primary);
    }

    .search-field svg {
      width: 15px;
      height: 15px;
      flex: 0 0 15px;
      fill: none;
      stroke: var(--color-muted);
      stroke-width: 2;
      stroke-linecap: round;
    }

    .search-field input {
      border: none;
      outline: none;
      padding: 8px 0;
      font-size: var(--font-size-sm);
      background: transparent;
      width: 100%;
    }

    .filters > select {
      padding: 8px 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: var(--color-surface);
      font-size: var(--font-size-sm);
    }

    button,
    .open-link {
      padding: 7px 12px;
      border: 1px solid transparent;
      border-radius: var(--radius-sm);
      font-size: var(--font-size-sm);
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background var(--transition-fast), filter var(--transition-fast);
    }

    .btn-icon {
      width: 14px;
      height: 14px;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    button {
      background: var(--color-primary);
      color: var(--color-on-primary);
    }

    button:hover:not(:disabled) {
      background: var(--color-primary-hover);
    }

    button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    button.danger {
      background: var(--color-danger);
    }

    button.danger:hover:not(:disabled) {
      filter: brightness(0.92);
    }

    button.btn-outline {
      background: transparent;
      border-color: var(--color-border);
      color: var(--color-foreground);
    }

    button.btn-outline:hover:not(:disabled) {
      background: var(--color-muted-bg);
    }

    .secondary-button {
      background: transparent;
      color: var(--color-primary);
      border: 1px solid var(--color-border);
    }

    .secondary-button:hover:not(:disabled) {
      background: var(--color-muted-bg);
    }

    .open-link {
      display: inline-block;
      background: var(--color-primary);
      color: var(--color-on-primary);
    }

    .open-link:hover {
      background: var(--color-primary-hover);
    }

    .error-box {
      margin-bottom: var(--space-4);
      padding: var(--space-3);
      background: var(--color-danger-bg);
      color: var(--color-danger);
      border-radius: var(--radius-md);
      font-size: var(--font-size-sm);
    }

    .empty-state {
      padding: var(--space-6) var(--space-4);
      border: 1px dashed var(--color-border);
      border-radius: var(--radius-md);
      color: var(--color-muted);
      background: var(--color-background);
      font-size: var(--font-size-sm);
      text-align: center;
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
      padding: var(--space-3) var(--space-2);
      border-bottom: 1px solid var(--color-border);
      text-align: left;
      vertical-align: middle;
      font-size: var(--font-size-sm);
    }

    th {
      color: var(--color-muted);
      background: var(--color-background);
      font-size: var(--font-size-xs);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .mono {
      font-family: ui-monospace, 'SF Mono', Menlo, monospace;
      font-size: var(--font-size-xs);
    }

    tbody tr:hover {
      background: var(--color-background);
    }

    .agent-name {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .agent-name span {
      color: var(--color-muted);
      font-size: var(--font-size-xs);
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
      padding: 4px 10px;
      border-radius: 999px;
      font-size: var(--font-size-xs);
      font-weight: 600;
      white-space: nowrap;
    }

    .meta-badge {
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    .status-draft {
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    .status-pending {
      background: var(--color-warning-bg);
      color: var(--color-warning);
    }

    .status-approved {
      background: var(--color-info-bg);
      color: var(--color-info);
    }

    .status-active {
      background: var(--color-success-bg);
      color: var(--color-success);
    }

    .status-danger {
      background: var(--color-danger-bg);
      color: var(--color-danger);
    }

    .status-neutral {
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    .rejection-reason {
      color: var(--color-danger);
      font-size: var(--font-size-xs);
      font-style: italic;
    }

    .continue-note {
      color: var(--color-warning);
      background: var(--color-warning-bg);
      border-radius: 999px;
      padding: 3px 8px;
      font-size: var(--font-size-xs);
      font-weight: 600;
      white-space: nowrap;
    }

    .expanded-row td {
      background: var(--color-background);
      padding: var(--space-4);
    }

    .inline-form label {
      display: flex;
      flex-direction: column;
      gap: 5px;
      font-size: var(--font-size-sm);
      font-weight: 500;
      max-width: 480px;
    }

    .inline-form textarea {
      box-sizing: border-box;
      width: 100%;
      padding: 8px 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      font-size: var(--font-size-sm);
      font-family: inherit;
      resize: vertical;
    }

    .inline-form-actions {
      display: flex;
      gap: var(--space-2);
      margin-top: var(--space-2);
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

  searchTerm = signal('');
  statusFilter = signal('');

  rejectFormId = signal<number | null>(null);
  suspendFormId = signal<number | null>(null);
  rejectReason = '';
  suspendReason = '';

  filteredAgents = computed(() => {
    const term = this.searchTerm().trim().toLowerCase();
    const status = this.statusFilter();

    return this.agents().filter((agent) => {
      const matchesTerm =
        !term ||
        agent.legal_name.toLowerCase().includes(term) ||
        agent.agent_code.toLowerCase().includes(term) ||
        (agent.trading_name ?? '').toLowerCase().includes(term);

      const matchesStatus = !status || agent.status === status;

      return matchesTerm && matchesStatus;
    });
  });

  activeCount = computed(
    () => this.agents().filter((a) => a.status === 'ACTIVE').length
  );

  pendingCount = computed(
    () =>
      this.agents().filter((a) =>
        [
          'DRAFT',
          'PENDING_KYC',
          'PENDING_LOCATION_VERIFICATION',
          'PENDING_COMPLIANCE_REVIEW',
          'PENDING_APPROVAL',
          'APPROVED',
          'AGREEMENT_PENDING',
          'TRAINING_PENDING',
          'TERMINAL_PENDING',
        ].includes(a.status)
      ).length
  );

  issueCount = computed(
    () =>
      this.agents().filter((a) =>
        ['SUSPENDED', 'RESTRICTED'].includes(a.status)
      ).length
  );

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

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
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

  toggleRejectForm(agentId: number): void {
    this.rejectReason = '';
    this.suspendFormId.set(null);
    this.rejectFormId.update((current) =>
      current === agentId ? null : agentId
    );
  }

  toggleSuspendForm(agentId: number): void {
    this.suspendReason = '';
    this.rejectFormId.set(null);
    this.suspendFormId.update((current) =>
      current === agentId ? null : agentId
    );
  }

  reject(agent: Agent): void {
    const reason = this.rejectReason.trim();

    if (!reason) {
      return;
    }

    this.rejectFormId.set(null);
    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .reject(
        agent.id,
        reason
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
    const reason = this.suspendReason.trim();

    if (!reason) {
      return;
    }

    this.suspendFormId.set(null);
    this.actionError.set(null);
    this.workingAgentId.set(agent.id);

    this.api
      .suspend(
        agent.id,
        reason
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
