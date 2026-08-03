import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, Wallet } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class WalletApiService {
  private readonly base = `${environment.apiUrl}/v1/wallets`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<Wallet[]>> {
    return this.http.get<ApiResponse<Wallet[]>>(this.base);
  }

  show(id: number): Observable<ApiResponse<Wallet>> {
    return this.http.get<ApiResponse<Wallet>>(`${this.base}/${id}`);
  }

  onboard(payload: {
    owner_name: string;
    phone: string;
    bvn?: string;
    nin?: string;
    kyc_tier?: number;
  }): Observable<ApiResponse<Wallet>> {
    return this.http.post<ApiResponse<Wallet>>(`${this.base}/onboard`, payload);
  }
}
