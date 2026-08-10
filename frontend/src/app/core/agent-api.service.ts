import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  Agent,
  AgentAgreement,
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
      effective_date?: string;
      expiry_date?: string;
      renewal_due_date?: string;
      permitted_services?: string[];
      commercial_terms?: Record<string, unknown>;
      document_path?: string;
    }
  ): Observable<ApiResponse<AgentAgreement>> {
    return this.http.post<ApiResponse<AgentAgreement>>(
      `${this.base}/${agentId}/agreements`,
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
}
