import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  AgentComplaint,
  PaginatedResponse,
} from './models/api.models';

@Injectable({ providedIn: 'root' })
export class AgentComplaintApiService {
  private readonly base = `${environment.apiUrl}/v1/agent-complaints`;

  constructor(private http: HttpClient) {}

  list(filters: {
    status?: string;
    category?: string;
    priority?: string;
    agent_id?: number;
    overdue?: boolean;
  } = {}): Observable<PaginatedResponse<AgentComplaint>> {
    let params = new HttpParams();

    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params = params.set(key, String(value));
      }
    }

    return this.http.get<PaginatedResponse<AgentComplaint>>(this.base, {
      params,
    });
  }

  show(id: number): Observable<AgentComplaint> {
    return this.http.get<AgentComplaint>(`${this.base}/${id}`);
  }

  create(payload: {
    agent_id?: number;
    branch_id?: number;
    agent_location_id?: number;
    agent_transaction_id?: number;
    complainant_name: string;
    complainant_phone?: string;
    complainant_email?: string;
    channel: string;
    category: string;
    subject: string;
    description: string;
    disputed_amount?: number;
    priority?: string;
  }): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(this.base, payload);
  }

  acknowledge(id: number): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(
      `${this.base}/${id}/acknowledge`,
      {}
    );
  }

  assign(id: number, assignedTo: number): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(`${this.base}/${id}/assign`, {
      assigned_to: assignedTo,
    });
  }

  startProgress(id: number): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(
      `${this.base}/${id}/start-progress`,
      {}
    );
  }

  escalate(id: number, reason: string): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(`${this.base}/${id}/escalate`, {
      reason,
    });
  }

  resolve(id: number, resolutionSummary: string): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(`${this.base}/${id}/resolve`, {
      resolution_summary: resolutionSummary,
    });
  }

  close(id: number): Observable<AgentComplaint> {
    return this.http.post<AgentComplaint>(`${this.base}/${id}/close`, {});
  }
}
