import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface TessaAlert {
  id: number;
  alert_type: 'TELLER_VARIANCE' | 'HIGH_REVERSAL' | 'UNUSUAL_APPROVAL';
  severity: 'LOW' | 'MEDIUM' | 'HIGH';
  subject_type: string;
  subject_id: number;
  title: string;
  description: string;
  metadata: Record<string, unknown>;
  status: 'OPEN' | 'ACKNOWLEDGED' | 'RESOLVED';
  acknowledged_by: number | null;
  acknowledged_at: string | null;
  resolved_by: number | null;
  resolved_at: string | null;
  resolution_note: string | null;
  detected_at: string;
}

export interface TessaSummary {
  total_open: number;
  total_acknowledged: number;
  resolved_last_7_days: number;
  open_by_severity: Record<string, number>;
  open_by_type: Record<string, number>;
}

@Injectable({ providedIn: 'root' })
export class TessaApiService {
  private readonly base = `${environment.apiUrl}/v1/tessa`;

  constructor(private http: HttpClient) {}

  summary(): Observable<ApiResponse<TessaSummary>> {
    return this.http.get<ApiResponse<TessaSummary>>(`${this.base}/summary`);
  }

  list(params?: { status?: string; alert_type?: string; severity?: string }): Observable<ApiResponse<TessaAlert[]>> {
    const query: Record<string, string> = {};
    if (params?.status) query['status'] = params.status;
    if (params?.alert_type) query['alert_type'] = params.alert_type;
    if (params?.severity) query['severity'] = params.severity;

    return this.http.get<ApiResponse<TessaAlert[]>>(`${this.base}/alerts`, { params: query });
  }

  acknowledge(id: number): Observable<ApiResponse<TessaAlert>> {
    return this.http.post<ApiResponse<TessaAlert>>(`${this.base}/alerts/${id}/acknowledge`, {});
  }

  resolve(id: number, resolutionNote: string): Observable<ApiResponse<TessaAlert>> {
    return this.http.post<ApiResponse<TessaAlert>>(`${this.base}/alerts/${id}/resolve`, { resolution_note: resolutionNote });
  }

  detect(): Observable<ApiResponse<{ teller_variance: number; high_reversal: number; unusual_approval: number }>> {
    return this.http.post<ApiResponse<{ teller_variance: number; high_reversal: number; unusual_approval: number }>>(`${this.base}/detect`, {});
  }
}
