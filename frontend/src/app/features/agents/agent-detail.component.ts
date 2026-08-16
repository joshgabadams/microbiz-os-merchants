import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { AgentApiService } from '../../core/agent-api.service';
import {
  Agent,
  AgentAgreement,
  AgentAgreementApprovalType,
  AgentAgreementSignatory,
  AgentAgreementSignatoryParty,
  AgentAgreementTemplate,
  AgentBeneficialOwner,
  AgentDocument,
  AgentLocation,
  AgentOperator,
  AgentTerminal,
  AgentTransaction,
  AgentTransactionType,
  AgentTrainingRecord,
  TrainingDocument,
} from '../../core/models/api.models';

@Component({
  selector: 'app-agent-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page">
      <!-- PAGE HEADER -->
      <div class="page-header">
        <div>
          <a routerLink="/agents" class="back-link">
            ← Back to Agents
          </a>

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
            {{ displayStatus(agent()!.status) }}
          </span>
        }
      </div>

      @if (loading()) {
        <section class="card">
          <p>Loading agent...</p>
        </section>
      }

      @if (error()) {
        <div class="error-box">
          {{ error() }}
        </div>
      }

      @if (!loading() && agent()) {
        <!-- ===================================================== -->
        <!-- LIFECYCLE -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Agent Lifecycle</h2>

              <p>
                Controlled progression from registration to active
                Agency Banking operations.
              </p>
            </div>
          </div>

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

                <div class="step-content">
                  <strong>{{ step.label }}</strong>

                  <span class="step-status">
                    {{ displayStatus(step.status) }}
                  </span>
                </div>
              </div>
            }
          </div>
        </section>

        <!-- ===================================================== -->
        <!-- OVERVIEW -->
        <!-- ===================================================== -->

        <section class="card">
          <h2>Overview</h2>

          <div class="details-grid">
            <div>
              <span class="label">Agent Code</span>
              <strong>{{ agent()!.agent_code }}</strong>
            </div>

            <div>
              <span class="label">Agent Type</span>
              <strong>{{ agent()!.agent_type }}</strong>
            </div>

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
                {{
                  agent()!.exclusive_relationship
                    ? 'YES'
                    : 'NO'
                }}
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

        <!-- ===================================================== -->
        <!-- AG-01: DRAFT -> KYC -->
        <!-- ===================================================== -->

        @if (agent()!.status === 'DRAFT') {
          <section class="card action-card">
            <div class="action-heading">
              <div class="action-number">1</div>

              <div>
                <h2>Submit Agent for KYC</h2>

                <p>
                  Registration has been created. Submit this agent
                  into the formal KYC and due-diligence workflow.
                </p>
              </div>
            </div>

            <button
              (click)="submitAgent()"
              [disabled]="working()"
            >
              {{
                working()
                  ? 'Submitting...'
                  : 'Submit Agent for KYC'
              }}
            </button>
          </section>
        }

        <!-- ===================================================== -->
        <!-- AG-02: KYC & DUE DILIGENCE -->
        <!-- ===================================================== -->

        @if (agent()!.status === 'PENDING_KYC') {
          <section class="card pending-card">
            <div class="action-heading">
              <div class="action-number">2</div>

              <div>
                <h2>KYC & Due Diligence</h2>

                <p>
                  Capture beneficial ownership and documentary
                  evidence before independent KYC completion.
                </p>
              </div>
            </div>

            <!-- KYC READINESS -->

            <div class="kyc-readiness">
              <div
                class="readiness-item"
                [class.ready]="owners().length > 0"
              >
                <strong>
                  {{ owners().length > 0 ? '✓' : '○' }}
                  Beneficial Owner
                </strong>

                <span>
                  {{
                    owners().length > 0
                      ? owners().length + ' recorded'
                      : 'At least one owner required'
                  }}
                </span>
              </div>

              <div
                class="readiness-item"
                [class.ready]="documents().length > 0"
              >
                <strong>
                  {{ documents().length > 0 ? '✓' : '○' }}
                  KYC Document
                </strong>

                <span>
                  {{
                    documents().length > 0
                      ? documents().length + ' recorded'
                      : 'At least one document required'
                  }}
                </span>
              </div>
            </div>

            <!-- BENEFICIAL OWNERS -->

            <div class="kyc-section">
              <div class="section-header">
                <div>
                  <h3>Beneficial Owners</h3>

                  <p>
                    Ownership, identity and screening information
                    for the agent.
                  </p>
                </div>

                <button
                  (click)="toggleOwnerForm()"
                  [disabled]="working()"
                >
                  {{
                    showOwnerForm()
                      ? 'Cancel'
                      : '+ Add Owner'
                  }}
                </button>
              </div>

              @if (showOwnerForm()) {
                <div class="form-grid">
                  <label>
                    Full Name *
                    <input
                      [(ngModel)]="ownerFullName"
                      placeholder="Full legal name"
                    />
                  </label>

                  <label>
                    Date of Birth
                    <input
                      type="date"
                      [(ngModel)]="ownerDateOfBirth"
                    />
                  </label>

                  <label>
                    Nationality
                    <input
                      maxlength="2"
                      [(ngModel)]="ownerNationality"
                      placeholder="NG"
                    />
                  </label>

                  <label>
                    Identification Type
                    <select
                      [(ngModel)]="ownerIdentificationType"
                    >
                      <option value="NIN">NIN</option>
                      <option value="BVN">BVN</option>
                      <option value="PASSPORT">
                        Passport
                      </option>
                      <option value="DRIVERS_LICENSE">
                        Driver's Licence
                      </option>
                      <option value="VOTERS_CARD">
                        Voter's Card
                      </option>
                      <option value="OTHER">
                        Other
                      </option>
                    </select>
                  </label>

                  <label>
                    Identification Number
                    <input
                      [(ngModel)]="
                        ownerIdentificationNumber
                      "
                      placeholder="Identification number"
                    />
                  </label>

                  <label>
                    Ownership %
                    <input
                      type="number"
                      min="0"
                      max="100"
                      [(ngModel)]="
                        ownerOwnershipPercentage
                      "
                    />
                  </label>

                  <label class="checkbox-field">
                    <input
                      type="checkbox"
                      [(ngModel)]="ownerIsDirector"
                    />

                    <span>Director</span>
                  </label>

                  <label class="checkbox-field">
                    <input
                      type="checkbox"
                      [(ngModel)]="ownerIsPep"
                    />

                    <span>
                      Politically Exposed Person
                    </span>
                  </label>

                  <label class="checkbox-field">
                    <input
                      type="checkbox"
                      [(ngModel)]="ownerSanctionsMatch"
                    />

                    <span>Sanctions Match</span>
                  </label>

                  <div class="form-actions">
                    <button
                      (click)="addOwner()"
                      [disabled]="working()"
                    >
                      {{
                        working()
                          ? 'Saving...'
                          : 'Save Beneficial Owner'
                      }}
                    </button>
                  </div>
                </div>
              }

              @if (owners().length === 0) {
                <div class="empty-state">
                  No beneficial owners recorded.
                </div>
              } @else {
                <div class="record-list">
                  @for (owner of owners(); track owner.id) {
                    <div class="record-card">
                      <div>
                        <strong>
                          {{ owner.full_name }}
                        </strong>

                        <div class="muted">
                          {{
                            owner.identification_type ||
                            'Identification'
                          }}:
                          {{
                            owner.identification_number ||
                            '—'
                          }}
                        </div>

                        @if (owner.nationality) {
                          <div class="muted">
                            Nationality:
                            {{ owner.nationality }}
                          </div>
                        }
                      </div>

                      <div class="record-meta">
                        @if (
                          owner.ownership_percentage !== null &&
                          owner.ownership_percentage !== undefined
                        ) {
                          <span>
                            Ownership:
                            {{
                              owner.ownership_percentage
                            }}%
                          </span>
                        }

                        @if (owner.is_director) {
                          <span class="meta-pill">
                            Director
                          </span>
                        }

                        @if (owner.is_pep) {
                          <span class="warning-pill">
                            PEP
                          </span>
                        }

                        @if (owner.sanctions_match) {
                          <span class="danger-pill">
                            Sanctions Match
                          </span>
                        }

                        @if (owner.screening_status) {
                          <span class="meta-pill">
                            {{
                              owner.screening_status
                            }}
                          </span>
                        }
                      </div>
                    </div>
                  }
                </div>
              }
            </div>

            <!-- DOCUMENTS -->

            <div class="kyc-section">
              <div class="section-header">
                <div>
                  <h3>KYC Documents</h3>

                  <p>
                    Identity and supporting documentary evidence
                    for the agent.
                  </p>
                </div>

                <button
                  (click)="toggleDocumentForm()"
                  [disabled]="working()"
                >
                  {{
                    showDocumentForm()
                      ? 'Cancel'
                      : '+ Add Document'
                  }}
                </button>
              </div>

              @if (showDocumentForm()) {
                <div class="form-grid">
                  <label>
                    Document Type *
                    <select
                      [(ngModel)]="documentType"
                    >
                      <option value="NATIONAL_ID">
                        National ID
                      </option>

                      <option value="NIN_SLIP">
                        NIN Slip
                      </option>

                      <option value="BVN_EVIDENCE">
                        BVN Evidence
                      </option>

                      <option value="PASSPORT">
                        Passport
                      </option>

                      <option value="DRIVERS_LICENSE">
                        Driver's Licence
                      </option>

                      <option value="CAC_CERTIFICATE">
                        CAC Certificate
                      </option>

                      <option value="UTILITY_BILL">
                        Utility Bill
                      </option>

                      <option value="OTHER">
                        Other
                      </option>
                    </select>
                  </label>

                  <label>
                    Document Number
                    <input
                      [(ngModel)]="documentNumber"
                      placeholder="Document number"
                    />
                  </label>

                  <label>
                    Storage Path
                    <input
                      [(ngModel)]="
                        documentStoragePath
                      "
                      placeholder="agent-documents/..."
                    />
                  </label>

                  <label>
                    Issued At
                    <input
                      type="date"
                      [(ngModel)]="documentIssuedAt"
                    />
                  </label>

                  <label>
                    Expires At
                    <input
                      type="date"
                      [(ngModel)]="documentExpiresAt"
                    />
                  </label>

                  <div class="form-actions">
                    <button
                      (click)="addDocument()"
                      [disabled]="working()"
                    >
                      {{
                        working()
                          ? 'Saving...'
                          : 'Save Document'
                      }}
                    </button>
                  </div>
                </div>
              }

              @if (documents().length === 0) {
                <div class="empty-state">
                  No KYC documents recorded.
                </div>
              } @else {
                <div class="record-list">
                  @for (
                    document of documents();
                    track document.id
                  ) {
                    <div class="record-card">
                      <div>
                        <strong>
                          {{
                            displayStatus(
                              document.document_type
                            )
                          }}
                        </strong>

                        <div class="muted">
                          Number:
                          {{
                            document.document_number ||
                            '—'
                          }}
                        </div>

                        @if (document.storage_path) {
                          <div class="muted">
                            File:
                            {{
                              document.storage_path
                            }}
                          </div>
                        }
                      </div>

                      <div class="record-meta">
                        @if (document.issued_at) {
                          <span>
                            Issued:
                            {{ document.issued_at }}
                          </span>
                        }

                        @if (document.expires_at) {
                          <span>
                            Expires:
                            {{ document.expires_at }}
                          </span>
                        }

                        <span
                          class="meta-pill"
                        >
                          {{
                            document.verification_status ||
                            'RECORDED'
                          }}
                        </span>
                      </div>
                    </div>
                  }
                </div>
              }
            </div>

            <!-- KYC COMPLETION -->

            <div class="kyc-completion">
              <div>
                <strong>KYC Readiness</strong>

                <p>
                  At least one beneficial owner and one document
                  are required before the independent KYC reviewer
                  can complete this stage.
                </p>
              </div>

              <button
                (click)="completeKyc()"
                [disabled]="
                  working() ||
                  owners().length === 0 ||
                  documents().length === 0
                "
              >
                {{
                  working()
                    ? 'Processing...'
                    : 'Complete KYC Review'
                }}
              </button>
            </div>
          </section>
        }

        <!-- ===================================================== -->
        <!-- AG-03: LOCATIONS -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Locations</h2>

              <p>
                Registered physical premises, GPS coordinates and
                independent verification.
              </p>
            </div>

            @if (
              agent()!.status ===
              'PENDING_LOCATION_VERIFICATION'
            ) {
              <button
                (click)="toggleLocationForm()"
                [disabled]="working()"
              >
                {{
                  showLocationForm()
                    ? 'Cancel'
                    : '+ Add Location'
                }}
              </button>
            }
          </div>

          @if (showLocationForm()) {
            <div class="form-grid">
              <label>
                Address Line 1
                <input
                  placeholder="12 Market Road"
                  [(ngModel)]="locationAddress"
                />
              </label>

              <label>
                Landmark
                <input
                  placeholder="Central Market"
                  [(ngModel)]="locationLandmark"
                />
              </label>

              <label>
                City
                <input
                  placeholder="Ikeja"
                  [(ngModel)]="locationCity"
                />
              </label>

              <label>
                Local Government
                <input
                  placeholder="Ikeja"
                  [(ngModel)]="locationLga"
                />
              </label>

              <label>
                State
                <input
                  placeholder="Lagos"
                  [(ngModel)]="locationState"
                />
              </label>

              <label>
                Latitude
                <input
                  type="number"
                  step="0.0000001"
                  placeholder="6.6018000"
                  [(ngModel)]="locationLatitude"
                />
              </label>

              <label>
                Longitude
                <input
                  type="number"
                  step="0.0000001"
                  placeholder="3.3515000"
                  [(ngModel)]="locationLongitude"
                />
              </label>

              <label>
                Approved Radius (metres)
                <input
                  type="number"
                  min="1"
                  placeholder="10"
                  [(ngModel)]="locationRadius"
                />
              </label>

              <div class="form-actions">
                <button
                  (click)="createLocation()"
                  [disabled]="working()"
                >
                  {{
                    working()
                      ? 'Saving...'
                      : 'Save Location'
                  }}
                </button>
              </div>
            </div>
          }

          @if (locations().length === 0) {
            <div class="empty-state">
              No locations recorded.
            </div>
          } @else {
            <div class="location-list">
              @for (
                location of locations();
                track location.id
              ) {
                <div class="location-card">
                  <div class="location-top">
                    <div>
                      <strong>
                        {{ location.address_line_1 }}
                      </strong>

                      <div class="muted">
                        {{ location.local_government }},
                        {{ location.state }}
                      </div>

                      @if (location.landmark) {
                        <div class="muted">
                          Landmark:
                          {{ location.landmark }}
                        </div>
                      }
                    </div>

                    <span
                      class="status-badge"
                      [class]="
                        location.verification_status ===
                        'VERIFIED'
                          ? 'status-active'
                          : location.verification_status ===
                              'REJECTED'
                            ? 'status-danger'
                            : 'status-pending'
                      "
                    >
                      {{
                        location.verification_status
                      }}
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
                      {{
                        location.approved_radius_metres
                      }}m
                    </span>

                    <span>
                      Status:
                      {{ location.status }}
                    </span>
                  </div>

                  @if (
                    location.verification_notes
                  ) {
                    <div class="verification-notes">
                      {{
                        location.verification_notes
                      }}
                    </div>
                  }

                  @if (
                    location.verification_status ===
                      'PENDING' &&
                    agent()!.status ===
                      'PENDING_LOCATION_VERIFICATION'
                  ) {
                    <div class="actions">
                      <button
                        (click)="
                          verifyLocation(location)
                        "
                        [disabled]="working()"
                      >
                        Verify Location
                      </button>

                      <button
                        class="danger"
                        (click)="
                          rejectLocation(location)
                        "
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

        <!-- ===================================================== -->
        <!-- AG-03: COMPLIANCE -->
        <!-- ===================================================== -->

        @if (
          agent()!.status ===
          'PENDING_COMPLIANCE_REVIEW'
        ) {
          <section class="card action-card">
            <div class="action-heading">
              <div class="action-number">4</div>

              <div>
                <h2>Compliance Review</h2>

                <p>
                  Location verification is complete. The agent
                  now requires independent compliance review.
                </p>
              </div>
            </div>

            <button
              (click)="completeComplianceReview()"
              [disabled]="working()"
            >
              {{
                working()
                  ? 'Processing...'
                  : 'Complete Compliance Review'
              }}
            </button>
          </section>
        }

        <!-- ===================================================== -->
        <!-- AG-03: APPROVAL -->
        <!-- ===================================================== -->

        @if (
          agent()!.status === 'PENDING_APPROVAL'
        ) {
          <section class="card action-card">
            <div class="action-heading">
              <div class="action-number">5</div>

              <div>
                <h2>Institutional Approval</h2>

                <p>
                  KYC, location verification and compliance review
                  are complete. The agent is ready for approval.
                </p>
              </div>
            </div>

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

        <!-- ===================================================== -->
        <!-- AG-03: AGREEMENTS -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Agreements</h2>

              <p>
                Versioned agency agreements, commercial terms
                and independent execution.
              </p>
            </div>

            @if (
              agent()!.status ===
              'AGREEMENT_PENDING'
            ) {
              <button
                [class.btn-outline]="showAgreementForm()"
                (click)="toggleAgreementForm()"
                [disabled]="working()"
              >
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
              <label class="full-width">
                Agreement Template
                <select [(ngModel)]="agreementTemplateId">
                  <option [ngValue]="null">— select a template —</option>
                  @for (tpl of templates(); track tpl.id) {
                    <option [ngValue]="tpl.id">
                      {{ tpl.name }} (v{{ tpl.version }})
                    </option>
                  }
                </select>
              </label>

              <label>
                Effective Date
                <input type="date" [(ngModel)]="agreementEffectiveDate" />
              </label>

              <label>
                Expiry Date
                <input
                  type="date"
                  [(ngModel)]="
                    agreementExpiryDate
                  "
                />
              </label>

              <label>
                Renewal Due Date
                <input
                  type="date"
                  [(ngModel)]="
                    agreementRenewalDate
                  "
                />
              </label>

              <label>
                Initial Term (months)
                <input type="number" [(ngModel)]="agreementInitialTermMonths" />
              </label>

              <label>
                Agent Termination Notice (days)
                <input type="number" [(ngModel)]="agreementAgentNoticeDays" />
              </label>

              <label>
                MicroBiz Termination Notice (days)
                <input type="number" [(ngModel)]="agreementMicrobizNoticeDays" />
              </label>

              <label>
                Dispute Resolution
                <select [(ngModel)]="agreementDisputeMethod">
                  <option value="MEDIATION">Mediation</option>
                  <option value="ARBITRATION">Arbitration</option>
                  <option value="COURTS">Courts</option>
                </select>
              </label>

              <label class="full-width">
                Special Conditions
                <textarea
                  rows="2"
                  [(ngModel)]="agreementSpecialConditions"
                ></textarea>
              </label>

              <label class="full-width">
                Document Path
                <input
                  placeholder="agent-agreements/..."
                  [(ngModel)]="
                    agreementDocumentPath
                  "
                />
              </label>

              <div class="full-width schedule-block">
                <h4>Schedule 2 &amp; 3 — Authorised Services, Limits, Fees</h4>

                <div class="service-row">
                  <label class="checkbox-label">
                    <input type="checkbox" [(ngModel)]="agreementCashInEnabled" />
                    Cash-in
                  </label>
                  <label>
                    Limit
                    <input type="number" [(ngModel)]="agreementCashInLimit" />
                  </label>
                  <label>
                    Customer Fee
                    <input [(ngModel)]="agreementCashInFee" />
                  </label>
                  <label>
                    Agent Commission
                    <input [(ngModel)]="agreementCashInCommission" />
                  </label>
                  <label>
                    Settlement
                    <input [(ngModel)]="agreementCashInSettlement" />
                  </label>
                </div>

                <div class="service-row">
                  <label class="checkbox-label">
                    <input type="checkbox" [(ngModel)]="agreementCashOutEnabled" />
                    Cash-out
                  </label>
                  <label>
                    Limit
                    <input type="number" [(ngModel)]="agreementCashOutLimit" />
                  </label>
                  <label>
                    Customer Fee
                    <input [(ngModel)]="agreementCashOutFee" />
                  </label>
                  <label>
                    Agent Commission
                    <input [(ngModel)]="agreementCashOutCommission" />
                  </label>
                  <label>
                    Settlement
                    <input [(ngModel)]="agreementCashOutSettlement" />
                  </label>
                </div>
              </div>

              <div class="form-actions">
                <button
                  (click)="createAgreement()"
                  [disabled]="working()"
                >
                  {{
                    working()
                      ? 'Creating...'
                      : 'Create Draft Agreement'
                  }}
                </button>
              </div>
            </div>
          }

          @if (agreements().length === 0) {
            <div class="empty-state">
              No agreements recorded.
            </div>
          } @else {
            <div class="table-wrapper">
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
                      <td>
                        <strong>
                          V{{ agreement.version }}
                        </strong>
                      </td>

                      <td>
                        <span
                          class="status-badge"
                          [class]="agreementStatusClass(agreement.status)"
                        >
                          {{ displayStatus(agreement.status) }}
                        </span>
                      </td>

                      <td>
                        {{
                          agreement.expiry_date ||
                          '—'
                        }}
                      </td>

                      <td>
                        {{
                          agreement.renewal_due_date ||
                          '—'
                        }}
                      </td>

                      <td>
                        {{
                          agreement.executed_at ||
                          '—'
                        }}
                      </td>

                      <td>
                        <button
                          class="btn-outline"
                          (click)="toggleAgreementExpanded(agreement.id)"
                        >
                          {{
                            expandedAgreementId() === agreement.id
                              ? 'Hide'
                              : 'Manage'
                          }}
                        </button>
                      </td>
                    </tr>

                    @if (expandedAgreementId() === agreement.id) {
                      <tr class="expanded-row">
                        <td colspan="6">
                          <div class="agreement-detail">
                            @if (agreement.status === 'DRAFT') {
                              <button
                                (click)="submitAgreementForReview(agreement)"
                                [disabled]="working()"
                              >
                                Submit for Internal Review
                              </button>
                            }

                            @if (agreement.status === 'PENDING_INTERNAL_REVIEW') {
                              <h4>Internal Approvals</h4>

                              <div class="checklist">
                                @for (
                                  type of approvalTypes;
                                  track type
                                ) {
                                  <div
                                    class="checklist-item"
                                    [class.done]="approvalStatus(agreement, type) === 'APPROVED'"
                                    [class.rejected]="approvalStatus(agreement, type) === 'REJECTED'"
                                  >
                                    <span class="checklist-icon">
                                      {{
                                        approvalStatus(agreement, type) === 'APPROVED'
                                          ? '✓'
                                          : approvalStatus(agreement, type) === 'REJECTED'
                                            ? '✕'
                                            : '•'
                                      }}
                                    </span>

                                    <span class="checklist-label">
                                      {{ displayStatus(type) }}
                                    </span>

                                    <span
                                      class="status-badge"
                                      [class]="
                                        approvalStatus(agreement, type) === 'APPROVED'
                                          ? 'status-active'
                                          : approvalStatus(agreement, type) === 'REJECTED'
                                            ? 'status-danger'
                                            : 'status-pending'
                                      "
                                    >
                                      {{ displayStatus(approvalStatus(agreement, type)) }}
                                    </span>

                                    @if (approvalStatus(agreement, type) === 'PENDING') {
                                      <div class="checklist-actions">
                                        <input
                                          class="notes-input"
                                          placeholder="Notes (optional)"
                                          [(ngModel)]="approvalNotes[type]"
                                        />
                                        <button
                                          class="btn-success"
                                          (click)="recordApproval(agreement, type, 'APPROVED')"
                                          [disabled]="working()"
                                        >
                                          Approve
                                        </button>
                                        <button
                                          class="btn-danger-outline"
                                          (click)="recordApproval(agreement, type, 'REJECTED')"
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

                            @if (agreement.status === 'APPROVED_FOR_EXECUTION') {
                              <button
                                (click)="sendAgreementForSignature(agreement)"
                                [disabled]="working()"
                              >
                                Send for Signature
                              </button>
                            }

                            @if (agreement.status === 'AWAITING_SIGNATURES') {
                              <h4>Signatures</h4>

                              <div class="checklist">
                                @for (party of signatoryParties; track party) {
                                  <div
                                    class="checklist-item"
                                    [class.done]="signatoryFor(agreement, party)"
                                  >
                                    <span class="checklist-icon">
                                      {{ signatoryFor(agreement, party) ? '✓' : '•' }}
                                    </span>

                                    <span class="checklist-label">
                                      {{ displayStatus(party) }}
                                    </span>

                                    @if (signatoryFor(agreement, party); as sig) {
                                      <span class="status-badge status-active">
                                        Signed by {{ sig.signatory_name }}
                                      </span>
                                    } @else {
                                      <span class="status-badge status-pending">
                                        Not yet signed
                                      </span>
                                    }
                                  </div>
                                }
                              </div>

                              <div class="form-grid">
                                <label>
                                  Party
                                  <select [(ngModel)]="signatureParty">
                                    <option value="MICROBIZ">MicroBiz</option>
                                    <option value="AGENT">Agent</option>
                                  </select>
                                </label>
                                <label>
                                  Signatory Name
                                  <input [(ngModel)]="signatoryName" />
                                </label>
                                <label>
                                  Title / Capacity
                                  <input [(ngModel)]="signatoryTitle" />
                                </label>
                                <label>
                                  Method
                                  <select [(ngModel)]="signatureMethod">
                                    <option value="WET_SIGNATURE_UPLOAD">
                                      Wet Signature (Upload)
                                    </option>
                                    <option value="E_SIGNATURE">E-Signature</option>
                                  </select>
                                </label>
                                <label class="full-width">
                                  Signature Evidence
                                  <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    [disabled]="uploadingEvidence()"
                                    (change)="onSignatureEvidenceSelected($event, agreement)"
                                  />
                                  @if (uploadingEvidence()) {
                                    <span class="muted">Uploading...</span>
                                  } @else if (signatureEvidenceFileName()) {
                                    <span class="muted">
                                      ✓ Uploaded: {{ signatureEvidenceFileName() }}
                                    </span>
                                  } @else {
                                    <span class="muted">
                                      Upload a scanned wet signature or an
                                      already e-signed document (PDF/JPG/PNG, max 10MB).
                                    </span>
                                  }
                                </label>
                                <div class="form-actions">
                                  <button
                                    class="btn-outline"
                                    (click)="recordAgreementSignature(agreement)"
                                    [disabled]="working() || !signatureEvidencePath"
                                  >
                                    Record Signature
                                  </button>
                                  <button
                                    class="btn-success"
                                    (click)="executeAgreement(agreement)"
                                    [disabled]="working()"
                                  >
                                    Execute Agreement
                                  </button>
                                </div>
                              </div>
                            }

                            @if (agreement.status === 'EXECUTED') {
                              <div class="empty-state">
                                Executed {{ agreement.executed_at }}. This
                                agreement is now frozen.
                              </div>
                            }

                            @if (agreement.status === 'REJECTED') {
                              <div class="empty-state">
                                This agreement draft was rejected during
                                internal review.
                              </div>
                            }

                            @if (agreement.status === 'SUPERSEDED') {
                              <div class="empty-state">
                                This version was replaced by a later
                                executed agreement and is kept for record
                                only.
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

        <!-- ===================================================== -->
        <!-- AG-04: TRAINING -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Training</h2>

              <p>
                Agency banking training guide delivery and
                acknowledgement.
              </p>
            </div>

            <button
              class="btn-outline"
              (click)="toggleTrainingDocumentForm()"
              [disabled]="uploadingTrainingDocument()"
            >
              {{
                showTrainingDocumentForm()
                  ? 'Cancel'
                  : '+ Upload Training Guide'
              }}
            </button>
          </div>

          @if (showTrainingDocumentForm()) {
            <div class="form-grid">
              <label>
                Guide Name
                <input [(ngModel)]="trainingDocumentName" />
              </label>

              <label>
                Version
                <input
                  [(ngModel)]="trainingDocumentVersion"
                  placeholder="v1"
                />
              </label>

              <label class="full-width">
                Guide File
                <input
                  type="file"
                  accept=".pdf,.doc,.docx"
                  [disabled]="uploadingTrainingDocument()"
                  (change)="onTrainingDocumentFileSelected($event)"
                />
                <span class="muted">
                  PDF/DOC/DOCX, max 10MB.
                </span>
              </label>

              <div class="form-actions">
                <button
                  (click)="uploadTrainingDocument()"
                  [disabled]="uploadingTrainingDocument()"
                >
                  {{
                    uploadingTrainingDocument()
                      ? 'Uploading...'
                      : 'Add Training Guide'
                  }}
                </button>
              </div>
            </div>
          }

          @if ((agent()!.training_records ?? []).length === 0) {
            <div class="empty-state">
              No training activity recorded.
            </div>
          } @else {
            <div class="checklist">
              @for (
                record of agent()!.training_records!;
                track record.id
              ) {
                <div
                  class="checklist-item"
                  [class.done]="record.acknowledged_at"
                >
                  <span class="checklist-icon">
                    {{ record.acknowledged_at ? '✓' : '•' }}
                  </span>

                  <span class="checklist-label">
                    {{ record.training_document?.name }}
                    (v{{ record.training_document_version }})
                  </span>

                  <span
                    class="status-badge"
                    [class]="
                      record.acknowledged_at
                        ? 'status-active'
                        : 'status-pending'
                    "
                  >
                    {{
                      record.acknowledged_at
                        ? 'Acknowledged ' + record.acknowledged_at
                        : 'Downloaded, awaiting acknowledgement'
                    }}
                  </span>

                  @if (
                    !record.acknowledged_at &&
                    agent()!.status === 'TRAINING_PENDING'
                  ) {
                    <button
                      class="btn-success"
                      (click)="acknowledgeTraining(record)"
                      [disabled]="working()"
                    >
                      Acknowledge
                    </button>
                  }
                </div>
              }
            </div>
          }

          @if (
            agent()!.status === 'TRAINING_PENDING' &&
            !hasPendingTrainingRecord()
          ) {
            <div class="form-grid">
              <label class="full-width">
                Issue Training Guide
                <select [(ngModel)]="selectedTrainingDocumentId">
                  <option [ngValue]="null">
                    — select a guide —
                  </option>
                  @for (
                    doc of trainingDocuments();
                    track doc.id
                  ) {
                    <option [ngValue]="doc.id">
                      {{ doc.name }} (v{{ doc.version }})
                    </option>
                  }
                </select>
              </label>

              <div class="form-actions">
                <button
                  (click)="recordTrainingDownload()"
                  [disabled]="working()"
                >
                  Record Download
                </button>
              </div>
            </div>
          }
        </section>

        <!-- ===================================================== -->
        <!-- AG-04: OPERATORS -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Operators</h2>

              <p>
                Platform users authorised to operate this agent's
                locations.
              </p>
            </div>

            <button
              class="btn-outline"
              (click)="toggleOperatorForm()"
              [disabled]="working()"
            >
              {{
                showOperatorForm()
                  ? 'Cancel'
                  : '+ Assign Operator'
              }}
            </button>
          </div>

          @if (showOperatorForm()) {
            <div class="form-grid">
              <label>
                Location
                <select [(ngModel)]="operatorLocationId">
                  <option [ngValue]="null">
                    — select a location —
                  </option>
                  @for (loc of locations(); track loc.id) {
                    <option [ngValue]="loc.id">
                      {{ loc.address_line_1 }}, {{ loc.city }}
                    </option>
                  }
                </select>
              </label>

              <label>
                User ID
                <input
                  type="number"
                  [(ngModel)]="operatorUserId"
                  placeholder="platform user ID"
                />
                <span class="muted">
                  The numeric ID of an existing platform login
                  account -- confirm it with the user directly,
                  there's no user directory here.
                </span>
              </label>

              <label>
                Role
                <input
                  [(ngModel)]="operatorRole"
                  placeholder="e.g. Cashier, Manager"
                />
              </label>

              <div class="form-actions">
                <button
                  (click)="createOperator()"
                  [disabled]="working()"
                >
                  Assign Operator
                </button>
              </div>
            </div>
          }

          @if (operators().length === 0) {
            <div class="empty-state">
              No operators assigned.
            </div>
          } @else {
            <div class="checklist">
              @for (operator of operators(); track operator.id) {
                <div
                  class="checklist-item"
                  [class.done]="operator.status === 'ACTIVE'"
                  [class.rejected]="operator.status === 'SUSPENDED'"
                >
                  <span class="checklist-icon">
                    {{
                      operator.status === 'ACTIVE'
                        ? '✓'
                        : operator.status === 'SUSPENDED'
                          ? '✕'
                          : '•'
                    }}
                  </span>

                  <span class="checklist-label">
                    {{ operator.role }} (user #{{ operator.user_id }})
                  </span>

                  <span
                    class="status-badge"
                    [class]="
                      operator.status === 'ACTIVE'
                        ? 'status-active'
                        : operator.status === 'SUSPENDED'
                          ? 'status-danger'
                          : 'status-pending'
                    "
                  >
                    {{ displayStatus(operator.status) }}
                  </span>

                  <div class="checklist-actions">
                    @if (operator.status !== 'ACTIVE') {
                      <button
                        class="btn-success"
                        (click)="activateOperator(operator)"
                        [disabled]="working()"
                      >
                        Activate
                      </button>
                    }

                    @if (operator.status === 'ACTIVE') {
                      <button
                        class="btn-danger-outline"
                        (click)="suspendOperator(operator)"
                        [disabled]="working()"
                      >
                        Suspend
                      </button>
                    }
                  </div>
                </div>
              }
            </div>
          }
        </section>

        <!-- ===================================================== -->
        <!-- AG-04/05: TERMINALS -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Terminals</h2>

              <p>
                POS/device registry and geo-fence status per
                location.
              </p>
            </div>

            <button
              class="btn-outline"
              (click)="toggleTerminalForm()"
              [disabled]="working()"
            >
              {{
                showTerminalForm()
                  ? 'Cancel'
                  : '+ Register Terminal'
              }}
            </button>
          </div>

          @if (showTerminalForm()) {
            <div class="form-grid">
              <label>
                Location
                <select [(ngModel)]="terminalLocationId">
                  <option [ngValue]="null">
                    — select a location —
                  </option>
                  @for (loc of locations(); track loc.id) {
                    <option [ngValue]="loc.id">
                      {{ loc.address_line_1 }}, {{ loc.city }}
                    </option>
                  }
                </select>
              </label>

              <label>
                Terminal ID
                <input [(ngModel)]="terminalIdValue" />
              </label>

              <label>
                Serial Number
                <input [(ngModel)]="terminalSerialNumber" />
              </label>

              <label>
                Device Model
                <input [(ngModel)]="terminalDeviceModel" />
              </label>

              <label>
                Provider
                <input [(ngModel)]="terminalProvider" />
              </label>

              <label>
                Registered Latitude
                <input
                  type="number"
                  [(ngModel)]="terminalLatitude"
                />
              </label>

              <label>
                Registered Longitude
                <input
                  type="number"
                  [(ngModel)]="terminalLongitude"
                />
              </label>

              <div class="form-actions">
                <button
                  (click)="createTerminal()"
                  [disabled]="working()"
                >
                  Register Terminal
                </button>
              </div>
            </div>
          }

          @if (terminals().length === 0) {
            <div class="empty-state">
              No terminals registered.
            </div>
          } @else {
            <div class="record-list">
              @for (terminal of terminals(); track terminal.id) {
                <div class="record-card">
                  <div>
                    <strong>{{ terminal.terminal_id }}</strong>

                    <div class="muted">
                      Serial: {{ terminal.serial_number }}
                      @if (terminal.device_model) {
                        · {{ terminal.device_model }}
                      }
                    </div>

                    <div class="muted">
                      Geo-fence radius:
                      {{ terminal.geo_fence_radius_metres }}m
                    </div>

                    @if (terminal.last_ip_address) {
                      <div class="muted">
                        Last seen from IP
                        {{ terminal.last_ip_address }}
                        @if (terminal.ip_city) {
                          ({{ terminal.ip_city }},
                          {{ terminal.ip_state }},
                          {{ terminal.ip_country }})
                        }
                        @if (terminal.ip_location_mismatch) {
                          <span class="status-badge status-danger">
                            IP location mismatch
                          </span>
                        }
                      </div>
                    } @else {
                      <div class="muted">
                        No IP location signal yet -- recorded on
                        the terminal's next heartbeat.
                      </div>
                    }
                  </div>

                  <div class="record-meta">
                    <span
                      class="status-badge"
                      [class]="
                        terminal.status === 'ACTIVE'
                          ? 'status-active'
                          : terminal.status === 'SUSPENDED'
                            ? 'status-danger'
                            : 'status-pending'
                      "
                    >
                      {{ displayStatus(terminal.status) }}
                    </span>

                    @if (terminal.status !== 'ACTIVE') {
                      <button
                        class="btn-success"
                        (click)="activateTerminal(terminal)"
                        [disabled]="working()"
                      >
                        Activate
                      </button>
                    }

                    @if (terminal.status === 'ACTIVE') {
                      <button
                        class="btn-danger-outline"
                        (click)="suspendTerminal(terminal)"
                        [disabled]="working()"
                      >
                        Suspend
                      </button>
                    }
                  </div>
                </div>
              }
            </div>
          }
        </section>

        <!-- ===================================================== -->
        <!-- AG-07/08/09: TRANSACTIONS -->
        <!-- ===================================================== -->

        <section class="card">
          <div class="section-header">
            <div>
              <h2>Transactions</h2>

              <p>
                Cash-in, cash-out and transfer, routed through the
                agent operation guard.
              </p>
            </div>

            <button
              class="btn-outline"
              (click)="toggleTransactionForm()"
              [disabled]="working()"
            >
              {{
                showTransactionForm()
                  ? 'Cancel'
                  : '+ New Transaction'
              }}
            </button>
          </div>

          @if (showTransactionForm()) {
            <div class="form-grid">
              <label>
                Type
                <select [(ngModel)]="transactionType">
                  <option value="CASH_IN">Cash-In</option>
                  <option value="CASH_OUT">Cash-Out</option>
                  <option value="TRANSFER">Transfer</option>
                </select>
              </label>

              <label>
                Operator
                <select [(ngModel)]="txOperatorId">
                  <option [ngValue]="null">
                    — select an operator —
                  </option>
                  @for (op of operators(); track op.id) {
                    <option [ngValue]="op.id">
                      {{ op.role }} (user #{{ op.user_id }}) --
                      {{ displayStatus(op.status) }}
                    </option>
                  }
                </select>
              </label>

              <label>
                Terminal
                <select
                  [(ngModel)]="txTerminalId"
                  (ngModelChange)="onTransactionTerminalChange()"
                >
                  <option [ngValue]="null">
                    — select a terminal —
                  </option>
                  @for (t of terminals(); track t.id) {
                    <option [ngValue]="t.id">
                      {{ t.terminal_id }} -- {{ displayStatus(t.status) }}
                    </option>
                  }
                </select>
              </label>

              @if (transactionType === 'CASH_IN' || transactionType === 'CASH_OUT') {
                <label>
                  Customer Account ID
                  <input
                    type="number"
                    [(ngModel)]="txCustomerAccountId"
                  />
                  <span class="muted">
                    Numeric customer account ID from FINCORE360 --
                    no lookup here yet.
                  </span>
                </label>
              }

              @if (transactionType === 'CASH_OUT') {
                <label class="checkbox-field">
                  <input
                    type="checkbox"
                    [(ngModel)]="txCustomerAuthenticated"
                  />
                  Customer authenticated
                </label>
              }

              @if (transactionType === 'TRANSFER') {
                <label>
                  From Account ID
                  <input
                    type="number"
                    [(ngModel)]="txFromAccountId"
                  />
                </label>

                <label>
                  To Account ID
                  <input
                    type="number"
                    [(ngModel)]="txToAccountId"
                  />
                </label>
              }

              <label>
                Amount
                <input
                  type="number"
                  [(ngModel)]="txAmount"
                />
              </label>

              <label>
                Latitude
                <input
                  type="number"
                  [(ngModel)]="txLatitude"
                />
                <span class="muted">
                  Auto-filled from the selected terminal's
                  registered position.
                </span>
              </label>

              <label>
                Longitude
                <input
                  type="number"
                  [(ngModel)]="txLongitude"
                />
              </label>

              <label>
                Customer Reference
                <input [(ngModel)]="txCustomerReference" />
              </label>

              <label class="full-width">
                Narration
                <input [(ngModel)]="txNarration" />
              </label>

              <div class="form-actions">
                <button
                  (click)="submitTransaction()"
                  [disabled]="working()"
                >
                  {{
                    working() ? 'Processing...' : 'Submit Transaction'
                  }}
                </button>
              </div>
            </div>
          }

          @if (transactions().length === 0) {
            <div class="empty-state">
              No transactions recorded.
            </div>
          } @else {
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Transaction No</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Terminal</th>
                    <th>Date</th>
                  </tr>
                </thead>

                <tbody>
                  @for (tx of transactions(); track tx.id) {
                    <tr>
                      <td>{{ tx.transaction_no }}</td>
                      <td>{{ displayStatus(tx.transaction_type) }}</td>
                      <td>
                        <span class="status-badge status-neutral">
                          {{ displayStatus(tx.status) }}
                        </span>
                      </td>
                      <td>{{ tx.amount }}</td>
                      <td>{{ tx.terminal?.terminal_id ?? '—' }}</td>
                      <td>{{ tx.transaction_date ?? tx.created_at ?? '—' }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          }
        </section>

        <!-- ===================================================== -->
        <!-- AG-03 EXIT / AG-04 ENTRY -->
        <!-- ===================================================== -->

        @if (
          agent()!.status === 'TRAINING_PENDING'
        ) {
          <section class="card next-stage">
            <div class="completion-icon">
              ✓
            </div>

            <div>
              <h2>AG-03 Complete</h2>

              <p>
                Registration, KYC, location verification,
                compliance review, approval and agreement
                execution are complete.
              </p>

              <strong>
                Next stage: AG-04 Training, Operators &
                Terminals
              </strong>
            </div>
          </section>
        }

        @if (
          agent()!.status === 'TERMINAL_PENDING'
        ) {
          <section class="card next-stage">
            <div>
              <h2>
                Terminal Provisioning Pending
              </h2>

              <p>
                Training has been completed. Assign an active
                operator and an active terminal above, then
                activate the agent -- the server re-checks all of
                agreement, training, location, operator and
                terminal before allowing it.
              </p>

              <button
                (click)="activateAgent()"
                [disabled]="working()"
              >
                Activate Agent
              </button>
            </div>
          </section>
        }

        @if (agent()!.status === 'ACTIVE') {
          <section class="card active-card">
            <div class="completion-icon">
              ✓
            </div>

            <div>
              <h2>Agent Active</h2>

              <p>
                This agent is currently operational in the
                Agency Banking network.
              </p>
            </div>
          </section>
        }
      }
    </div>
  `,
  styles: [`
    :host {
      display: block;
      font-family: var(--font-sans);
      color: var(--color-foreground);
    }

    .page {
      max-width: 1240px;
      margin: 0 auto;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      gap: var(--space-5);
      align-items: flex-start;
      margin-bottom: var(--space-5);
    }

    h1 {
      margin: 6px 0 4px;
      font-size: var(--font-size-2xl);
      font-weight: 700;
      color: var(--color-primary);
    }

    h2 {
      margin: 0 0 8px;
      font-size: var(--font-size-lg);
      font-weight: 600;
      color: var(--color-primary);
    }

    h3 {
      margin: 0 0 5px;
      color: var(--color-primary);
      font-size: var(--font-size-base);
      font-weight: 600;
    }

    p {
      line-height: var(--line-height-base);
    }

    .back-link {
      text-decoration: none;
      color: var(--color-primary);
      font-size: var(--font-size-sm);
      font-weight: 500;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .agent-meta {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-2);
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
      align-items: flex-start;
      gap: var(--space-4);
      margin-bottom: var(--space-4);
    }

    .section-header p {
      margin: 0;
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    .details-grid {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(180px, 1fr));
      gap: var(--space-4);
    }

    .details-grid > div {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .label {
      font-size: var(--font-size-xs);
      color: var(--color-muted);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    /* ---------- Lifecycle ---------- */

    .lifecycle {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(190px, 1fr));
      gap: var(--space-3);
    }

    .lifecycle-step {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-3);
      display: flex;
      gap: var(--space-2);
      min-width: 0;
      font-size: var(--font-size-xs);
      color: var(--color-muted);
      background: var(--color-background);
      transition: background var(--transition-fast), border-color var(--transition-fast);
    }

    .lifecycle-step.complete {
      background: var(--color-success-bg);
      border-color: var(--color-success);
      color: var(--color-success);
    }

    .lifecycle-step.current {
      background: var(--color-warning-bg);
      border-color: var(--color-warning);
      color: var(--color-warning);
      box-shadow:
        0 0 0 1px rgba(146, 64, 14, 0.15);
    }

    .step-dot {
      flex: 0 0 auto;
      font-size: 1rem;
      line-height: 1.2;
    }

    .step-content {
      min-width: 0;
    }

    .step-content strong {
      display: block;
      margin-bottom: 4px;
      color: var(--color-foreground);
    }

    .step-status {
      display: block;
      overflow-wrap: anywhere;
      word-break: break-word;
      line-height: 1.25;
    }

    /* ---------- Status ---------- */

    .status-badge {
      display: inline-block;
      font-size: var(--font-size-xs);
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .status-active {
      background: var(--color-success-bg);
      color: var(--color-success);
    }

    .status-pending {
      background: var(--color-warning-bg);
      color: var(--color-warning);
    }

    .status-info {
      background: var(--color-info-bg);
      color: var(--color-info);
    }

    .status-danger {
      background: var(--color-danger-bg);
      color: var(--color-danger);
    }

    .status-neutral {
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    /* ---------- Forms ---------- */

    .form-grid {
      margin: var(--space-4) 0;
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-3);
      padding: var(--space-4);
      background: var(--color-background);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
    }

    .form-grid label {
      display: flex;
      flex-direction: column;
      gap: 5px;
      font-size: var(--font-size-sm);
      font-weight: 500;
      color: var(--color-foreground);
    }

    .form-grid input,
    .form-grid select,
    .form-grid textarea {
      box-sizing: border-box;
      width: 100%;
      padding: 8px 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: var(--color-surface);
      font-size: var(--font-size-sm);
      color: var(--color-foreground);
      transition: border-color var(--transition-fast);
    }

    .form-grid input:focus-visible,
    .form-grid select:focus-visible,
    .form-grid textarea:focus-visible {
      border-color: var(--color-primary);
    }

    .full-width {
      grid-column: 1 / -1;
    }

    .form-actions {
      grid-column: 1 / -1;
      display: flex;
      gap: var(--space-2);
    }

    .checkbox-field {
      flex-direction: row !important;
      align-items: center;
      gap: 8px !important;
    }

    .checkbox-field input {
      width: auto;
    }

    button {
      padding: 8px 14px;
      border: 1px solid transparent;
      border-radius: var(--radius-sm);
      background: var(--color-primary);
      color: var(--color-on-primary);
      font-size: var(--font-size-sm);
      font-weight: 600;
      cursor: pointer;
      transition: background var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
    }

    button:hover:not(:disabled) {
      background: var(--color-primary-hover);
    }

    button:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    button.danger {
      background: var(--color-danger);
    }

    button.btn-outline {
      background: transparent;
      border-color: var(--color-border);
      color: var(--color-foreground);
    }

    button.btn-outline:hover:not(:disabled) {
      background: var(--color-muted-bg);
      border-color: var(--color-muted);
    }

    button.btn-danger-outline {
      background: transparent;
      border-color: var(--color-danger-bg);
      color: var(--color-danger);
    }

    button.btn-danger-outline:hover:not(:disabled) {
      background: var(--color-danger-bg);
      border-color: var(--color-danger);
    }

    button.btn-success {
      background: var(--color-success);
    }

    button.btn-success:hover:not(:disabled) {
      background: var(--color-success);
      filter: brightness(0.92);
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-2);
      margin-top: var(--space-3);
    }

    /* ---------- Workflow ---------- */

    .action-card {
      border-left: 4px solid var(--color-primary);
    }

    .pending-card {
      border-left: 4px solid var(--color-accent);
      background: var(--color-warning-bg);
    }

    .action-heading {
      display: flex;
      gap: var(--space-3);
      align-items: flex-start;
      margin-bottom: var(--space-3);
    }

    .action-heading p {
      margin: 0;
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    .action-number {
      width: 28px;
      height: 28px;
      flex: 0 0 28px;
      border-radius: 50%;
      background: var(--color-primary);
      color: var(--color-on-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-size-xs);
      font-weight: 600;
    }

    /* ---------- KYC ---------- */

    .kyc-readiness {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));
      gap: var(--space-2);
      margin: var(--space-4) 0 var(--space-5);
    }

    .readiness-item {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-3);
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: var(--color-muted);
      background: var(--color-surface);
    }

    .readiness-item.ready {
      background: var(--color-success-bg);
      border-color: var(--color-success);
      color: var(--color-success);
    }

    .readiness-item span {
      font-size: var(--font-size-xs);
    }

    .kyc-section {
      border-top: 1px solid var(--color-border);
      padding-top: var(--space-4);
      margin-top: var(--space-4);
    }

    .record-list {
      display: grid;
      gap: var(--space-2);
    }

    .record-card {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-3);
      display: flex;
      justify-content: space-between;
      gap: var(--space-3);
      background: var(--color-surface);
    }

    .record-meta {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      align-items: center;
      gap: 6px;
      font-size: var(--font-size-xs);
    }

    .meta-pill,
    .warning-pill,
    .danger-pill {
      border-radius: 999px;
      padding: 3px 8px;
      font-size: var(--font-size-xs);
      font-weight: 600;
    }

    .meta-pill {
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    .warning-pill {
      background: var(--color-warning-bg);
      color: var(--color-warning);
    }

    .danger-pill {
      background: var(--color-danger-bg);
      color: var(--color-danger);
    }

    .kyc-completion {
      margin-top: var(--space-5);
      border-top: 1px solid var(--color-border);
      padding-top: var(--space-4);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: var(--space-4);
    }

    .kyc-completion p {
      margin: 4px 0 0;
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    /* ---------- Locations ---------- */

    .location-list {
      display: grid;
      gap: var(--space-2);
    }

    .location-card {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-3);
    }

    .location-top {
      display: flex;
      justify-content: space-between;
      gap: var(--space-4);
    }

    .location-details {
      margin-top: var(--space-2);
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-4);
      color: var(--color-muted);
      font-size: var(--font-size-sm);
    }

    .verification-notes {
      margin-top: var(--space-2);
      padding: 8px 10px;
      border-radius: var(--radius-sm);
      background: var(--color-background);
      color: var(--color-foreground);
      font-size: var(--font-size-sm);
    }

    .muted {
      color: var(--color-muted);
      font-size: var(--font-size-sm);
      margin-top: 2px;
    }

    .empty-state {
      padding: var(--space-4);
      border: 1px dashed var(--color-border);
      border-radius: var(--radius-md);
      color: var(--color-muted);
      background: var(--color-background);
      font-size: var(--font-size-sm);
      text-align: center;
    }

    /* ---------- Agreements ---------- */

    .table-wrapper {
      width: 100%;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: var(--space-2) var(--space-2);
      border-bottom: 1px solid var(--color-border);
      text-align: left;
      font-size: var(--font-size-sm);
      white-space: nowrap;
    }

    th {
      color: var(--color-muted);
      background: var(--color-background);
      font-size: var(--font-size-xs);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    /* ---------- Agreement workflow ---------- */

    .schedule-block {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-3);
      margin-top: 6px;
      background: var(--color-surface);
    }

    .schedule-block h4 {
      margin: 0 0 10px;
      font-size: var(--font-size-sm);
      font-weight: 600;
      color: var(--color-foreground);
    }

    .service-row {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-3);
      align-items: flex-end;
      padding: var(--space-2) 0;
      border-top: 1px solid var(--color-border);
    }

    .service-row:first-of-type {
      border-top: none;
    }

    .service-row label {
      font-size: var(--font-size-xs);
      color: var(--color-muted);
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .service-row input {
      width: 100px;
    }

    .checkbox-label {
      flex-direction: row !important;
      align-items: center;
      gap: 6px !important;
    }

    .expanded-row td {
      background: var(--color-background);
      padding: var(--space-4);
    }

    .agreement-detail h4 {
      margin: var(--space-2) 0 var(--space-2);
      font-size: var(--font-size-sm);
      font-weight: 600;
      color: var(--color-foreground);
    }

    .checklist {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
      margin-bottom: var(--space-3);
    }

    .checklist-item {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      flex-wrap: wrap;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      padding: var(--space-2) var(--space-3);
      background: var(--color-surface);
    }

    .checklist-item.done {
      border-color: var(--color-success);
      background: var(--color-success-bg);
    }

    .checklist-item.rejected {
      border-color: var(--color-danger);
      background: var(--color-danger-bg);
    }

    .checklist-icon {
      width: 22px;
      height: 22px;
      flex: 0 0 22px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-size-xs);
      font-weight: 700;
      background: var(--color-muted-bg);
      color: var(--color-muted);
    }

    .checklist-item.done .checklist-icon {
      background: var(--color-success);
      color: var(--color-on-primary);
    }

    .checklist-item.rejected .checklist-icon {
      background: var(--color-danger);
      color: var(--color-on-primary);
    }

    .checklist-label {
      font-size: var(--font-size-sm);
      font-weight: 600;
      color: var(--color-foreground);
      flex: 1 1 auto;
      min-width: 140px;
    }

    .checklist-actions {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      flex-wrap: wrap;
    }

    .notes-input {
      flex: 1;
      min-width: 160px;
      padding: 6px 10px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      font-size: var(--font-size-sm);
      background: var(--color-surface);
    }

    /* ---------- General ---------- */

    .error-box {
      padding: var(--space-3);
      background: var(--color-danger-bg);
      color: var(--color-danger);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-4);
      font-size: var(--font-size-sm);
    }

    .next-stage {
      background: var(--color-info-bg);
      border-color: var(--color-info);
      display: flex;
      gap: var(--space-4);
      align-items: flex-start;
    }

    .active-card {
      background: var(--color-success-bg);
      border-color: var(--color-success);
      display: flex;
      gap: var(--space-4);
      align-items: flex-start;
    }

    .completion-icon {
      width: 34px;
      height: 34px;
      flex: 0 0 34px;
      border-radius: 50%;
      background: var(--color-success);
      color: var(--color-on-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    @media (max-width: 760px) {
      .page-header,
      .section-header,
      .location-top,
      .record-card,
      .kyc-completion {
        flex-direction: column;
      }

      .record-meta {
        justify-content: flex-start;
      }

      .status-badge {
        white-space: normal;
      }

      .lifecycle {
        grid-template-columns: 1fr;
      }

      .checklist-item {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  `],
})
export class AgentDetailComponent implements OnInit {
  // ---------- Core Agent State ----------

  agent = signal<Agent | null>(null);

  owners = signal<AgentBeneficialOwner[]>([]);
  documents = signal<AgentDocument[]>([]);
  locations = signal<AgentLocation[]>([]);
  agreements = signal<AgentAgreement[]>([]);

  loading = signal(true);
  working = signal(false);
  error = signal<string | null>(null);

  // ---------- Form Visibility ----------

  showOwnerForm = signal(false);
  showDocumentForm = signal(false);
  showLocationForm = signal(false);
  showAgreementForm = signal(false);

  // ---------- KYC Owner Form ----------

  ownerFullName = '';
  ownerDateOfBirth = '';
  ownerNationality = 'NG';
  ownerIdentificationType = 'NIN';
  ownerIdentificationNumber = '';
  ownerOwnershipPercentage: number | null = null;

  ownerIsDirector = false;
  ownerIsPep = false;
  ownerSanctionsMatch = false;

  // ---------- KYC Document Form ----------

  documentType = 'NATIONAL_ID';
  documentNumber = '';
  documentStoragePath = '';
  documentIssuedAt = '';
  documentExpiresAt = '';

  // ---------- Location Form ----------

  locationAddress = '';
  locationLandmark = '';
  locationCity = '';
  locationLga = '';
  locationState = '';

  locationLatitude: number | null = null;
  locationLongitude: number | null = null;
  locationRadius = 10;

  // ---------- Agreement Form ----------

  templates = signal<AgentAgreementTemplate[]>([]);
  expandedAgreementId = signal<number | null>(null);

  readonly approvalTypes: AgentAgreementApprovalType[] = [
    'RISK',
    'COMPLIANCE',
    'LEGAL',
    'BUSINESS_OWNER',
  ];

  readonly signatoryParties: AgentAgreementSignatoryParty[] = [
    'MICROBIZ',
    'AGENT',
  ];

  agreementTemplateId: number | null = null;
  agreementEffectiveDate = '';
  agreementExpiryDate = '';
  agreementRenewalDate = '';
  agreementInitialTermMonths: number | null = 12;
  agreementAgentNoticeDays: number | null = 30;
  agreementMicrobizNoticeDays: number | null = 60;
  agreementDisputeMethod = 'ARBITRATION';
  agreementSpecialConditions = '';
  agreementDocumentPath = '';

  agreementCashInEnabled = true;
  agreementCashInLimit: number | null = 100000;
  agreementCashInFee = '0';
  agreementCashInCommission = '1%';
  agreementCashInSettlement = 'T+1';

  agreementCashOutEnabled = true;
  agreementCashOutLimit: number | null = 100000;
  agreementCashOutFee = '0';
  agreementCashOutCommission = '1%';
  agreementCashOutSettlement = 'T+1';

  approvalNotes: Record<string, string> = {
    RISK: '',
    COMPLIANCE: '',
    LEGAL: '',
    BUSINESS_OWNER: '',
  };

  signatureParty: AgentAgreementSignatoryParty = 'MICROBIZ';
  signatoryName = '';
  signatoryTitle = '';
  signatureMethod: 'WET_SIGNATURE_UPLOAD' | 'E_SIGNATURE' =
    'WET_SIGNATURE_UPLOAD';
  signatureEvidencePath = '';
  signatureEvidenceFileName = signal<string | null>(null);
  uploadingEvidence = signal(false);

  // ---------- Training (AG-04) ----------

  trainingDocuments = signal<TrainingDocument[]>([]);
  showTrainingDocumentForm = signal(false);
  uploadingTrainingDocument = signal(false);

  trainingDocumentName = '';
  trainingDocumentVersion = '';
  trainingDocumentFile: File | null = null;

  selectedTrainingDocumentId: number | null = null;

  // ---------- Operators (AG-04) ----------

  operators = signal<AgentOperator[]>([]);
  showOperatorForm = signal(false);

  operatorLocationId: number | null = null;
  operatorUserId: number | null = null;
  operatorRole = '';

  // ---------- Terminals (AG-04/05) ----------

  terminals = signal<AgentTerminal[]>([]);
  showTerminalForm = signal(false);

  terminalLocationId: number | null = null;
  terminalIdValue = '';
  terminalSerialNumber = '';
  terminalDeviceModel = '';
  terminalProvider = '';
  terminalLatitude: number | null = null;
  terminalLongitude: number | null = null;

  // ---------- Transactions (AG-07/08/09) ----------

  transactions = signal<AgentTransaction[]>([]);
  showTransactionForm = signal(false);
  transactionType: AgentTransactionType = 'CASH_IN';

  txOperatorId: number | null = null;
  txTerminalId: number | null = null;
  txCustomerAccountId: number | null = null;
  txFromAccountId: number | null = null;
  txToAccountId: number | null = null;
  txAmount: number | null = null;
  txCustomerAuthenticated = false;
  txCustomerReference = '';
  txNarration = '';
  txLatitude: number | null = null;
  txLongitude: number | null = null;

  // ---------- Lifecycle ----------

  readonly lifecycleSteps = [
    {
      label: 'Registered',
      status: 'DRAFT',
    },
    {
      label: 'KYC',
      status: 'PENDING_KYC',
    },
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
    {
      label: 'Terminal',
      status: 'TERMINAL_PENDING',
    },
    {
      label: 'Active',
      status: 'ACTIVE',
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

    if (!this.agentId) {
      this.error.set(
        'Invalid agent identifier.'
      );

      this.loading.set(false);
      return;
    }

    this.reload();
  }

  // ============================================================
  // LOAD ALL AGENT DATA
  // ============================================================

  reload(): void {
    this.loading.set(true);
    this.error.set(null);

    forkJoin({
      agent:
        this.api.show(this.agentId),

      owners:
        this.api.listOwners(this.agentId),

      documents:
        this.api.listDocuments(this.agentId),

      locations:
        this.api.listLocations(this.agentId),

      agreements:
        this.api.listAgreements(this.agentId),

      templates:
        this.api.listAgreementTemplates(),

      trainingDocuments:
        this.api.listTrainingDocuments(),

      operators:
        this.api.listOperators(this.agentId),

      terminals:
        this.api.listTerminals(this.agentId),

      transactions:
        this.api.listTransactions(this.agentId),
    }).subscribe({
      next: (result) => {
        this.agent.set(
          result.agent.data
        );

        this.owners.set(
          result.owners.data
        );

        this.documents.set(
          result.documents.data
        );

        this.locations.set(
          result.locations.data
        );

        this.agreements.set(
          result.agreements.data
        );

        this.templates.set(
          result.templates.data
        );

        this.trainingDocuments.set(
          result.trainingDocuments.data
        );

        this.operators.set(
          result.operators.data
        );

        this.terminals.set(
          result.terminals.data
        );

        this.transactions.set(
          result.transactions.data
        );

        this.loading.set(false);
      },

      error: (err) => {
        this.error.set(
          err?.error?.message ??
            'Failed to load agent.'
        );

        this.loading.set(false);
      },
    });
  }

  // ============================================================
  // DISPLAY HELPERS
  // ============================================================

  displayStatus(status: string): string {
    return status
      .replaceAll('_', ' ')
      .toLowerCase()
      .replace(
        /\b\w/g,
        (character) =>
          character.toUpperCase()
      );
  }

  statusClass(status: string): string {
    if (status === 'ACTIVE') {
      return 'status-active';
    }

    if (
      status === 'REJECTED' ||
      status === 'SUSPENDED' ||
      status === 'TERMINATED' ||
      status === 'BLACKLISTED' ||
      status === 'EXPIRED'
    ) {
      return 'status-danger';
    }

    if (
      status === 'DRAFT' ||
      status === 'PENDING_KYC' ||
      status ===
        'PENDING_LOCATION_VERIFICATION' ||
      status ===
        'PENDING_COMPLIANCE_REVIEW' ||
      status === 'PENDING_APPROVAL' ||
      status === 'AGREEMENT_PENDING' ||
      status === 'TRAINING_PENDING' ||
      status === 'TERMINAL_PENDING'
    ) {
      return 'status-pending';
    }

    return 'status-neutral';
  }

  agreementStatusClass(status: string): string {
    if (status === 'EXECUTED') {
      return 'status-active';
    }

    if (status === 'REJECTED') {
      return 'status-danger';
    }

    if (
      status === 'DRAFT' ||
      status === 'PENDING_INTERNAL_REVIEW'
    ) {
      return 'status-pending';
    }

    if (
      status === 'APPROVED_FOR_EXECUTION' ||
      status === 'AWAITING_SIGNATURES'
    ) {
      return 'status-info';
    }

    return 'status-neutral';
  }

  isCurrentStep(
    status: string
  ): boolean {
    return (
      this.agent()?.status === status
    );
  }

  isStepComplete(
    status: string
  ): boolean {
    const currentStatus =
      this.agent()?.status;

    if (!currentStatus) {
      return false;
    }

    const currentIndex =
      this.lifecycleOrder.indexOf(
        currentStatus
      );

    const stepIndex =
      this.lifecycleOrder.indexOf(
        status
      );

    if (
      currentIndex === -1 ||
      stepIndex === -1
    ) {
      return false;
    }

    return currentIndex > stepIndex;
  }

  // ============================================================
  // AG-01: SUBMIT FOR KYC
  // ============================================================

  submitAgent(): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .submit(this.agentId)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },

        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to submit agent for KYC.'
          );

          this.working.set(false);
        },
      });
  }

  // ============================================================
  // AG-02: KYC
  // ============================================================

  toggleOwnerForm(): void {
    this.showOwnerForm.update(
      (value) => !value
    );
  }

  toggleDocumentForm(): void {
    this.showDocumentForm.update(
      (value) => !value
    );
  }

  addOwner(): void {
    const fullName =
      this.ownerFullName.trim();

    if (!fullName) {
      this.error.set(
        'Beneficial owner full name is required.'
      );

      return;
    }

    const nationality =
      this.ownerNationality
        .trim()
        .toUpperCase();

    if (
      nationality &&
      nationality.length > 2
    ) {
      this.error.set(
        'Nationality must use a maximum 2-character country code.'
      );

      return;
    }

    if (
      this.ownerOwnershipPercentage !== null &&
      (
        this.ownerOwnershipPercentage < 0 ||
        this.ownerOwnershipPercentage > 100
      )
    ) {
      this.error.set(
        'Ownership percentage must be between 0 and 100.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .addOwner(
        this.agentId,
        {
          full_name:
            fullName,

          date_of_birth:
            this.ownerDateOfBirth ||
            undefined,

          nationality:
            nationality ||
            undefined,

          identification_type:
            this.ownerIdentificationType ||
            undefined,

          identification_number:
            this.ownerIdentificationNumber
              .trim() ||
            undefined,

          ownership_percentage:
            this.ownerOwnershipPercentage ??
            undefined,

          is_director:
            this.ownerIsDirector,

          is_pep:
            this.ownerIsPep,

          sanctions_match:
            this.ownerSanctionsMatch,
        }
      )
      .subscribe({
        next: () => {
          this.resetOwnerForm();
          this.showOwnerForm.set(false);
          this.working.set(false);
          this.reload();
        },

        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to add beneficial owner.'
          );

          this.working.set(false);
        },
      });
  }

  addDocument(): void {
    const type =
      this.documentType.trim();

    if (!type) {
      this.error.set(
        'Document type is required.'
      );

      return;
    }

    if (
      this.documentIssuedAt &&
      this.documentExpiresAt &&
      this.documentExpiresAt <
        this.documentIssuedAt
    ) {
      this.error.set(
        'Document expiry date cannot be earlier than the issue date.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .addDocument(
        this.agentId,
        {
          document_type:
            type,

          document_number:
            this.documentNumber
              .trim() ||
            undefined,

          storage_path:
            this.documentStoragePath
              .trim() ||
            undefined,

          issued_at:
            this.documentIssuedAt ||
            undefined,

          expires_at:
            this.documentExpiresAt ||
            undefined,
        }
      )
      .subscribe({
        next: () => {
          this.resetDocumentForm();
          this.showDocumentForm.set(false);
          this.working.set(false);
          this.reload();
        },

        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to add KYC document.'
          );

          this.working.set(false);
        },
      });
  }

  completeKyc(): void {
    if (
      this.owners().length === 0 ||
      this.documents().length === 0
    ) {
      this.error.set(
        'At least one beneficial owner and one document are required.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .completeKyc(
        this.agentId
      )
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },

        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to complete KYC review.'
          );

          this.working.set(false);
        },
      });
  }

  // ============================================================
  // AG-03: LOCATIONS
  // ============================================================

  toggleLocationForm(): void {
    this.showLocationForm.update(
      (value) => !value
    );
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

    if (
      !Number.isFinite(
        this.locationLatitude
      ) ||
      !Number.isFinite(
        this.locationLongitude
      )
    ) {
      this.error.set(
        'Latitude and longitude must be valid numeric values.'
      );

      return;
    }

    if (
      this.locationLatitude < -90 ||
      this.locationLatitude > 90
    ) {
      this.error.set(
        'Latitude must be between -90 and 90.'
      );

      return;
    }

    if (
      this.locationLongitude < -180 ||
      this.locationLongitude > 180
    ) {
      this.error.set(
        'Longitude must be between -180 and 180.'
      );

      return;
    }

    if (this.locationRadius < 1) {
      this.error.set(
        'Approved radius must be at least 1 metre.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .createLocation(
        this.agentId,
        {
          address_line_1:
            this.locationAddress.trim(),

          landmark:
            this.locationLandmark
              .trim() ||
            undefined,

          city:
            this.locationCity.trim(),

          local_government:
            this.locationLga.trim(),

          state:
            this.locationState.trim(),

          latitude:
            this.locationLatitude,

          longitude:
            this.locationLongitude,

          approved_radius_metres:
            this.locationRadius,
        }
      )
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

  verifyLocation(
    location: AgentLocation
  ): void {
    const notes =
      window.prompt(
        'Verification notes:'
      ) ?? '';

    this.working.set(true);
    this.error.set(null);

    this.api
      .verifyLocation(
        this.agentId,
        location.id,
        notes.trim() ||
          undefined
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

  rejectLocation(
    location: AgentLocation
  ): void {
    const reason =
      window.prompt(
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

  // ============================================================
  // AG-03: COMPLIANCE
  // ============================================================

  completeComplianceReview(): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .completeComplianceReview(
        this.agentId
      )
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

  // ============================================================
  // AG-03: APPROVAL
  // ============================================================

  approveAgent(): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .approve(
        this.agentId
      )
      .subscribe({
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
    const reason =
      window.prompt(
        'Reason for rejecting this agent:'
      );

    if (!reason?.trim()) {
      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .reject(
        this.agentId,
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
              'Failed to reject agent.'
          );

          this.working.set(false);
        },
      });
  }

  // ============================================================
  // AG-03: AGREEMENTS
  // ============================================================

  toggleAgreementForm(): void {
    this.showAgreementForm.update(
      (value) => !value
    );
  }

  toggleAgreementExpanded(agreementId: number): void {
    this.expandedAgreementId.update((current) =>
      current === agreementId ? null : agreementId
    );
  }

  createAgreement(): void {
    if (!this.agreementTemplateId) {
      this.error.set(
        'An agreement template must be selected.'
      );

      return;
    }

    if (
      !this.agreementExpiryDate
    ) {
      this.error.set(
        'Agreement expiry date is required.'
      );

      return;
    }

    if (
      this.agreementRenewalDate &&
      this.agreementRenewalDate >
        this.agreementExpiryDate
    ) {
      this.error.set(
        'Renewal due date cannot be later than the agreement expiry date.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    const permittedServices = [
      {
        service: 'cash-in',
        enabled: this.agreementCashInEnabled,
        limit: this.agreementCashInLimit ?? undefined,
      },
      {
        service: 'cash-out',
        enabled: this.agreementCashOutEnabled,
        limit: this.agreementCashOutLimit ?? undefined,
      },
    ];

    const commercialTerms = [
      {
        service: 'cash-in',
        customer_fee: this.agreementCashInFee || undefined,
        agent_commission: this.agreementCashInCommission || undefined,
        settlement_timing: this.agreementCashInSettlement || undefined,
      },
      {
        service: 'cash-out',
        customer_fee: this.agreementCashOutFee || undefined,
        agent_commission: this.agreementCashOutCommission || undefined,
        settlement_timing: this.agreementCashOutSettlement || undefined,
      },
    ];

    this.api
      .createAgreement(
        this.agentId,
        {
          agreement_template_id: this.agreementTemplateId,

          effective_date:
            this.agreementEffectiveDate || undefined,

          expiry_date:
            this.agreementExpiryDate,

          renewal_due_date:
            this.agreementRenewalDate ||
            undefined,

          initial_term_months:
            this.agreementInitialTermMonths ?? undefined,

          agent_termination_notice_days:
            this.agreementAgentNoticeDays ?? undefined,

          microbiz_termination_notice_days:
            this.agreementMicrobizNoticeDays ?? undefined,

          dispute_resolution_method:
            this.agreementDisputeMethod || undefined,

          special_conditions:
            this.agreementSpecialConditions.trim() || undefined,

          permitted_services: permittedServices,
          commercial_terms: commercialTerms,

          document_path:
            this.agreementDocumentPath
              .trim() ||
            undefined,
        }
      )
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

  submitAgreementForReview(agreement: AgentAgreement): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .submitAgreementForReview(this.agentId, agreement.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to submit agreement for review.'
          );
          this.working.set(false);
        },
      });
  }

  recordApproval(
    agreement: AgentAgreement,
    approvalType: AgentAgreementApprovalType,
    decision: 'APPROVED' | 'REJECTED'
  ): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .recordAgreementApproval(
        this.agentId,
        agreement.id,
        approvalType,
        decision,
        this.approvalNotes[approvalType] || undefined
      )
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to record approval decision.'
          );
          this.working.set(false);
        },
      });
  }

  sendAgreementForSignature(agreement: AgentAgreement): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .sendAgreementForSignature(this.agentId, agreement.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to send agreement for signature.'
          );
          this.working.set(false);
        },
      });
  }

  onSignatureEvidenceSelected(
    event: Event,
    agreement: AgentAgreement
  ): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
      return;
    }

    this.uploadingEvidence.set(true);
    this.error.set(null);

    this.api
      .uploadAgreementSignatureEvidence(
        this.agentId,
        agreement.id,
        file
      )
      .subscribe({
        next: (result) => {
          this.signatureEvidencePath = result.data.path;
          this.signatureEvidenceFileName.set(file.name);
          this.uploadingEvidence.set(false);
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to upload signature evidence.'
          );
          this.uploadingEvidence.set(false);
          input.value = '';
        },
      });
  }

  recordAgreementSignature(agreement: AgentAgreement): void {
    if (!this.signatoryName.trim() || !this.signatureEvidencePath.trim()) {
      this.error.set(
        'Signatory name and uploaded signature evidence are required.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .recordAgreementSignature(this.agentId, agreement.id, {
        party: this.signatureParty,
        signatory_name: this.signatoryName.trim(),
        signatory_title: this.signatoryTitle.trim() || undefined,
        signature_method: this.signatureMethod,
        signature_evidence_path: this.signatureEvidencePath.trim(),
      })
      .subscribe({
        next: () => {
          this.signatoryName = '';
          this.signatoryTitle = '';
          this.signatureEvidencePath = '';
          this.signatureEvidenceFileName.set(null);
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to record signature.'
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

  approvalStatus(
    agreement: AgentAgreement,
    type: AgentAgreementApprovalType
  ): 'PENDING' | 'APPROVED' | 'REJECTED' {
    return (
      agreement.approvals?.find((a) => a.approval_type === type)?.status ??
      'PENDING'
    );
  }

  signatoryFor(
    agreement: AgentAgreement,
    party: AgentAgreementSignatoryParty
  ): AgentAgreementSignatory | null {
    return agreement.signatories?.find((s) => s.party === party) ?? null;
  }

  // ============================================================
  // FORM RESET HELPERS
  // ============================================================

  private resetOwnerForm(): void {
    this.ownerFullName = '';
    this.ownerDateOfBirth = '';
    this.ownerNationality = 'NG';
    this.ownerIdentificationType = 'NIN';
    this.ownerIdentificationNumber = '';
    this.ownerOwnershipPercentage = null;

    this.ownerIsDirector = false;
    this.ownerIsPep = false;
    this.ownerSanctionsMatch = false;
  }

  private resetDocumentForm(): void {
    this.documentType =
      'NATIONAL_ID';

    this.documentNumber = '';
    this.documentStoragePath = '';
    this.documentIssuedAt = '';
    this.documentExpiresAt = '';
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
    this.agreementTemplateId = null;
    this.agreementEffectiveDate = '';
    this.agreementExpiryDate = '';
    this.agreementRenewalDate = '';
    this.agreementInitialTermMonths = 12;
    this.agreementAgentNoticeDays = 30;
    this.agreementMicrobizNoticeDays = 60;
    this.agreementDisputeMethod = 'ARBITRATION';
    this.agreementSpecialConditions = '';
    this.agreementDocumentPath = '';
  }

  // ---------- AG-04: Training ----------

  toggleTrainingDocumentForm(): void {
    this.showTrainingDocumentForm.update(
      (current) => !current
    );
  }

  onTrainingDocumentFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.trainingDocumentFile = input.files?.[0] ?? null;
  }

  uploadTrainingDocument(): void {
    if (
      !this.trainingDocumentName.trim() ||
      !this.trainingDocumentVersion.trim() ||
      !this.trainingDocumentFile
    ) {
      this.error.set(
        'Guide name, version and file are all required.'
      );

      return;
    }

    this.uploadingTrainingDocument.set(true);
    this.error.set(null);

    this.api
      .createTrainingDocument(
        this.trainingDocumentName.trim(),
        this.trainingDocumentVersion.trim(),
        this.trainingDocumentFile
      )
      .subscribe({
        next: () => {
          this.trainingDocumentName = '';
          this.trainingDocumentVersion = '';
          this.trainingDocumentFile = null;
          this.showTrainingDocumentForm.set(false);
          this.uploadingTrainingDocument.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to upload training guide.'
          );
          this.uploadingTrainingDocument.set(false);
        },
      });
  }

  hasPendingTrainingRecord(): boolean {
    return (
      this.agent()?.training_records?.some(
        (record) => !record.acknowledged_at
      ) ?? false
    );
  }

  recordTrainingDownload(): void {
    if (!this.selectedTrainingDocumentId) {
      this.error.set(
        'Select a training guide to issue.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .recordTrainingDownload(
        this.agentId,
        this.selectedTrainingDocumentId
      )
      .subscribe({
        next: () => {
          this.selectedTrainingDocumentId = null;
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to record training download.'
          );
          this.working.set(false);
        },
      });
  }

  acknowledgeTraining(record: AgentTrainingRecord): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .acknowledgeTraining(this.agentId, record.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to acknowledge training.'
          );
          this.working.set(false);
        },
      });
  }

  // ---------- AG-04: Operators ----------

  toggleOperatorForm(): void {
    this.showOperatorForm.update(
      (current) => !current
    );
  }

  createOperator(): void {
    if (
      !this.operatorLocationId ||
      !this.operatorUserId ||
      !this.operatorRole.trim()
    ) {
      this.error.set(
        'Location, user ID and role are all required.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .createOperator(this.agentId, {
        agent_location_id: this.operatorLocationId,
        user_id: this.operatorUserId,
        role: this.operatorRole.trim(),
      })
      .subscribe({
        next: () => {
          this.operatorLocationId = null;
          this.operatorUserId = null;
          this.operatorRole = '';
          this.showOperatorForm.set(false);
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to assign operator.'
          );
          this.working.set(false);
        },
      });
  }

  activateOperator(operator: AgentOperator): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .activateOperator(operator.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to activate operator.'
          );
          this.working.set(false);
        },
      });
  }

  suspendOperator(operator: AgentOperator): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .suspendOperator(operator.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to suspend operator.'
          );
          this.working.set(false);
        },
      });
  }

  // ---------- AG-04/05: Terminals ----------

  toggleTerminalForm(): void {
    this.showTerminalForm.update(
      (current) => !current
    );
  }

  createTerminal(): void {
    if (
      !this.terminalLocationId ||
      !this.terminalIdValue.trim() ||
      !this.terminalSerialNumber.trim() ||
      this.terminalLatitude === null ||
      this.terminalLongitude === null
    ) {
      this.error.set(
        'Location, terminal ID, serial number and GPS coordinates are all required.'
      );

      return;
    }

    this.working.set(true);
    this.error.set(null);

    this.api
      .createTerminal({
        agent_id: this.agentId,
        agent_location_id: this.terminalLocationId,
        terminal_id: this.terminalIdValue.trim(),
        serial_number: this.terminalSerialNumber.trim(),
        device_model: this.terminalDeviceModel.trim() || undefined,
        provider: this.terminalProvider.trim() || undefined,
        registered_latitude: this.terminalLatitude,
        registered_longitude: this.terminalLongitude,
      })
      .subscribe({
        next: () => {
          this.terminalLocationId = null;
          this.terminalIdValue = '';
          this.terminalSerialNumber = '';
          this.terminalDeviceModel = '';
          this.terminalProvider = '';
          this.terminalLatitude = null;
          this.terminalLongitude = null;
          this.showTerminalForm.set(false);
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to register terminal.'
          );
          this.working.set(false);
        },
      });
  }

  activateTerminal(terminal: AgentTerminal): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .activateTerminal(terminal.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to activate terminal.'
          );
          this.working.set(false);
        },
      });
  }

  suspendTerminal(terminal: AgentTerminal): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .suspendTerminal(terminal.id)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ??
              'Failed to suspend terminal.'
          );
          this.working.set(false);
        },
      });
  }

  // ---------- AG-07/08/09: Transactions ----------

  toggleTransactionForm(): void {
    this.showTransactionForm.update(
      (current) => !current
    );
  }

  onTransactionTerminalChange(): void {
    const terminal = this.terminals().find(
      (t) => t.id === this.txTerminalId
    );

    if (terminal) {
      this.txLatitude = Number(terminal.registered_latitude);
      this.txLongitude = Number(terminal.registered_longitude);
    }
  }

  submitTransaction(): void {
    if (
      !this.txOperatorId ||
      !this.txTerminalId ||
      !this.txAmount ||
      this.txLatitude === null ||
      this.txLongitude === null
    ) {
      this.error.set(
        'Operator, terminal, amount and GPS coordinates are all required.'
      );

      return;
    }

    const idempotencyKey = crypto.randomUUID();

    this.working.set(true);
    this.error.set(null);

    if (this.transactionType === 'CASH_IN') {
      if (!this.txCustomerAccountId) {
        this.error.set('Customer account is required.');
        this.working.set(false);

        return;
      }

      this.api
        .cashIn(this.agentId, {
          operator_id: this.txOperatorId,
          terminal_id: this.txTerminalId,
          customer_account_id: this.txCustomerAccountId,
          amount: this.txAmount,
          idempotency_key: idempotencyKey,
          latitude: this.txLatitude,
          longitude: this.txLongitude,
          customer_reference: this.txCustomerReference.trim() || undefined,
          narration: this.txNarration.trim() || undefined,
        })
        .subscribe(this.transactionObserver());

      return;
    }

    if (this.transactionType === 'CASH_OUT') {
      if (!this.txCustomerAccountId) {
        this.error.set('Customer account is required.');
        this.working.set(false);

        return;
      }

      this.api
        .cashOut(this.agentId, {
          operator_id: this.txOperatorId,
          terminal_id: this.txTerminalId,
          customer_account_id: this.txCustomerAccountId,
          amount: this.txAmount,
          idempotency_key: idempotencyKey,
          latitude: this.txLatitude,
          longitude: this.txLongitude,
          customer_authenticated: this.txCustomerAuthenticated,
          customer_reference: this.txCustomerReference.trim() || undefined,
          narration: this.txNarration.trim() || undefined,
        })
        .subscribe(this.transactionObserver());

      return;
    }

    if (!this.txFromAccountId || !this.txToAccountId) {
      this.error.set(
        'Both the source and destination customer accounts are required.'
      );
      this.working.set(false);

      return;
    }

    this.api
      .transfer(this.agentId, {
        operator_id: this.txOperatorId,
        terminal_id: this.txTerminalId,
        from_account_id: this.txFromAccountId,
        to_account_id: this.txToAccountId,
        amount: this.txAmount,
        idempotency_key: idempotencyKey,
        latitude: this.txLatitude,
        longitude: this.txLongitude,
        narration: this.txNarration.trim() || undefined,
      })
      .subscribe(this.transactionObserver());
  }

  private transactionObserver() {
    return {
      next: () => {
        this.resetTransactionForm();
        this.working.set(false);
        this.reload();
      },
      error: (err: any) => {
        this.error.set(
          err?.error?.message ?? 'Transaction failed.'
        );
        this.working.set(false);
      },
    };
  }

  activateAgent(): void {
    this.working.set(true);
    this.error.set(null);

    this.api
      .activate(this.agentId)
      .subscribe({
        next: () => {
          this.working.set(false);
          this.reload();
        },
        error: (err) => {
          this.error.set(
            err?.error?.message ?? 'Failed to activate agent.'
          );
          this.working.set(false);
        },
      });
  }

  private resetTransactionForm(): void {
    this.txOperatorId = null;
    this.txTerminalId = null;
    this.txCustomerAccountId = null;
    this.txFromAccountId = null;
    this.txToAccountId = null;
    this.txAmount = null;
    this.txCustomerAuthenticated = false;
    this.txCustomerReference = '';
    this.txNarration = '';
    this.txLatitude = null;
    this.txLongitude = null;
    this.showTransactionForm.set(false);
  }
}