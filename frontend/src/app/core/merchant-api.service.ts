import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, Merchant } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class MerchantApiService {
  private readonly base = `${environment.apiUrl}/v1/merchants`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<Merchant[]>> {
    return this.http.get<ApiResponse<Merchant[]>>(this.base);
  }

  show(id: number): Observable<ApiResponse<Merchant>> {
    return this.http.get<ApiResponse<Merchant>>(`${this.base}/${id}`);
  }

  onboard(payload: {
    business_name: string;
    contact_name?: string;
    phone?: string;
    email?: string;
    branch_id?: number;
  }): Observable<ApiResponse<Merchant>> {
    return this.http.post<ApiResponse<Merchant>>(`${this.base}/onboard`, payload);
  }

  submit(id: number): Observable<ApiResponse<Merchant>> {
    return this.http.post<ApiResponse<Merchant>>(`${this.base}/${id}/submit`, {});
  }

  approve(id: number): Observable<ApiResponse<Merchant>> {
    return this.http.post<ApiResponse<Merchant>>(`${this.base}/${id}/approve`, {});
  }

  reject(id: number, reason: string): Observable<ApiResponse<Merchant>> {
    return this.http.post<ApiResponse<Merchant>>(`${this.base}/${id}/reject`, { reason });
  }

  activate(id: number): Observable<ApiResponse<Merchant>> {
    return this.http.post<ApiResponse<Merchant>>(`${this.base}/${id}/activate`, {});
  }
}
