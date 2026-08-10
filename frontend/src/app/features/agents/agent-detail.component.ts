import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { AgentApiService } from '../../core/agent-api.service';
import {
  Agent,
  AgentAgreement,
  AgentLocation,
} from '../../core/models/api.models';

@Component({
  selector: 'app-agent-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page">
      <div class="page-header">
        <div>
          <a routerLink="/agents" class="back-link">← Back to Agents</a>

          @if (agent()) {
            <h1>{{ agent()!.legal_name }}</h1>

            <div class="agent-meta">
              <span>{{ agent()!.agent_code }}</span>
              <span>•</span>
              <span>{{ agent()!.agent_type }}</span>
              <span>•</span>
              <span>{{ agent()!.phone }}</span>
            </div>
          }
        </div>

        @if (agent()) {
          <span
            class="status-badge"
            [class]="statusClass(agent()!.status)"
          >
            {{ agent()!.status }}
          </span>
        }
      </div>

      @if (loading()) {
        <p>Loading agent...</p>
      }

      @if (error()) {
        <div class="error-box">
          {{ error() }}
        </div>
      }

      @if (!loading() && agent()) {
        <section class="card">
          <h2>Agent Lifecycle</h2>

          <div class="lifecycle">
            @for (step of lifecycleSteps; track step.status) {
              <div
                class="lifecycle-step"
                [class.complete]="isStepComplete(step.status)"
                [class.current]="isCurrentStep(step.status)"
              >
                <div class="step-dot">
                  @if (isStepComplete(step.status)) {
                    ✓
                  } @else if (isCurrentStep(step.status)) {
                    ●
                  } @else {
                    ○
                  }
                </div>

                <div>
                  <strong>{{ step.label }}</strong>
                  <div>{{ step.status }}</div>
                </div>
              </div>
            }
          </div>
        </section>

        <section class="card">
          <h2>Overview</h2>

          <div class="details-grid">
            <div>
              <span class="label">KYC Status</span>
              <strong>{{ agent()!.kyc_status }}</strong>
            </div>

            <div>
              <span class="label">Risk Rating</span>
              <strong>{{ agent()!.risk_rating }}</strong>
            </div>

            <div>
              <span class="label">Branch ID</span>
              <strong>{{ agent()!.branch_id }}</strong>
            </div>

            <div>
              <span class="label">Exclusive Relationship</span>
              <strong>
                {{ agent()!.exclusive_relationship ? 'YES' : 'NO' }}
              </strong>
            </div>

            <div>
              <span class="label">Principal Reference</span>
              <strong>
                {{ agent()!.principal_reference || '—' }}
              </strong>
            </div>

            <div>
              <span class="label">Approved At</span>
              <strong>
                {{ agent()!.approved_at || '—' }}
              </strong>
            </div>
          </div>
        </section>

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Locations</h2>
              <p>Approved physical premises and GPS verification.</p>
            </div>

            @if (
              agent()!.status === 'PENDING_LOCATION_VERIFICATION'
            ) {
              <button (click)="toggleLocationForm()">
                {{ showLocationForm() ? 'Cancel' : '+ Add Location' }}
              </button>
            }
          </div>

          @if (showLocationForm()) {
            <div class="form-grid">
              <input
                placeholder="Address line 1"
                [(ngModel)]="locationAddress"
              />

              <input
                placeholder="Landmark"
                [(ngModel)]="locationLandmark"
              />

              <input
                placeholder="City"
                [(ngModel)]="locationCity"
              />

              <input
                placeholder="Local Government"
                [(ngModel)]="locationLga"
              />

              <input
                placeholder="State"
                [(ngModel)]="locationState"
              />

              <input
                type="number"
                placeholder="Latitude"
                [(ngModel)]="locationLatitude"
              />

              <input
                type="number"
                placeholder="Longitude"
                [(ngModel)]="locationLongitude"
              />

              <input
                type="number"
                placeholder="Radius metres"
                [(ngModel)]="locationRadius"
              />

              <div class="form-actions">
                <button
                  (click)="createLocation()"
                  [disabled]="working()"
                >
                  {{ working() ? 'Saving...' : 'Save Location' }}
                </button>
              </div>
            </div>
          }

          @if (locations().length === 0) {
            <p>No locations recorded.</p>
          } @else {
            <div class="location-list">
              @for (location of locations(); track location.id) {
                <div class="location-card">
                  <div class="location-top">
                    <div>
                      <strong>
                        {{ location.address_line_1 }}
                      </strong>

                      <div>
                        {{ location.local_government }},
                        {{ location.state }}
                      </div>
                    </div>

                    <span
                      class="status-badge"
                      [class]="
                        location.verification_status === 'VERIFIED'
                          ? 'status-active'
                          : location.verification_status === 'REJECTED'
                            ? 'status-danger'
                            : 'status-pending'
                      "
                    >
                      {{ location.verification_status }}
                    </span>
                  </div>

                  <div class="location-details">
                    <span>
                      GPS:
                      {{ location.latitude }},
                      {{ location.longitude }}
                    </span>

                    <span>
                      Radius:
                      {{ location.approved_radius_metres }}m
                    </span>
                  </div>

                  @if (
                    location.verification_status === 'PENDING' &&
                    agent()!.status ===
                      'PENDING_LOCATION_VERIFICATION'
                  ) {
                    <div class="actions">
                      <button
                        (click)="verifyLocation(location)"
                        [disabled]="working()"
                      >
                        Verify
                      </button>

                      <button
                        class="danger"
                        (click)="rejectLocation(location)"
                        [disabled]="working()"
                      >
                        Reject
                      </button>
                    </div>
                  }
                </div>
              }
            </div>
          }
        </section>

        @if (
          agent()!.status === 'PENDING_COMPLIANCE_REVIEW'
        ) {
          <section class="card action-card">
            <h2>Compliance Review</h2>

            <p>
              Location verification is complete. The agent is now
              awaiting independent compliance review.
            </p>

            <button
              (click)="completeComplianceReview()"
              [disabled]="working()"
            >
              Complete Compliance Review
            </button>
          </section>
        }

        @if (agent()!.status === 'PENDING_APPROVAL') {
          <section class="card action-card">
            <h2>Institutional Approval</h2>

            <p>
              Compliance review is complete. The agent is ready for
              institutional approval.
            </p>

            <div class="actions">
              <button
                (click)="approveAgent()"
                [disabled]="working()"
              >
                Approve Agent
              </button>

              <button
                class="danger"
                (click)="rejectAgent()"
                [disabled]="working()"
              >
                Reject Agent
              </button>
            </div>
          </section>
        }

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Agreements</h2>
              <p>
                Versioned agency agreements and execution status.
              </p>
            </div>

            @if (agent()!.status === 'AGREEMENT_PENDING') {
              <button (click)="toggleAgreementForm()">
                {{
                  showAgreementForm()
                    ? 'Cancel'
                    : '+ Create Agreement'
                }}
              </button>
            }
          </div>

          @if (showAgreementForm()) {
            <div class="form-grid">
              <label>
                Expiry Date
                <input
                  type="date"
                  [(ngModel)]="agreementExpiryDate"
                />
              </label>

              <label>
                Renewal Due Date
                <input
                  type="date"
                  [(ngModel)]="agreementRenewalDate"
                />
              </label>

              <label class="full-width">
                Document Path
                <input
                  placeholder="agent-agreements/..."
                  [(ngModel)]="agreementDocumentPath"
                />
              </label>

              <div class="form-actions">
                <button
                  (click)="createAgreement()"
                  [disabled]="working()"
                >
                  Create Draft Agreement
                </button>
              </div>
            </div>
          }

          @if (agreements().length === 0) {
            <p>No agreements recorded.</p>
          } @else {
            <table>
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Expiry</th>
                  <th>Renewal Due</th>
                  <th>Executed</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody>
                @for (
                  agreement of agreements();
                  track agreement.id
                ) {
                  <tr>
                    <td>V{{ agreement.version }}</td>
                    <td>{{ agreement.status }}</td>
                    <td>{{ agreement.expiry_date || '—' }}</td>
                    <td>
                      {{ agreement.renewal_due_date || '—' }}
                    </td>
                    <td>
                      {{ agreement.executed_at || '—' }}
                    </td>
                    <td>
                      @if (
                        agreement.status === 'DRAFT' &&
                        agent()!.status === 'AGREEMENT_PENDING'
                      ) {
                        <button
                          (click)="executeAgreement(agreement)"
                          [disabled]="working()"
                        >
                          Execute
                        </button>
                      }
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          }
        </section>

        @if (agent()!.status === 'TRAINING_PENDING') {
          <section class="card next-stage">
            <h2>AG-03 Complete</h2>

            <p>
              Registration, KYC, location verification, compliance
              review, approval and agreement execution are complete.
            </p>

            <strong>
              Next stage: AG-04 Training, Operators & Terminals
            </strong>
          </section>
        }
      }
    </div>
  `,
  styles: [`
    .page {
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      align-items: flex-start;
      margin-bottom: 22px;
    }

    h1 {
      margin: 6px 0 4px;
      font-size: 1.7rem;
    }

    h2 {
      margin-top: 0;
      font-size: 1.05rem;
    }

    .back-link {
      text-decoration: none;
      color: #1e2761;
      font-size: 0.9rem;
    }

    .agent-meta {
      display: flex;
      gap: 8px;
      color: #666;
      font-size: 0.88rem;
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
      align-items: flex-start;
      gap: 16px;
    }

    .section-header p {
      margin-top: -5px;
      color: #666;
      font-size: 0.87rem;
    }

    .details-grid {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
    }

    .details-grid > div {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .label {
      font-size: 0.78rem;
      color: #777;
    }

    .lifecycle {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(145px, 1fr));
      gap: 10px;
    }

    .lifecycle-step {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px;
      display: flex;
      gap: 8px;
      font-size: 0.75rem;
      color: #777;
    }

    .lifecycle-step.complete {
      background: #e7f6ec;
      border-color: #b9dfc7;
      color: #26623c;
    }

    .lifecycle-step.current {
      background: #fff3d5;
      border-color: #eccb77;
      color: #795900;
    }

    .step-dot {
      font-size: 1rem;
    }

    .status-badge {
      font-size: 0.76rem;
      padding: 5px 10px;
      border-radius: 14px;
      white-space: nowrap;
    }

    .status-active {
      background: #d7f0dd;
      color: #1f6f5c;
    }

    .status-pending {
      background: #fbe9c9;
      color: #8a5d00;
    }

    .status-danger {
      background: #f6d9d5;
      color: #a6432f;
    }

    .form-grid {
      margin: 15px 0;
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px;
      padding: 14px;
      background: #f8f9fc;
      border-radius: 8px;
    }

    .form-grid input {
      box-sizing: border-box;
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    .form-grid label {
      display: flex;
      flex-direction: column;
      gap: 5px;
      font-size: 0.8rem;
      color: #555;
    }

    .full-width {
      grid-column: 1 / -1;
    }

    .form-actions {
      grid-column: 1 / -1;
    }

    button {
      padding: 7px 12px;
      border: 0;
      border-radius: 5px;
      background: #1e2761;
      color: white;
      cursor: pointer;
    }

    button:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    button.danger {
      background: #a6432f;
    }

    .actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .location-list {
      display: grid;
      gap: 10px;
    }

    .location-card {
      border: 1px solid #e1e5ee;
      border-radius: 8px;
      padding: 13px;
    }

    .location-top {
      display: flex;
      justify-content: space-between;
      gap: 15px;
    }

    .location-details {
      margin-top: 8px;
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      color: #666;
      font-size: 0.8rem;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 9px;
      border-bottom: 1px solid #eee;
      text-align: left;
      font-size: 0.83rem;
    }

    th {
      color: #555;
    }

    .error-box {
      padding: 12px;
      background: #f6d9d5;
      color: #a6432f;
      border-radius: 7px;
      margin-bottom: 15px;
    }

    .action-card {
      border-left: 4px solid #1e2761;
    }

    .next-stage {
      background: #eef3ff;
      border-color: #bcccf2;
    }
  `],
})
export class AgentDetailComponent implements OnInit {
  agent = signal<Agent | null>(null);
  locations = signal<AgentLocation[]>([]);
  agreements = signal<AgentAgreement[]>([]);

  loading = signal(true);
  working = signal(false);
  error = signal<string | null>(null);

  showLocationForm = signal(false);
  showAgreementForm = signal(false);

  locationAddress = '';
  locationLandmark = '';
  locationCity = '';
  locationLga = '';
  locationState = '';
  locationLatitude: number | null = null;
  locationLongitude: number | null = null;
  locationRadius = 10;

  agreementExpiryDate = '';
  agreementRenewalDate = '';
  agreementDocumentPath = '';

  readonly lifecycleSteps = [
    { label: 'Registered', status: 'DRAFT' },
    { label: 'KYC', status: 'PENDING_KYC' },
    {
      label: 'Location',
      status: 'PENDING_LOCATION_VERIFICATION',
    },
    {
      label: 'Compliance',
      status: 'PENDING_COMPLIANCE_REVIEW',
    },
    {
      label: 'Approval',
      status: 'PENDING_APPROVAL',
    },
    {
      label: 'Agreement',
      status: 'AGREEMENT_PENDING',
    },
    {
      label: 'Training',
      status: 'TRAINING_PENDING',
    },
  ];

  private readonly lifecycleOrder = [
    'DRAFT',
    'PENDING_KYC',
    'PENDING_LOCATION_VERIFICATION',
    'PENDING_COMPLIANCE_REVIEW',
    'PENDING_APPROVAL',
    'AGREEMENT_PENDING',
    'TRAINING_PENDING',
    'TERMINAL_PENDING',
    'ACTIVE',
  ];

  private agentId = 0;

  constructor(
    private route: ActivatedRoute,
    private api: AgentApiService
  ) {}

  ngOnInit(): void {
    this.agentId = Number(
      this.route.snapshot.paramMap.get('id')
    );

    this.reload();
  }

  reload(): void {
    this.loading.set(true);
    this.error.set(null);

    forkJoin({
      agent: this.api.show(this.agentId),
      locations: this.api.listLocations(this.agentId),
      agreements: this.api.listAgreements(this.agentId),
    }).subscribe({
      next: (result) => {
        this.agent.set(result.agent.data);
        this.locations.set(result.locations.data);
        this.agreements.set(result.agreements.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(
          err?.error?.message ?? 'Failed to load agent.'
        );
        this.loading.set(false);
      },
    });
  }

  statusClass(status: string): string {
    if (
      status === 'ACTIVE' ||
      status === 'TRAINING_PENDING'
    ) {
      return 'status-active';
    }

    if (
      status === 'REJECTED' ||
      status === 'SUSPENDED' ||
      status === 'TERMINATED' ||
      status === 'BLACKLISTED'
    ) {
      return 'status-danger';
    }

    return 'status-pending';
  }

  isCurrentStep(status: string): boolean {
    return this.agent()?.status === status;
  }

  isStepComplete(status: string): boolean {
    const currentStatus = this.agent()?.status;

    if (!currentStatus) {
      return false;
    }

    const currentIndex =
      this.lifecycleOrder.indexOf(currentStatus);

    const stepIndex =
      this.lifecycleOrder.indexOf(status);

    return (
      currentIndex > stepIndex &&
      currentIndex !== -1 &&
      stepIndex !== -1
    );
  }

  toggleLocationForm(): void {
    this.showLocationForm.update((value) => !value);
  }

  createLocation(): void {
    if (
      !this.locationAddress.trim() ||
      !this.locationLga.trim() ||
      !this.locationState.trim() ||
      this.locationLatitude === null ||
      this.locationLongitude === null
    ) {
      this.error.set(
        'Address, LGA, state, latitude and longitude are required.'
      );
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .createLocation(this.agentId, {
        address_line_1: this.locationAddress.trim(),
        landmark:
          this.locationLandmark.trim() || undefined,
        city: this.locationCity.trim(),
        local_government: this.locationLga.trim(),
        state: this.locationState.trim(),
        latitude: this.locationLatitude,
        longitude: this.locationLongitude,
        approved_radius_metres: this.locationRadius,
      })
      .subscribe({
        next: () => {
          this.showLocationForm.set(false);
          this.resetLocationForm();
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to create location.'
          );
          this.working.set(false);
        },
      });
  }

  verifyLocation(location: AgentLocation): void {
    const notes =
      window.prompt('Verification notes:') ?? '';

    this.working.set(true);
    this.error.set(null);

    this.api
      .verifyLocation(
        this.agentId,
        location.id,
        notes.trim() || undefined
      )
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to verify location.'
          );
          this.working.set(false);
        },
      });
  }

  rejectLocation(location: AgentLocation): void {
    const reason = window.prompt(
      'Reason for rejecting this location:'
    );

    if (!reason?.trim()) {
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .rejectLocation(
        this.agentId,
        location.id,
        reason.trim()
      )
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to reject location.'
          );
          this.working.set(false);
        },
      });
  }

  completeComplianceReview(): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .completeComplianceReview(this.agentId)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to complete compliance review.'
          );
          this.working.set(false);
        },
      });
  }

  approveAgent(): void {
    this.working.set(true);
    this.error.set(null);

    this.api.approve(this.agentId).subscribe({
      next: () => {
        this.working.set(false);
        this.reload();
      },
      error: (err) => {
        this.error.set(
          err?.error?.message ??
            'Failed to approve agent.'
        );
        this.working.set(false);
      },
    });
  }

  rejectAgent(): void {
    const reason = window.prompt(
      'Reason for rejecting this agent:'
    );

    if (!reason?.trim()) {
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .reject(this.agentId, reason.trim())
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to reject agent.'
          );
          this.working.set(false);
        },
      });
  }

  toggleAgreementForm(): void {
    this.showAgreementForm.update((value) => !value);
  }

  createAgreement(): void {
    if (!this.agreementExpiryDate) {
      this.error.set('Agreement expiry date is required.');
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .createAgreement(this.agentId, {
        expiry_date: this.agreementExpiryDate,
        renewal_due_date:
          this.agreementRenewalDate || undefined,
        permitted_services: [
          'CASH_IN',
          'CASH_OUT',
        ],
        commercial_terms: {
          commission_model: 'STANDARD',
        },
        document_path:
          this.agreementDocumentPath.trim() || undefined,
      })
      .subscribe({
        next: () => {
          this.showAgreementForm.set(false);
          this.resetAgreementForm();
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to create agreement.'
          );
          this.working.set(false);
        },
      });
  }

  executeAgreement(
    agreement: AgentAgreement
  ): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .executeAgreement(
        this.agentId,
        agreement.id
      )
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to execute agreement.'
          );
          this.working.set(false);
        },
      });
  }

  private resetLocationForm(): void {
    this.locationAddress = '';
    this.locationLandmark = '';
    this.locationCity = '';
    this.locationLga = '';
    this.locationState = '';
    this.locationLatitude = null;
    this.locationLongitude = null;
    this.locationRadius = 10;
  }

  private resetAgreementForm(): void {
    this.agreementExpiryDate = '';
    this.agreementRenewalDate = '';
    this.agreementDocumentPath = '';
  }
}