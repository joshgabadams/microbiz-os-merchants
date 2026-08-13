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
  AgentOperator,
  AgentTerminal,
  AgentTransaction,
  AgentTrainingRecord,
  AgentType,
  ApiResponse,
  TrainingDocument,
} from './models/api.models';

@Injectable({ providedIn: 'root' })
export class AgentApiService {
  private readonly base = `${environment.apiUrl}/v1/agents`;
  private readonly trainingDocumentsBase = `${environment.apiUrl}/v1/training-documents`;
  private readonly agentOperatorsBase = `${environment.apiUrl}/v1/agent-operators`;
  private readonly agentTerminalsBase = `${environment.apiUrl}/v1/agent-terminals`;

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

  uploadAgreementSignatureEvidence(
    agentId: number,
    agreementId: number,
    file: File
  ): Observable<ApiResponse<{ path: string }>> {
    const formData = new FormData();
    formData.append('evidence', file);

    return this.http.post<ApiResponse<{ path: string }>>(
      `${this.base}/${agentId}/agreements/${agreementId}/signature-evidence`,
      formData
    );
  }

  listTrainingDocuments(): Observable<ApiResponse<TrainingDocument[]>> {
    return this.http.get<ApiResponse<TrainingDocument[]>>(
      this.trainingDocumentsBase
    );
  }

  createTrainingDocument(
    name: string,
    version: string,
    file: File
  ): Observable<ApiResponse<TrainingDocument>> {
    const formData = new FormData();
    formData.append('name', name);
    formData.append('version', version);
    formData.append('file', file);

    return this.http.post<ApiResponse<TrainingDocument>>(
      this.trainingDocumentsBase,
      formData
    );
  }

  recordTrainingDownload(
    agentId: number,
    trainingDocumentId: number
  ): Observable<ApiResponse<AgentTrainingRecord>> {
    return this.http.post<ApiResponse<AgentTrainingRecord>>(
      `${this.base}/${agentId}/training/download`,
      { training_document_id: trainingDocumentId }
    );
  }

  acknowledgeTraining(
    agentId: number,
    trainingRecordId: number
  ): Observable<ApiResponse<AgentTrainingRecord>> {
    return this.http.post<ApiResponse<AgentTrainingRecord>>(
      `${this.base}/${agentId}/training/${trainingRecordId}/acknowledge`,
      {}
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

// ---------- AG-04: Operators ----------

listOperators(
  agentId: number
): Observable<ApiResponse<AgentOperator[]>> {
  return this.http.get<ApiResponse<AgentOperator[]>>(
    `${this.base}/${agentId}/operators`
  );
}

createOperator(
  agentId: number,
  payload: {
    agent_location_id: number;
    user_id: number;
    role: string;
  }
): Observable<ApiResponse<AgentOperator>> {
  return this.http.post<ApiResponse<AgentOperator>>(
    `${this.base}/${agentId}/operators`,
    payload
  );
}

activateOperator(
  operatorId: number
): Observable<ApiResponse<AgentOperator>> {
  return this.http.post<ApiResponse<AgentOperator>>(
    `${this.agentOperatorsBase}/${operatorId}/activate`,
    {}
  );
}

suspendOperator(
  operatorId: number
): Observable<ApiResponse<AgentOperator>> {
  return this.http.post<ApiResponse<AgentOperator>>(
    `${this.agentOperatorsBase}/${operatorId}/suspend`,
    {}
  );
}

// ---------- AG-04/05: Terminals & Geo-Fence ----------

listTerminals(
  agentId: number
): Observable<ApiResponse<AgentTerminal[]>> {
  return this.http.get<ApiResponse<AgentTerminal[]>>(
    `${this.base}/${agentId}/terminals`
  );
}

createTerminal(payload: {
  agent_id: number;
  agent_location_id: number;
  terminal_id: string;
  serial_number: string;
  device_model?: string;
  provider?: string;
  application_version?: string;
  registered_latitude: number;
  registered_longitude: number;
  geo_fence_radius_metres?: number;
}): Observable<ApiResponse<AgentTerminal>> {
  return this.http.post<ApiResponse<AgentTerminal>>(
    this.agentTerminalsBase,
    payload
  );
}

activateTerminal(
  terminalId: number
): Observable<ApiResponse<AgentTerminal>> {
  return this.http.post<ApiResponse<AgentTerminal>>(
    `${this.agentTerminalsBase}/${terminalId}/activate`,
    {}
  );
}

suspendTerminal(
  terminalId: number
): Observable<ApiResponse<AgentTerminal>> {
  return this.http.post<ApiResponse<AgentTerminal>>(
    `${this.agentTerminalsBase}/${terminalId}/suspend`,
    {}
  );
}

// ---------- AG-07/08/09: Operational Transactions ----------

listTransactions(
  agentId: number
): Observable<ApiResponse<AgentTransaction[]>> {
  return this.http.get<ApiResponse<AgentTransaction[]>>(
    `${this.base}/${agentId}/transactions`
  );
}

cashIn(
  agentId: number,
  payload: {
    operator_id: number;
    terminal_id: number;
    customer_account_id: number;
    amount: number;
    idempotency_key: string;
    latitude: number;
    longitude: number;
    customer_reference?: string;
    narration?: string;
  }
): Observable<ApiResponse<AgentTransaction>> {
  return this.http.post<ApiResponse<AgentTransaction>>(
    `${this.base}/${agentId}/transactions/cash-in`,
    payload
  );
}

cashOut(
  agentId: number,
  payload: {
    operator_id: number;
    terminal_id: number;
    customer_account_id: number;
    amount: number;
    idempotency_key: string;
    latitude: number;
    longitude: number;
    customer_authenticated: boolean;
    customer_reference?: string;
    narration?: string;
  }
): Observable<ApiResponse<AgentTransaction>> {
  return this.http.post<ApiResponse<AgentTransaction>>(
    `${this.base}/${agentId}/transactions/cash-out`,
    payload
  );
}

transfer(
  agentId: number,
  payload: {
    operator_id: number;
    terminal_id: number;
    from_account_id: number;
    to_account_id: number;
    amount: number;
    idempotency_key: string;
    latitude: number;
    longitude: number;
    narration?: string;
  }
): Observable<ApiResponse<AgentTransaction>> {
  return this.http.post<ApiResponse<AgentTransaction>>(
    `${this.base}/${agentId}/transactions/transfer`,
    payload
  );
}


}
