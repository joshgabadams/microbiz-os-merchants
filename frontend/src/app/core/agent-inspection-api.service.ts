import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  AgentInspection,
  PaginatedResponse,
} from './models/api.models';

@Injectable({ providedIn: 'root' })
export class AgentInspectionApiService {
  private readonly base = `${environment.apiUrl}/v1/agent-inspections`;

  constructor(private http: HttpClient) {}

  list(filters: {
    status?: string;
    compliance_outcome?: string;
    follow_up_status?: string;
    agent_id?: number;
    overdue?: boolean;
  } = {}): Observable<PaginatedResponse<AgentInspection>> {
    let params = new HttpParams();

    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params = params.set(key, String(value));
      }
    }

    return this.http.get<PaginatedResponse<AgentInspection>>(this.base, {
      params,
    });
  }

  show(id: number): Observable<AgentInspection> {
    return this.http.get<AgentInspection>(`${this.base}/${id}`);
  }

  create(payload: {
    agent_id: number;
    agent_location_id?: number;
    inspector_id: number;
    inspection_type: string;
    inspection_date: string;
  }): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(this.base, payload);
  }

  start(id: number): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(`${this.base}/${id}/start`, {});
  }

  complete(
    id: number,
    payload: {
      findings: string;
      compliance_outcome: string;
      corrective_action?: string;
      corrective_action_deadline?: string;
    }
  ): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(
      `${this.base}/${id}/complete`,
      payload
    );
  }

  cancel(id: number, reason: string): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(`${this.base}/${id}/cancel`, {
      reason,
    });
  }

  startFollowUp(id: number): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(
      `${this.base}/${id}/follow-up/start`,
      {}
    );
  }

  completeFollowUp(
    id: number,
    notes?: string
  ): Observable<AgentInspection> {
    return this.http.post<AgentInspection>(
      `${this.base}/${id}/follow-up/complete`,
      { notes }
    );
  }
}
