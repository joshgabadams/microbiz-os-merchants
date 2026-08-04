import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface FixedDeposit {
  id: number;
  fd_no: string;
  customer_account_id: number;
  settlement_account_id: number;
  principal_amount: string;
  currency: string;
  interest_rate: string;
  pre_liquidation_rate: string;
  pre_liquidation_penalty_fee: string;
  tenor_days: number;
  start_date: string;
  maturity_date: string;
  status: 'ACTIVE' | 'LIQUIDATED_EARLY' | 'LIQUIDATED_AT_MATURITY';
  interest_paid: string | null;
  narration: string | null;
}

export interface LiquidationCalculation {
  is_early: boolean;
  elapsed_days: number;
  rate_applied: number;
  principal: number;
  gross_interest: number;
  fee_applied: number;
  net_interest: number;
  total_payout: number;
}

@Injectable({ providedIn: 'root' })
export class FixedDepositApiService {
  private readonly base = `${environment.apiUrl}/v1/fixed-deposits`;

  constructor(private http: HttpClient) {}

  list(): Observable<ApiResponse<FixedDeposit[]>> {
    return this.http.get<ApiResponse<FixedDeposit[]>>(this.base);
  }

  book(payload: {
    customer_account_id: number;
    settlement_account_id: number;
    principal_amount: number;
    interest_rate: number;
    pre_liquidation_rate: number;
    pre_liquidation_penalty_fee?: number;
    tenor_days: number;
    narration?: string;
  }): Observable<ApiResponse<FixedDeposit>> {
    return this.http.post<ApiResponse<FixedDeposit>>(`${this.base}/book`, payload);
  }

  previewLiquidation(id: number): Observable<ApiResponse<LiquidationCalculation>> {
    return this.http.get<ApiResponse<LiquidationCalculation>>(`${this.base}/${id}/preview-liquidation`);
  }

  liquidate(id: number, narration?: string): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/${id}/liquidate`, { narration });
  }
}
