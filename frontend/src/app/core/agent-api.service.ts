import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Agent, AgentType, ApiResponse } from './models/api.models';

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
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/submit`, {});
  }

  approve(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/approve`, {});
  }

  reject(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/reject`, { reason });
  }

  activate(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/activate`, {});
  }

  restrict(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/restrict`, { reason });
  }

  suspend(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/suspend`, { reason });
  }

  reactivate(id: number): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/reactivate`, {});
  }

  terminate(id: number, reason: string): Observable<ApiResponse<Agent>> {
    return this.http.post<ApiResponse<Agent>>(`${this.base}/${id}/terminate`, { reason });
  }
}
