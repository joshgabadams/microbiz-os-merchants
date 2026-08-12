import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  Agent,
  AgentAgreement,
  AgentAgreementApprovalType,
  AgentAgreementCommercialTerm,
  AgentAgreementPermittedService,
  AgentAgreementSignatoryParty,
  AgentAgreementTemplate,
  AgentBeneficialOwner,
  AgentDocument,
  AgentLocation,
  AgentType,
  ApiResponse,
} from './models/api.models';

@Injectable({ providedIn: 'root' })
export class AgentApiService {
  private readonly base = `${environment.apiUrl}/v1/agents`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<Agent[]>> {
    return this.http.get<ApiResponse<Agent[]>>(this.base);
  }

  show(id: number): Observable<ApiResponse<Agent>> {
    return this.http.get<ApiResponse<Agent>>(`${this.base}/${id}`);
  }

  register(payload: {
    agent_type: AgentType;
    legal_name: string;
    phone: string;
    branch_id: number;
    trading_name?: string;
    email?: string;
  }): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(this.base, payload);
  }

  submit(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/submit`,
      {}
    );
  }

  approve(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/approve`,
      {}
    );
  }

  reject(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/reject`,
      { reason }
    );
  }

  activate(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/activate`,
      {}
    );
  }

  restrict(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/restrict`,
      { reason }
    );
  }

  suspend(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/suspend`,
      { reason }
    );
  }

  reactivate(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/reactivate`,
      {}
    );
  }

  terminate(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${id}/terminate`,
      { reason }
    );
  }

  // ---------- AG-03: Locations ----------

  listLocations(
    agentId: number
  ): Observable<ApiResponse<AgentLocation[]>> {
    return this.http.get<ApiResponse<AgentLocation[]>>(
      `${this.base}/${agentId}/locations`
    );
  }

  createLocation(
    agentId: number,
    payload: {
      address_line_1: string;
      address_line_2?: string;
      landmark?: string;
      city: string;
      local_government: string;
      state: string;
      latitude: number;
      longitude: number;
      approved_radius_metres: number;
    }
  ): Observable<ApiResponse<AgentLocation>> {
    return this.http.post<ApiResponse<AgentLocation>>(
      `${this.base}/${agentId}/locations`,
      payload
    );
  }

  verifyLocation(
    agentId: number,
    locationId: number,
    notes?: string
  ): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${agentId}/locations/${locationId}/verify`,
      notes ? { notes } : {}
    );
  }

  rejectLocation(
    agentId: number,
    locationId: number,
    reason: string
  ): Observable<ApiResponse<AgentLocation>> {
    return this.http.post<ApiResponse<AgentLocation>>(
      `${this.base}/${agentId}/locations/${locationId}/reject`,
      { reason }
    );
  }

  // ---------- AG-03: Compliance ----------

  completeComplianceReview(
    agentId: number
  ): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${agentId}/complete-compliance-review`,
      {}
    );
  }

  // ---------- AG-03: Agreements ----------

  listAgreements(
    agentId: number
  ): Observable<ApiResponse<AgentAgreement[]>> {
    return this.http.get<ApiResponse<AgentAgreement[]>>(
      `${this.base}/${agentId}/agreements`
    );
  }

  createAgreement(
    agentId: number,
    payload: {
      agreement_template_id: number;
      effective_date?: string;
      expiry_date?: string;
      renewal_due_date?: string;
      initial_term_months?: number;
      agent_termination_notice_days?: number;
      microbiz_termination_notice_days?: number;
      dispute_resolution_method?: string;
      arbitration_seat?: string;
      special_conditions?: string;
      permitted_services?: AgentAgreementPermittedService[];
      commercial_terms?: AgentAgreementCommercialTerm[];
      document_path?: string;
    }
  ): Observable<ApiResponse<AgentAgreement>> {
    return this.http.post<ApiResponse<AgentAgreement>>(
      `${this.base}/${agentId}/agreements`,
      payload
    );
  }

  submitAgreementForReview(
    agentId: number,
    agreementId: number
  ): Observable<ApiResponse<AgentAgreement>> {
    return this.http.post<ApiResponse<AgentAgreement>>(
      `${this.base}/${agentId}/agreements/${agreementId}/submit-review`,
      {}
    );
  }

  private readonly approvalRoutes: Record<AgentAgreementApprovalType, string> = {
    RISK: 'risk',
    COMPLIANCE: 'compliance',
    LEGAL: 'legal',
    BUSINESS_OWNER: 'business-owner',
  };

  recordAgreementApproval(
    agentId: number,
    agreementId: number,
    approvalType: AgentAgreementApprovalType,
    decision: 'APPROVED' | 'REJECTED',
    notes?: string
  ): Observable<ApiResponse<AgentAgreement>> {
    const segment = this.approvalRoutes[approvalType];

    return this.http.post<ApiResponse<AgentAgreement>>(
      `${this.base}/${agentId}/agreements/${agreementId}/approve/${segment}`,
      { decision, notes }
    );
  }

  sendAgreementForSignature(
    agentId: number,
    agreementId: number
  ): Observable<ApiResponse<AgentAgreement>> {
    return this.http.post<ApiResponse<AgentAgreement>>(
      `${this.base}/${agentId}/agreements/${agreementId}/send-for-signature`,
      {}
    );
  }

  recordAgreementSignature(
    agentId: number,
    agreementId: number,
    payload: {
      party: AgentAgreementSignatoryParty;
      signatory_name: string;
      signatory_title?: string;
      signature_method: 'WET_SIGNATURE_UPLOAD' | 'E_SIGNATURE';
      signature_evidence_path: string;
      provider_reference_id?: string;
    }
  ): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(
      `${this.base}/${agentId}/agreements/${agreementId}/signatures`,
      payload
    );
  }

  executeAgreement(
    agentId: number,
    agreementId: number
  ): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(
      `${this.base}/${agentId}/agreements/${agreementId}/execute`,
      {}
    );
  }

  // ---------- Agreement Templates ----------

  listAgreementTemplates(): Observable<ApiResponse<AgentAgreementTemplate[]>> {
    return this.http.get<ApiResponse<AgentAgreementTemplate[]>>(
      `${environment.apiUrl}/v1/agent-agreement-templates`
    );
  }
