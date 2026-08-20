import { Component, OnInit, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AgentInspectionApiService } from '../../core/agent-inspection-api.service';
import { AgentInspection } from '../../core/models/api.models';

@Component({
  selector: 'app-inspection-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page">
      <div class="page-header">
        <div>
          <h1>Agent Inspections</h1>
          <p>Site-visit scheduling, findings, compliance grading and corrective-action follow-up.</p>
        </div>

        <button
          class="btn-outline"
          (click)="toggleCreateForm()"
          [disabled]="working()"
        >
          {{ showCreateForm() ? 'Cancel' : '+ Schedule Inspection' }}
        </button>
      </div>

      <div class="stat-grid">
        <div class="stat-card">
          <span class="stat-value">{{ scheduledCount() }}</span>
          <span class="stat-label">Scheduled</span>
        </div>
        <div class="stat-card" [class.stat-danger]="nonCompliantCount() > 0">
          <span class="stat-value">{{ nonCompliantCount() }}</span>
          <span class="stat-label">Non-Compliant</span>
        </div>
        <div class="stat-card" [class.stat-warning]="pendingFollowUpCount() > 0">
          <span class="stat-value">{{ pendingFollowUpCount() }}</span>
          <span class="stat-label">Follow-Up Pending</span>
        </div>
        <div class="stat-card">
          <span class="stat-value">{{ inspections().length }}</span>
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
              Agent ID
              <input type="number" [(ngModel)]="form.agent_id" />
            </label>

            <label>
              Agent Location ID (optional)
              <input type="number" [(ngModel)]="form.agent_location_id" />
            </label>

            <label>
              Inspector User ID
              <input type="number" [(ngModel)]="form.inspector_id" />
              <span class="hint">Numeric platform user ID.</span>
            </label>

            <label>
              Inspection Type
              <select [(ngModel)]="form.inspection_type">
                <option value="ROUTINE">Routine</option>
                <option value="COMPLIANCE">Compliance</option>
                <option value="FOLLOW_UP">Follow-Up</option>
                <option value="INCIDENT_TRIGGERED">Incident-Triggered</option>
                <option value="PRE_ACTIVATION">Pre-Activation</option>
              </select>
            </label>

            <label>
              Inspection Date
              <input type="date" [(ngModel)]="form.inspection_date" />
            </label>

            <div class="form-actions">
              <button (click)="create()" [disabled]="working()">
                {{ working() ? 'Scheduling...' : 'Schedule Inspection' }}
              </button>
            </div>
          </div>
        </section>
      }

      <section class="card">
        <div class="filters">
          <select [(ngModel)]="filterStatus" (ngModelChange)="reload()">
            <option value="">All statuses</option>
            <option value="SCHEDULED">Scheduled</option>
            <option value="IN_PROGRESS">In Progress</option>
            <option value="COMPLETED">Completed</option>
            <option value="CANCELLED">Cancelled</option>
          </select>

          <select [(ngModel)]="filterFollowUp" (ngModelChange)="reload()">
            <option value="">Any follow-up status</option>
            <option value="NOT_REQUIRED">Not Required</option>
            <option value="PENDING">Pending</option>
            <option value="IN_PROGRESS">In Progress</option>
            <option value="COMPLETED">Completed</option>
          </select>

          <label class="checkbox-field">
            <input
              type="checkbox"
              [(ngModel)]="filterOverdue"
              (ngModelChange)="reload()"
            />
            Overdue follow-up only
          </label>

          <button class="btn-outline" (click)="reload()">
            <svg viewBox="0 0 24 24" class="btn-icon"><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3M4 4v5h5M20 20v-5h-5"/></svg>
            Refresh
          </button>
        </div>

        @if (loading()) {
          <div class="empty-state">Loading...</div>
        } @else if (inspections().length === 0) {
          <div class="empty-state">
            <svg viewBox="0 0 24 24" class="empty-icon"><rect x="6" y="4" width="12" height="17" rx="1.5"/><path d="M9 3.5h6v2H9zM9 11l2 2 4-4"/></svg>
            No inspections match these filters.
          </div>
        } @else {
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Agent</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Compliance</th>
                  <th>Follow-Up</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @for (i of inspections(); track i.id) {
                  <tr>
                    <td class="mono">{{ i.inspection_no }}</td>
                    <td>{{ i.agent?.legal_name ?? ('#' + i.agent_id) }}</td>
                    <td>{{ displayStatus(i.inspection_type) }}</td>
                    <td>{{ formatDate(i.inspection_date) }}</td>
                    <td>
                      <span class="status-badge" [class]="statusClass(i.status)">
                        {{ displayStatus(i.status) }}
                      </span>
                    </td>
                    <td>
                      @if (i.compliance_outcome) {
                        <span
                          class="status-badge"
                          [class]="complianceClass(i.compliance_outcome)"
                        >
                          {{ displayStatus(i.compliance_outcome) }}
                        </span>
                      } @else {
                        <span class="muted">—</span>
                      }
                    </td>
                    <td>
                      <span
                        class="status-badge"
                        [class]="followUpClass(i.follow_up_status)"
                      >
                        {{ displayStatus(i.follow_up_status) }}
                      </span>
                    </td>
                    <td>
                      <button
                        class="btn-outline"
                        (click)="toggleExpanded(i.id)"
                      >
                        {{ expandedId() === i.id ? 'Hide' : 'Manage' }}
                      </button>
                    </td>
                  </tr>

                  @if (expandedId() === i.id) {
                    <tr class="expanded-row">
                      <td colspan="8">
                        <div class="detail">
                          @if (i.findings) {
                            <p><strong>Findings:</strong> {{ i.findings }}</p>
                          }

                          @if (i.corrective_action) {
                            <p>
                              <strong>Corrective action:</strong>
                              {{ i.corrective_action }}
                              @if (i.corrective_action_deadline) {
                                (due {{ i.corrective_action_deadline }})
                              }
                            </p>
                          }

                          @if (i.follow_up_notes) {
                            <p class="muted">{{ i.follow_up_notes }}</p>
                          }

                          <div class="actions">
                            @if (i.status === 'SCHEDULED') {
                              <button
                                (click)="start(i)"
                                [disabled]="working()"
                              >
                                Start
                              </button>
                            }

                            @if (
                              i.status === 'SCHEDULED' ||
                              i.status === 'IN_PROGRESS'
                            ) {
                              <button
                                class="btn-success"
                                (click)="toggleCompleteForm(i.id)"
                                [disabled]="working()"
                              >
                                Complete
                              </button>
                              <button
                                class="btn-danger-outline"
                                (click)="toggleCancelForm(i.id)"
                                [disabled]="working()"
                              >
                                Cancel
                              </button>
                            }

                            @if (i.follow_up_status === 'PENDING') {
                              <button
                                (click)="startFollowUp(i)"
                                [disabled]="working()"
                              >
                                Start Follow-Up
                              </button>
                            }

                            @if (i.follow_up_status === 'IN_PROGRESS') {
                              <button
                                class="btn-success"
                                (click)="toggleFollowUpForm(i.id)"
                                [disabled]="working()"
                              >
                                Complete Follow-Up
                              </button>
                            }
                          </div>

                          @if (completeFormId() === i.id) {
                            <div class="inline-form">
                              <div class="inline-form-grid">
                                <label class="full-width">
                                  Findings
                                  <textarea
                                    rows="2"
                                    [(ngModel)]="completeForm.findings"
                                    [ngModelOptions]="{ standalone: true }"
                                  ></textarea>
                                </label>
                                <label>
                                  Compliance Outcome
                                  <select
                                    [(ngModel)]="completeForm.compliance_outcome"
                                    [ngModelOptions]="{ standalone: true }"
                                  >
                                    <option value="COMPLIANT">Compliant</option>
                                    <option value="MINOR_NON_COMPLIANCE">Minor Non-Compliance</option>
                                    <option value="MAJOR_NON_COMPLIANCE">Major Non-Compliance</option>
                                    <option value="CRITICAL_NON_COMPLIANCE">Critical Non-Compliance</option>
                                  </select>
                                </label>
                                @if (completeForm.compliance_outcome !== 'COMPLIANT') {
                                  <label>
                                    Corrective Action
                                    <input
                                      [(ngModel)]="completeForm.corrective_action"
                                      [ngModelOptions]="{ standalone: true }"
                                    />
                                  </label>
                                  <label>
                                    Deadline
                                    <input
                                      type="date"
                                      [(ngModel)]="completeForm.corrective_action_deadline"
                                      [ngModelOptions]="{ standalone: true }"
                                    />
                                  </label>
                                }
                              </div>
                              <div class="inline-form-actions">
                                <button class="btn-outline" (click)="completeFormId.set(null)">
                                  Cancel
                                </button>
                                <button
                                  class="btn-success"
                                  (click)="complete(i)"
                                  [disabled]="working() || !completeForm.findings.trim()"
                                >
                                  Confirm Completion
                                </button>
                              </div>
                            </div>
                          }

                          @if (cancelFormId() === i.id) {
                            <div class="inline-form">
                              <label>
                                Cancellation reason
                                <textarea
                                  rows="2"
                                  [(ngModel)]="cancelReason"
                                  [ngModelOptions]="{ standalone: true }"
                                ></textarea>
                              </label>
                              <div class="inline-form-actions">
                                <button class="btn-outline" (click)="cancelFormId.set(null)">
                                  Back
                                </button>
                                <button
                                  class="btn-danger-outline"
                                  (click)="cancel(i)"
                                  [disabled]="working() || !cancelReason.trim()"
                                >
                                  Confirm Cancellation
                                </button>
                              </div>
                            </div>
                          }

                          @if (followUpFormId() === i.id) {
                            <div class="inline-form">
                              <label>
                                Follow-up notes (optional)
                                <textarea
                                  rows="2"
                                  [(ngModel)]="followUpNotes"
                                  [ngModelOptions]="{ standalone: true }"
                                ></textarea>
                              </label>
                              <div class="inline-form-actions">
                                <button class="btn-outline" (click)="followUpFormId.set(null)">
                                  Cancel
                                </button>
                                <button
                                  class="btn-success"
                                  (click)="completeFollowUp(i)"
                                  [disabled]="working()"
                                >
                                  Confirm Follow-Up Complete
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
    .form-grid input, .form-grid select, .form-grid textarea { box-sizing: border-box; width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); font-size: var(--font-size-sm); font-family: inherit; }
    .form-grid .hint { font-size: var(--font-size-xs); color: var(--color-muted); font-weight: 400; }
    .full-width { grid-column: 1 / -1; }
    .form-actions { grid-column: 1 / -1; }

    button { padding: 8px 14px; border: 1px solid transparent; border-radius: var(--radius-sm); background: var(--color-primary); color: var(--color-on-primary); font-size: var(--font-size-sm); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background var(--transition-fast), filter var(--transition-fast); }
    button:hover:not(:disabled) { background: var(--color-primary-hover); }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    button.btn-outline { background: transparent; border-color: var(--color-border); color: var(--color-foreground); }
    button.btn-outline:hover:not(:disabled) { background: var(--color-muted-bg); }
    button.btn-success { background: var(--color-success); }
    button.btn-success:hover:not(:disabled) { filter: brightness(0.93); }
    button.btn-danger-outline { background: transparent; border-color: var(--color-danger-bg); color: var(--color-danger); }
    button.btn-danger-outline:hover:not(:disabled) { background: var(--color-danger-bg); }
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
    .inline-form > label { display: flex; flex-direction: column; gap: 5px; font-size: var(--font-size-sm); font-weight: 500; }
    .inline-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-3); }
    .inline-form-grid label { display: flex; flex-direction: column; gap: 5px; font-size: var(--font-size-sm); font-weight: 500; }
    .inline-form textarea, .inline-form input, .inline-form select { box-sizing: border-box; width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: var(--font-size-sm); font-family: inherit; resize: vertical; }
    .inline-form-actions { display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-3); }

    .empty-state { padding: var(--space-6) var(--space-4); border: 1px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-muted); background: var(--color-background); font-size: var(--font-size-sm); text-align: center; display: flex; flex-direction: column; align-items: center; gap: var(--space-2); }
    .empty-icon { width: 28px; height: 28px; fill: none; stroke: currentColor; stroke-width: 1.5; opacity: 0.5; }
    .error-box { padding: var(--space-3); background: var(--color-danger-bg); color: var(--color-danger); border-radius: var(--radius-md); margin-bottom: var(--space-4); font-size: var(--font-size-sm); }
  `],
})
export class InspectionListComponent implements OnInit {
  inspections = signal<AgentInspection[]>([]);
  loading = signal(false);
  working = signal(false);
  error = signal<string | null>(null);
  showCreateForm = signal(false);
  expandedId = signal<number | null>(null);
  completeFormId = signal<number | null>(null);
  cancelFormId = signal<number | null>(null);
  followUpFormId = signal<number | null>(null);

  cancelReason = '';
  followUpNotes = '';
  completeForm = this.emptyCompleteForm();

  filterStatus = '';
  filterFollowUp = '';
  filterOverdue = false;

  scheduledCount = computed(
    () => this.inspections().filter((i) => i.status === 'SCHEDULED').length
  );
  nonCompliantCount = computed(
    () =>
      this.inspections().filter(
        (i) =>
          !!i.compliance_outcome && i.compliance_outcome !== 'COMPLIANT'
      ).length
  );
  pendingFollowUpCount = computed(
    () =>
      this.inspections().filter((i) => i.follow_up_status === 'PENDING')
        .length
  );

  form: {
    agent_id: number | null;
    agent_location_id: number | null;
    inspector_id: number | null;
    inspection_type: string;
    inspection_date: string;
  } = this.emptyForm();

  constructor(private api: AgentInspectionApiService) {}

  ngOnInit(): void {
    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .list({
        status: this.filterStatus || undefined,
        follow_up_status: this.filterFollowUp || undefined,
        overdue: this.filterOverdue || undefined,
      })
      .subscribe({
        next: (result) => {
          this.inspections.set(result.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to load inspections.'
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
    this.completeFormId.set(null);
    this.cancelFormId.set(null);
    this.followUpFormId.set(null);
  }

  toggleCompleteForm(id: number): void {
    this.completeForm = this.emptyCompleteForm();
    this.cancelFormId.set(null);
    this.completeFormId.update((current) => (current === id ? null : id));
  }

  toggleCancelForm(id: number): void {
    this.cancelReason = '';
    this.completeFormId.set(null);
    this.cancelFormId.update((current) => (current === id ? null : id));
  }

  toggleFollowUpForm(id: number): void {
    this.followUpNotes = '';
    this.followUpFormId.update((current) => (current === id ? null : id));
  }

  create(): void {
    if (
      !this.form.agent_id ||
      !this.form.inspector_id ||
      !this.form.inspection_date
    ) {
      this.error.set(
        'Agent, inspector and inspection date are all required.'
      );
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .create({
        agent_id: this.form.agent_id,
        agent_location_id: this.form.agent_location_id ?? undefined,
        inspector_id: this.form.inspector_id,
        inspection_type: this.form.inspection_type,
        inspection_date: this.form.inspection_date,
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
            err?.error?.message ?? 'Failed to schedule inspection.'
          );
          this.working.set(false);
        },
      });
  }

  start(i: AgentInspection): void {
    this.runAction(this.api.start(i.id));
  }

  complete(i: AgentInspection): void {
    if (!this.completeForm.findings.trim()) return;

    this.completeFormId.set(null);
    this.runAction(
      this.api.complete(i.id, {
        findings: this.completeForm.findings.trim(),
        compliance_outcome: this.completeForm.compliance_outcome,
        corrective_action:
          this.completeForm.corrective_action.trim() || undefined,
        corrective_action_deadline:
          this.completeForm.corrective_action_deadline || undefined,
      })
    );
  }

  cancel(i: AgentInspection): void {
    if (!this.cancelReason.trim()) return;
    this.cancelFormId.set(null);
    this.runAction(this.api.cancel(i.id, this.cancelReason.trim()));
  }

  startFollowUp(i: AgentInspection): void {
    this.runAction(this.api.startFollowUp(i.id));
  }

  completeFollowUp(i: AgentInspection): void {
    this.followUpFormId.set(null);
    this.runAction(
      this.api.completeFollowUp(i.id, this.followUpNotes.trim() || undefined)
    );
  }

  private runAction(
    obs: import('rxjs').Observable<AgentInspection>
  ): void {
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
    if (status === 'COMPLETED') return 'status-active';
    if (status === 'CANCELLED') return 'status-danger';
    if (status === 'IN_PROGRESS') return 'status-info';
    return 'status-pending';
  }

  complianceClass(outcome: string): string {
    if (outcome === 'COMPLIANT') return 'status-active';
    if (outcome === 'MINOR_NON_COMPLIANCE') return 'status-pending';
    return 'status-danger';
  }

  followUpClass(status: string): string {
    if (status === 'COMPLETED' || status === 'NOT_REQUIRED') {
      return 'status-active';
    }
    if (status === 'IN_PROGRESS') return 'status-info';
    return 'status-pending';
  }

  displayStatus(value: string): string {
    return value
      .replaceAll('_', ' ')
      .toLowerCase()
      .replace(/\b\w/g, (c) => c.toUpperCase());
  }

  formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
      dateStyle: 'medium',
    });
  }

  private emptyForm() {
    return {
      agent_id: null,
      agent_location_id: null,
      inspector_id: null,
      inspection_type: 'ROUTINE',
      inspection_date: new Date().toISOString().slice(0, 10),
    };
  }

  private emptyCompleteForm() {
    return {
      findings: '',
      compliance_outcome: 'COMPLIANT',
      corrective_action: '',
      corrective_action_deadline: '',
    };
  }
}
