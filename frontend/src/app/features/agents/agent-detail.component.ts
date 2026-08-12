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
                          [class]="
                            agreement.status === 'EXECUTED'
                              ? 'status-active'
                              : agreement.status === 'REJECTED'
                                ? 'status-danger'
                                : agreement.status === 'DRAFT'
                                  ? 'status-pending'
                                  : 'status-neutral'
                          "
                        >
                          {{ agreement.status }}
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

                              <div class="approval-grid">
                                @for (
                                  type of approvalTypes;
                                  track type
                                ) {
                                  <div class="approval-row">
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
                                      {{ type }}: {{ approvalStatus(agreement, type) }}
                                    </span>

                                    @if (approvalStatus(agreement, type) === 'PENDING') {
                                      <input
                                        class="notes-input"
                                        placeholder="Notes (optional)"
                                        [(ngModel)]="approvalNotes[type]"
                                      />
                                      <button
                                        (click)="recordApproval(agreement, type, 'APPROVED')"
                                        [disabled]="working()"
                                      >
                                        Approve
                                      </button>
                                      <button
                                        (click)="recordApproval(agreement, type, 'REJECTED')"
                                        [disabled]="working()"
                                      >
                                        Reject
                                      </button>
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

                              <div class="approval-grid">
                                @for (party of signatoryParties; track party) {
                                  <div class="approval-row">
                                    @if (signatoryFor(agreement, party); as sig) {
                                      <span class="status-badge status-active">
                                        {{ party }} signed by {{ sig.signatory_name }}
                                      </span>
                                    } @else {
                                      <span class="status-badge status-pending">
                                        {{ party }}: not yet signed
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
                                  Signature Evidence Path
                                  <input
                                    placeholder="/storage/agreements/..."
                                    [(ngModel)]="signatureEvidencePath"
                                  />
                                </label>
                                <div class="form-actions">
                                  <button
                                    (click)="recordAgreementSignature(agreement)"
                                    [disabled]="working()"
                                  >
                                    Record Signature
                                  </button>
                                  <button
                                    (click)="executeAgreement(agreement)"
                                    [disabled]="working()"
                                  >
                                    Execute
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
                Training has been completed. Terminal controls
                belong to the AG-04 workflow.
              </p>
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
    }

    .page {
      max-width: 1240px;
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
      color: #1e2761;
    }

    h2 {
      margin: 0 0 8px;
      font-size: 1.05rem;
      color: #1e2761;
    }

    h3 {
      margin: 0 0 5px;
      color: #1e2761;
      font-size: 0.95rem;
    }

    p {
      line-height: 1.5;
    }

    .back-link {
      text-decoration: none;
      color: #1e2761;
      font-size: 0.9rem;
    }

    .agent-meta {
      display: flex;
      flex-wrap: wrap;
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
      margin-bottom: 14px;
    }

    .section-header p {
      margin: 0;
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

    /* ---------- Lifecycle ---------- */

    .lifecycle {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(190px, 1fr));
      gap: 12px;
    }

    .lifecycle-step {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 12px;
      display: flex;
      gap: 10px;
      min-width: 0;
      font-size: 0.78rem;
      color: #777;
      background: #fafafa;
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
      box-shadow:
        0 0 0 1px rgba(236, 203, 119, 0.2);
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

    .status-neutral {
      background: #eef1f8;
      color: #4d5875;
    }

    /* ---------- Forms ---------- */

    .form-grid {
      margin: 15px 0;
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      padding: 14px;
      background: #f8f9fc;
      border-radius: 8px;
    }

    .form-grid label {
      display: flex;
      flex-direction: column;
      gap: 5px;
      font-size: 0.8rem;
      color: #555;
    }

    .form-grid input,
    .form-grid select {
      box-sizing: border-box;
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      background: white;
    }

    .full-width {
      grid-column: 1 / -1;
    }

    .form-actions {
      grid-column: 1 / -1;
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
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }

    /* ---------- Workflow ---------- */

    .action-card {
      border-left: 4px solid #1e2761;
    }

    .pending-card {
      border-left: 4px solid #d0a22c;
      background: #fffdf7;
    }

    .action-heading {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 12px;
    }

    .action-heading p {
      margin: 0;
      color: #666;
      font-size: 0.88rem;
    }

    .action-number {
      width: 28px;
      height: 28px;
      flex: 0 0 28px;
      border-radius: 50%;
      background: #1e2761;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 600;
    }

    /* ---------- KYC ---------- */

    .kyc-readiness {
      display: grid;
      grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
      margin: 15px 0 20px;
    }

    .readiness-item {
      border: 1px solid #e1e5ee;
      border-radius: 8px;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: #777;
      background: white;
    }

    .readiness-item.ready {
      background: #e7f6ec;
      border-color: #b9dfc7;
      color: #26623c;
    }

    .readiness-item span {
      font-size: 0.78rem;
    }

    .kyc-section {
      border-top: 1px solid #eee;
      padding-top: 18px;
      margin-top: 18px;
    }

    .record-list {
      display: grid;
      gap: 10px;
    }

    .record-card {
      border: 1px solid #e1e5ee;
      border-radius: 8px;
      padding: 12px;
      display: flex;
      justify-content: space-between;
      gap: 14px;
      background: white;
    }

    .record-meta {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      align-items: center;
      gap: 6px;
      font-size: 0.78rem;
    }

    .meta-pill,
    .warning-pill,
    .danger-pill {
      border-radius: 12px;
      padding: 3px 7px;
      font-size: 0.72rem;
    }

    .meta-pill {
      background: #eef1f8;
      color: #4d5875;
    }

    .warning-pill {
      background: #fff3d5;
      color: #795900;
    }

    .danger-pill {
      background: #f6d9d5;
      color: #a6432f;
    }

    .kyc-completion {
      margin-top: 20px;
      border-top: 1px solid #eee;
      padding-top: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
    }

    .kyc-completion p {
      margin: 4px 0 0;
      color: #666;
      font-size: 0.84rem;
    }

    /* ---------- Locations ---------- */

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

    .verification-notes {
      margin-top: 10px;
      padding: 8px 10px;
      border-radius: 5px;
      background: #f8f9fc;
      color: #555;
      font-size: 0.8rem;
    }

    .muted {
      color: #777;
      font-size: 0.82rem;
      margin-top: 2px;
    }

    .empty-state {
      padding: 14px;
      border: 1px dashed #ccd3e5;
      border-radius: 7px;
      color: #777;
      background: #fafbfe;
      font-size: 0.85rem;
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
      padding: 9px;
      border-bottom: 1px solid #eee;
      text-align: left;
      font-size: 0.83rem;
      white-space: nowrap;
    }

    th {
      color: #555;
      background: #fafbfe;
    }

    /* ---------- Agreement workflow ---------- */

    .schedule-block {
      border: 1px solid #e3e7f1;
      border-radius: 8px;
      padding: 12px;
      margin-top: 6px;
    }

    .schedule-block h4 {
      margin: 0 0 10px;
      font-size: 0.85rem;
      color: #4d5875;
    }

    .service-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-end;
      padding: 8px 0;
      border-top: 1px solid #f0f2f8;
    }

    .service-row:first-of-type {
      border-top: none;
    }

    .service-row label {
      font-size: 0.78rem;
      color: #555;
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
      background: #fafbfe;
      padding: 14px;
    }

    .agreement-detail h4 {
      margin: 10px 0 8px;
      font-size: 0.85rem;
      color: #4d5875;
    }

    .approval-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 10px;
    }

    .approval-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .notes-input {
      flex: 1;
      min-width: 160px;
    }

    /* ---------- General ---------- */

    .error-box {
      padding: 12px;
      background: #f6d9d5;
      color: #a6432f;
      border-radius: 7px;
      margin-bottom: 15px;
    }

    .next-stage {
      background: #eef3ff;
      border-color: #bcccf2;
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .active-card {
      background: #e7f6ec;
      border-color: #b9dfc7;
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }

    .completion-icon {
      width: 34px;
      height: 34px;
      flex: 0 0 34px;
      border-radius: 50%;
      background: #1f6f5c;
      color: white;
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

  recordAgreementSignature(agreement: AgentAgreement): void {
    if (!this.signatoryName.trim() || !this.signatureEvidencePath.trim()) {
      this.error.set(
        'Signatory name and signature evidence are required.'
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
}