// ---------- AG-02: KYC ----------

listOwners(
  agentId: number
): Observable<ApiResponse<AgentBeneficialOwner[]>> {
  return this.http.get<ApiResponse<AgentBeneficialOwner[]>>(
    `${this.base}/${agentId}/owners`
  );
}

addOwner(
  agentId: number,
  payload: {
    full_name: string;
    date_of_birth?: string;
    nationality?: string;
    identification_type?: string;
    identification_number?: string;
    ownership_percentage?: number;
    is_director?: boolean;
    is_pep?: boolean;
    sanctions_match?: boolean;
  }
): Observable<ApiResponse<AgentBeneficialOwner>> {
  return this.http.post<ApiResponse<AgentBeneficialOwner>>(
    `${this.base}/${agentId}/owners`,
    payload
  );
}

listDocuments(
  agentId: number
): Observable<ApiResponse<AgentDocument[]>> {
  return this.http.get<ApiResponse<AgentDocument[]>>(
    `${this.base}/${agentId}/documents`
  );
}

addDocument(
  agentId: number,
  payload: {
    document_type: string;
    document_number?: string;
    storage_path?: string;
    issued_at?: string;
    expires_at?: string;
  }
): Observable<ApiResponse<AgentDocument>> {
  return this.http.post<ApiResponse<AgentDocument>>(
    `${this.base}/${agentId}/documents`,
    payload
  );
}

completeKyc(
  agentId: number
): Observable<ApiResponse<Agent>> {
  return this.http.post<ApiResponse<Agent>>(
    `${this.base}/${agentId}/complete-kyc`,
    {}
  );
}



}
