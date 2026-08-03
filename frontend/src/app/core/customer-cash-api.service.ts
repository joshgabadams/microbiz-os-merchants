import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class CustomerCashApiService {
  private readonly base = `${environment.apiUrl}/v1/customer`;

  constructor(private http: HttpClient) {}

  deposit(payload: {
    teller_id: number;
    customer_account_id: number;
    amount: number;
    performed_by: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/deposit`, payload);
  }

  withdraw(payload: {
    teller_id: number;
    customer_account_id: number;
    amount: number;
    performed_by: number;
    reference?: string;
    narration?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/withdraw`, payload);
  }
}
