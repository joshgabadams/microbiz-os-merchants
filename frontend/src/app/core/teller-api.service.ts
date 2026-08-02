import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, TellerModel } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class TellerApiService {
  private readonly base = `${environment.apiUrl}/tellers`;
  private readonly v1 = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<TellerModel[]>> {
    return this.http.get<ApiResponse<TellerModel[]>>(this.base);
  }

  create(payload: {
    branch_id: number;
    vault_id: number;
    gl_account_id: number;
    teller_code: string;
    display_name: string;
    daily_limit: number;
    opening_cash_limit: number;
    minimum_cash: number;
    maximum_cash: number;
    active: boolean;
    status: string;
  }): Observable<ApiResponse<TellerModel>> {
    return this.http.post<ApiResponse<TellerModel>>(this.base, payload);
  }

  open(payload: {
    teller_id: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.v1}/teller/open`, payload);
  }

  close(payload: {
    teller_id: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.v1}/teller/close`, payload);
  }
}
