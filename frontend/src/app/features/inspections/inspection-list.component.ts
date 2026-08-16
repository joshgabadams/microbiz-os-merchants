import { Component, OnInit, signal } from '@angular/core';
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
              <span class="muted">Numeric platform user ID.</span>
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

          <button class="btn-outline" (click)="reload()">Refresh</button>
        </div>

        @if (loading()) {
          <div class="empty-state">Loading...</div>
        } @else if (inspections().length === 0) {
          <div class="empty-state">No inspections match these filters.</div>
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
                    <td>{{ i.inspection_no }}</td>
                    <td>{{ i.agent?.legal_name ?? ('#' + i.agent_id) }}</td>
                    <td>{{ displayStatus(i.inspection_type) }}</td>
                    <td>{{ i.inspection_date }}</td>
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
                        —
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
                              <button
                                class="btn-danger-outline"
                                (click)="cancel(i)"
                                [disabled]="working()"
                              >
                                Cancel
                              </button>
                            }

                            @if (
                              i.status === 'SCHEDULED' ||
                              i.status === 'IN_PROGRESS'
                            ) {
                              <button
                                class="btn-success"
                                (click)="complete(i)"
                                [disabled]="working()"
                              >
                                Complete
                              </button>
                            }

                            @if (i.status === 'IN_PROGRESS') {
                              <button
                                class="btn-danger-outline"
                                (click)="cancel(i)"
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
                                (click)="completeFollowUp(i)"
                                [disabled]="working()"
                              >
                                Complete Follow-Up
                              </button>
                            }
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
    </div>
  `,
  styles: [`
    :host { display: block; font-family: var(--font-sans); color: var(--color-foreground); }
    .page { max-width: 1240px; margin: 0 auto; }
    .page-header { display: flex; justify-content: space-between; gap: var(--space-5); align-items: flex-start; margin-bottom: var(--space-5); }
    h1 { margin: 0 0 4px; font-size: var(--font-size-2xl); font-weight: 700; color: var(--color-primary); }
    p { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }
    .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-5); margin-bottom: var(--space-4); }
    .filters { display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4); flex-wrap: wrap; }
    .filters select { padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); font-size: var(--font-size-sm); }
    .checkbox-field { display: flex; align-items: center; gap: 6px; font-size: var(--font-size-sm); color: var(--color-foreground); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3); }
    .form-grid label { display: flex; flex-direction: column; gap: 5px; font-size: var(--font-size-sm); font-weight: 500; color: var(--color-foreground); }
    .form-grid input, .form-grid select, .form-grid textarea { box-sizing: border-box; width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); font-size: var(--font-size-sm); }
    .form-grid .muted { font-size: var(--font-size-xs); color: var(--color-muted); font-weight: 400; }
    .full-width { grid-column: 1 / -1; }
    .form-actions { grid-column: 1 / -1; }
    button { padding: 8px 14px; border: 1px solid transparent; border-radius: var(--radius-sm); background: var(--color-primary); color: var(--color-on-primary); font-size: var(--font-size-sm); font-weight: 600; cursor: pointer; }
    button:hover:not(:disabled) { background: var(--color-primary-hover); }
    button:disabled { opacity: 0.55; cursor: not-allowed; }
    button.btn-outline { background: transparent; border-color: var(--color-border); color: var(--color-foreground); }
    button.btn-outline:hover:not(:disabled) { background: var(--color-muted-bg); }
    button.btn-success { background: var(--color-success); }
    button.btn-danger-outline { background: transparent; border-color: var(--color-danger-bg); color: var(--color-danger); }
    button.btn-danger-outline:hover:not(:disabled) { background: var(--color-danger-bg); }
    .table-wrapper { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: var(--space-2); border-bottom: 1px solid var(--color-border); text-align: left; font-size: var(--font-size-sm); white-space: nowrap; }
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
    .empty-state { padding: var(--space-4); border: 1px dashed var(--color-border); border-radius: var(--radius-md); color: var(--color-muted); background: var(--color-background); font-size: var(--font-size-sm); text-align: center; }
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

  filterStatus = '';
  filterFollowUp = '';
  filterOverdue = false;

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
    const findings = prompt('Findings:');
    if (!findings) return;

    const outcome = prompt(
      'Compliance outcome (COMPLIANT, MINOR_NON_COMPLIANCE, MAJOR_NON_COMPLIANCE, CRITICAL_NON_COMPLIANCE):',
      'COMPLIANT'
    );
    if (!outcome) return;

    let correctiveAction: string | undefined;
    let deadline: string | undefined;

    if (outcome !== 'COMPLIANT') {
      correctiveAction = prompt('Corrective action (optional):') ?? undefined;

      if (correctiveAction) {
        deadline =
          prompt('Corrective action deadline (YYYY-MM-DD):') ?? undefined;
      }
    }

    this.runAction(
      this.api.complete(i.id, {
        findings,
        compliance_outcome: outcome,
        corrective_action: correctiveAction || undefined,
        corrective_action_deadline: deadline || undefined,
      })
    );
  }

  cancel(i: AgentInspection): void {
    const reason = prompt('Cancellation reason:');
    if (!reason) return;
    this.runAction(this.api.cancel(i.id, reason));
  }

  startFollowUp(i: AgentInspection): void {
    this.runAction(this.api.startFollowUp(i.id));
  }

  completeFollowUp(i: AgentInspection): void {
    const notes = prompt('Follow-up notes (optional):') ?? undefined;
    this.runAction(this.api.completeFollowUp(i.id, notes));
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

  private emptyForm() {
    return {
      agent_id: null,
      agent_location_id: null,
      inspector_id: null,
      inspection_type: 'ROUTINE',
      inspection_date: new Date().toISOString().slice(0, 10),
    };
  }
}
