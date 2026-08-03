import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class BalancingApiService {
  private readonly base = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  tellerBalance(payload: {
    teller_id: number;
    business_date: string;
    physical_cash: number;
    balanced_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/teller/balance`, payload);
  }

  vaultBalance(payload: {
    vault_id: number;
    business_date: string;
    physical_cash: number;
    balanced_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/vault/balance`, payload);
  }
}
