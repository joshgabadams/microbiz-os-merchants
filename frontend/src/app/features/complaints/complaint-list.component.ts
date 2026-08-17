import { Component, OnInit, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AgentComplaintApiService } from '../../core/agent-complaint-api.service';
import { AgentComplaint } from '../../core/models/api.models';

@Component({
  selector: 'app-complaint-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page">
      <div class="page-header">
        <div>
          <h1>Agent Complaints</h1>
          <p>Customer and agent-conduct complaint tracking, SLA and escalation.</p>
        </div>

        <button
          class="btn-outline"
          (click)="toggleCreateForm()"
          [disabled]="working()"
        >
          {{ showCreateForm() ? 'Cancel' : '+ Log Complaint' }}
        </button>
      </div>

      <div class="stat-grid">
        <div class="stat-card">
          <span class="stat-value">{{ openCount() }}</span>
          <span class="stat-label">Open</span>
        </div>
        <div class="stat-card" [class.stat-warning]="overdueCount() > 0">
          <span class="stat-value">{{ overdueCount() }}</span>
          <span class="stat-label">Overdue</span>
        </div>
        <div class="stat-card" [class.stat-danger]="escalatedCount() > 0">
          <span class="stat-value">{{ escalatedCount() }}</span>
          <span class="stat-label">Escalated</span>
        </div>
        <div class="stat-card">
          <span class="stat-value">{{ complaints().length }}</span>
          <span class="stat-label">Showing</span>
        </div>
      </div>

      @if (error()) {
        <div class="error-box">{{ error() }}</div>
      }

      @if (showCreateForm()) {
        <section class="card">
          <div class="form-grid">
            <label>
              Complainant Name
              <input [(ngModel)]="form.complainant_name" />
            </label>

            <label>
              Phone
              <input [(ngModel)]="form.complainant_phone" />
            </label>

            <label>
              Email
              <input [(ngModel)]="form.complainant_email" />
            </label>

            <label>
              Channel
              <select [(ngModel)]="form.channel">
                <option value="BRANCH">Branch</option>
                <option value="PHONE">Phone</option>
                <option value="EMAIL">Email</option>
                <option value="WEB">Web</option>
                <option value="AGENT">Agent</option>
                <option value="OTHER">Other</option>
              </select>
            </label>

            <label>
              Category
              <select [(ngModel)]="form.category">
                <option value="CASH_IN">Cash-In</option>
                <option value="CASH_OUT">Cash-Out</option>
                <option value="TRANSFER">Transfer</option>
                <option value="FEES">Fees</option>
                <option value="AGENT_CONDUCT">Agent Conduct</option>
                <option value="SERVICE_FAILURE">Service Failure</option>
                <option value="FRAUD_SUSPECTED">Fraud Suspected</option>
                <option value="OTHER">Other</option>
              </select>
            </label>

            <label>
              Priority
              <select [(ngModel)]="form.priority">
                <option value="LOW">Low</option>
                <option value="NORMAL">Normal</option>
                <option value="HIGH">High</option>
                <option value="CRITICAL">Critical</option>
              </select>
            </label>

            <label>
              Agent ID (optional)
              <input type="number" [(ngModel)]="form.agent_id" />
            </label>

            <label>
              Disputed Amount (optional)
              <input type="number" [(ngModel)]="form.disputed_amount" />
            </label>

            <label class="full-width">
              Subject
              <input [(ngModel)]="form.subject" />
            </label>

            <label class="full-width">
              Description
              <textarea rows="3" [(ngModel)]="form.description"></textarea>
            </label>

            <div class="form-actions">
              <button (click)="create()" [disabled]="working()">
                {{ working() ? 'Logging...' : 'Log Complaint' }}
              </button>
            </div>
          </div>
        </section>
      }

      <section class="card">
        <div class="filters">
          <select [(ngModel)]="filterStatus" (ngModelChange)="reload()">
            <option value="">All statuses</option>
            <option value="OPEN">Open</option>
            <option value="ACKNOWLEDGED">Acknowledged</option>
            <option value="IN_PROGRESS">In Progress</option>
            <option value="ESCALATED">Escalated</option>
            <option value="RESOLVED">Resolved</option>
            <option value="CLOSED">Closed</option>
          </select>

          <label class="checkbox-field">
            <input
              type="checkbox"
              [(ngModel)]="filterOverdue"
              (ngModelChange)="reload()"
            />
            Overdue only
          </label>

          <button class="btn-outline" (click)="reload()">
            <svg viewBox="0 0 24 24" class="btn-icon"><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M4 4v5h5M20 20v-5h-5"/></svg>
            Refresh
          </button>
        </div>

        @if (loading()) {
          <div class="empty-state">Loading...</div>
        } @else if (complaints().length === 0) {
          <div class="empty-state">
            <svg viewBox="0 0 24 24" class="empty-icon"><path d="M5 3v18M5 4h11l-2 4 2 4H5"/></svg>
            No complaints match these filters.
          </div>
        } @else {
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Subject</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Due</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @for (c of complaints(); track c.id) {
                  <tr>
                    <td class="mono">{{ c.complaint_no }}</td>
                    <td>{{ c.subject }}</td>
                    <td>{{ displayStatus(c.category) }}</td>
                    <td>
                      <span
                        class="status-badge"
                        [class]="priorityClass(c.priority)"
                      >
                        {{ displayStatus(c.priority) }}
                      </span>
                    </td>
                    <td>
                      <span class="status-badge" [class]="statusClass(c.status)">
                        {{ displayStatus(c.status) }}
                      </span>
                    </td>
                    <td>{{ formatDate(c.due_at) }}</td>
                    <td>
                      <button
                        class="btn-outline"
                        (click)="toggleExpanded(c.id)"
                      >
                        {{ expandedId() === c.id ? 'Hide' : 'Manage' }}
                      </button>
                    </td>
                  </tr>

                  @if (expandedId() === c.id) {
                    <tr class="expanded-row">
                      <td colspan="7">
                        <div class="detail">
                          <p>{{ c.description }}</p>

                          @if (c.disputed_amount) {
                            <p class="muted">
                              Disputed amount: {{ c.disputed_amount }}
                            </p>
                          }

                          <div class="actions">
                            @if (c.status === 'OPEN') {
                              <button
                                class="btn-success"
                                (click)="acknowledge(c)"
                                [disabled]="working()"
                              >
                                Acknowledge
                              </button>
                            }

                            @if (
                              c.status === 'ACKNOWLEDGED' ||
                              c.status === 'ESCALATED'
                            ) {
                              <button
                                (click)="startProgress(c)"
                                [disabled]="working()"
                              >
                                Start Progress
                              </button>
                            }

                            @if (
                              c.status !== 'RESOLVED' && c.status !== 'CLOSED'
                            ) {
                              <button
                                class="btn-outline"
                                (click)="toggleEscalateForm(c.id)"
                                [disabled]="working()"
                              >
                                Escalate
                              </button>
                            }

                            @if (
                              c.status === 'ACKNOWLEDGED' ||
                              c.status === 'IN_PROGRESS' ||
                              c.status === 'ESCALATED'
                            ) {
                              <button
                                class="btn-success"
                                (click)="toggleResolveForm(c.id)"
                                [disabled]="working()"
                              >
                                Resolve
                              </button>
                            }

                            @if (c.status === 'RESOLVED') {
                              <button
                                class="btn-success"
                                (click)="close(c)"
                                [disabled]="working()"
                              >
                                Close
                              </button>
                            }
                          </div>

                          @if (escalateFormId() === c.id) {
                            <div class="inline-form">
                              <label>
                                Escalation reason
                                <textarea
                                  rows="2"
                                  [(ngModel)]="escalateReason"
                                  [ngModelOptions]="{ standalone: true }"
                                  placeholder="Why does this need management review?"
                                ></textarea>
                              </label>
                              <div class="inline-form-actions">
                                <button
                                  class="btn-outline"
                                  (click)="escalateFormId.set(null)"
                                >
                                  Cancel
                                </button>
                                <button
                                  (click)="escalate(c)"
                                  [disabled]="working() || !escalateReason.trim()"
                                >
                                  Confirm Escalation
                                </button>
                              </div>
                            </div>
                          }

                          @if (resolveFormId() === c.id) {
                            <div class="inline-form">
                              <label>
                                Resolution summary
                                <textarea
                                  rows="2"
                                  [(ngModel)]="resolveSummary"
                                  [ngModelOptions]="{ standalone: true }"
                                  placeholder="What was done to resolve this?"
                                ></textarea>
                              </label>
                              <div class="inline-form-actions">
                                <button
                                  class="btn-outline"
                                  (click)="resolveFormId.set(null)"
                                >
                                  Cancel
                                </button>
                                <button
                                  class="btn-success"
                                  (click)="resolve(c)"
                                  [disabled]="working() || !resolveSummary.trim()"
                                >
                                  Confirm Resolution
                                </button>
                              </div>
                            </div>
                          }
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
    </div>
  `,
  styles: [`
    :host { display: block; font-family: var(--font-sans); color: var(--color-foreground); }
    .page { max-width: 1240px; margin: 0 auto; }
    .page-header { display: flex; justify-content: space-between; gap: var(--space-5); align-items: flex-start; margin-bottom: var(--space-4); }
    h1 { margin: 0 0 4px; font-size: var(--font-size-2xl); font-weight: 700; color: var(--color-primary); }
    p { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }

    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-4); }
    .stat-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-4); display: flex; flex-direction: column; gap: 2px; }
    .stat-value { font-size: var(--font-size-2xl); font-weight: 700; color: var(--color-primary); line-height: 1.1; }
    .stat-label { font-size: var(--font-size-xs); color: var(--color-muted); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
    .stat-card.stat-warning .stat-value { color: var(--color-warning); }
    .stat-card.stat-danger .stat-value { color: var(--color-danger); }

    .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-5); margin-bottom: var(--space-4); }
    .filters { display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4); flex-wrap: wrap; }
    .filters select { padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); font-size: var(--font-size-sm); }
    .checkbox-field { display: flex; align-items: center; gap: 6px; font-size: var(--font-size-sm); color: var(--color-foreground); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3); }
    .form-grid label { display: flex; flex-direction: column; gap: 5px; font-size: var(--font-size-sm); font-weight: 500; color: var(--color-foreground); }
    .form-grid input, .form-grid select, .form-grid textarea { box-sizing: border-box; width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); font-size: var(--font-size-sm); font-family: inherit; transition: border-color var(--transition-fast); }
    .form-grid input:focus-visible, .form-grid select:focus-visible, .form-grid textarea:focus-visible { border-color: var(--color-primary); }
    .full-width { grid-column: 1 / -1; }
    .form-actions { grid-column: 1 / -1; }

    button { padding: 8px 14px; border: 1px solid transparent; border-radius: var(--radius-sm); background: var(--color-primary); color: var(--color-on-primary); font-size: var(--font-size-sm); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background var(--transition-fast), filter var(--transition-fast); }
    button:hover:not(:disabled) { background: var(--color-primary-hover); }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    button.btn-outline { background: transparent; border-color: var(--color-border); color: var(--color-foreground); }
    button.btn-outline:hover:not(:disabled) { background: var(--color-muted-bg); }
    button.btn-success { background: var(--color-success); }
    button.btn-success:hover:not(:disabled) { filter: brightness(0.93); }
    .btn-icon { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    .table-wrapper { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: var(--space-3) var(--space-2); border-bottom: 1px solid var(--color-border); text-align: left; font-size: var(--font-size-sm); white-space: nowrap; }
    tbody tr:not(.expanded-row):hover { background: var(--color-background); }
    .mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; font-size: var(--font-size-xs); color: var(--color-muted); }
    th { color: var(--color-muted); background: var(--color-background); font-size: var(--font-size-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }

    .status-badge { display: inline-block; font-size: var(--font-size-xs); font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
    .status-active { background: var(--color-success-bg); color: var(--color-success); }
    .status-pending { background: var(--color-warning-bg); color: var(--color-warning); }
    .status-info { background: var(--color-info-bg); color: var(--color-info); }
    .status-danger { background: var(--color-danger-bg); color: var(--color-danger); }
    .status-neutral { background: var(--color-muted-bg); color: var(--color-muted); }

    .expanded-row td { background: var(--color-background); padding: var(--space-4); white-space: normal; }
    .detail p { margin: 0 0 var(--space-2); }
    .muted { color: var(--color-muted); font-size: var(--font-size-sm); }
    .actions { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-3); }

    .inline-form { margin-top: var(--space-3); padding: var(--space-3); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); }
    .inline-form label { display: flex; flex-direction: column; gap: 5px; font-size: var(--font-size-sm); font-weight: 500; }
    .inline-form textarea { box-sizing: border-box; width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: var(--font-size-sm); font-family: inherit; resize: vertical; }
    .inline-form-actions { display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-2); }

    .empty-state { padding: var(--space-6) var(--space-4); border: 1px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-muted); background: var(--color-background); font-size: var(--font-size-sm); text-align: center; display: flex; flex-direction: column; align-items: center; gap: var(--space-2); }
    .empty-icon { width: 28px; height: 28px; fill: none; stroke: currentColor; stroke-width: 1.5; opacity: 0.5; }
    .error-box { padding: var(--space-3); background: var(--color-danger-bg); color: var(--color-danger); border-radius: var(--radius-md); margin-bottom: var(--space-4); font-size: var(--font-size-sm); }
  `],
})
export class ComplaintListComponent implements OnInit {
  complaints = signal<AgentComplaint[]>([]);
  loading = signal(false);
  working = signal(false);
  error = signal<string | null>(null);
  showCreateForm = signal(false);
  expandedId = signal<number | null>(null);
  escalateFormId = signal<number | null>(null);
  resolveFormId = signal<number | null>(null);

  escalateReason = '';
  resolveSummary = '';

  filterStatus = '';
  filterOverdue = false;

  openCount = computed(
    () => this.complaints().filter((c) => c.status === 'OPEN').length
  );
  escalatedCount = computed(
    () => this.complaints().filter((c) => c.status === 'ESCALATED').length
  );
  overdueCount = computed(
    () =>
      this.complaints().filter(
        (c) =>
          c.due_at &&
          !['RESOLVED', 'CLOSED'].includes(c.status) &&
          new Date(c.due_at).getTime() < Date.now()
      ).length
  );

  form: {
    agent_id: number | null;
    complainant_name: string;
    complainant_phone: string;
    complainant_email: string;
    channel: string;
    category: string;
    subject: string;
    description: string;
    disputed_amount: number | null;
    priority: string;
  } = this.emptyForm();

  constructor(private api: AgentComplaintApiService) {}

  ngOnInit(): void {
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .list({
        status: this.filterStatus || undefined,
        overdue: this.filterOverdue || undefined,
      })
      .subscribe({
        next: (result) => {
          this.complaints.set(result.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to load complaints.'
          );
          this.loading.set(false);
        },
      });
  }

  toggleCreateForm(): void {
    this.showCreateForm.update((v) => !v);
  }

  toggleExpanded(id: number): void {
    this.expandedId.update((current) => (current === id ? null : id));
    this.escalateFormId.set(null);
    this.resolveFormId.set(null);
  }

  toggleEscalateForm(id: number): void {
    this.escalateReason = '';
    this.resolveFormId.set(null);
    this.escalateFormId.update((current) => (current === id ? null : id));
  }

  toggleResolveForm(id: number): void {
    this.resolveSummary = '';
    this.escalateFormId.set(null);
    this.resolveFormId.update((current) => (current === id ? null : id));
  }

  create(): void {
    if (
      !this.form.complainant_name.trim() ||
      !this.form.subject.trim() ||
      !this.form.description.trim()
    ) {
      this.error.set('Complainant name, subject and description are required.');
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .create({
        agent_id: this.form.agent_id ?? undefined,
        complainant_name: this.form.complainant_name.trim(),
        complainant_phone: this.form.complainant_phone.trim() || undefined,
        complainant_email: this.form.complainant_email.trim() || undefined,
        channel: this.form.channel,
        category: this.form.category,
        subject: this.form.subject.trim(),
        description: this.form.description.trim(),
        disputed_amount: this.form.disputed_amount ?? undefined,
        priority: this.form.priority,
      })
      .subscribe({
        next: () => {
          this.form = this.emptyForm();
          this.showCreateForm.set(false);
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to log complaint.'
          );
          this.working.set(false);
        },
      });
  }

  acknowledge(c: AgentComplaint): void {
    this.runAction(this.api.acknowledge(c.id));
  }

  startProgress(c: AgentComplaint): void {
    this.runAction(this.api.startProgress(c.id));
  }

  escalate(c: AgentComplaint): void {
    if (!this.escalateReason.trim()) return;
    this.escalateFormId.set(null);
    this.runAction(this.api.escalate(c.id, this.escalateReason.trim()));
  }

  resolve(c: AgentComplaint): void {
    if (!this.resolveSummary.trim()) return;
    this.resolveFormId.set(null);
    this.runAction(this.api.resolve(c.id, this.resolveSummary.trim()));
  }

  close(c: AgentComplaint): void {
    this.runAction(this.api.close(c.id));
  }

  private runAction(obs: import('rxjs').Observable<AgentComplaint>): void {
    this.working.set(true);
    this.error.set(null);

    obs.subscribe({
      next: () => {
        this.working.set(false);
        this.reload();
      },
      error: (err) => {
        this.error.set(err?.error?.message ?? 'Action failed.');
        this.working.set(false);
      },
    });
  }

  statusClass(status: string): string {
    if (status === 'RESOLVED' || status === 'CLOSED') return 'status-active';
    if (status === 'ESCALATED') return 'status-danger';
    if (status === 'OPEN') return 'status-pending';
    return 'status-info';
  }

  priorityClass(priority: string): string {
    if (priority === 'CRITICAL') return 'status-danger';
    if (priority === 'HIGH') return 'status-pending';
    return 'status-neutral';
  }

  displayStatus(value: string): string {
    return value
      .replaceAll('_', ' ')
      .toLowerCase()
      .replace(/\b\w/g, (c) => c.toUpperCase());
  }

  formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  }

  private emptyForm() {
    return {
      agent_id: null,
      complainant_name: '',
      complainant_phone: '',
      complainant_email: '',
      channel: 'BRANCH',
      category: 'SERVICE_FAILURE',
      subject: '',
      description: '',
      disputed_amount: null,
      priority: 'NORMAL',
    };
  }
}
