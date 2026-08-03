import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

export interface CallOverTransaction {
  id: number;
  transaction_no: string;
  transaction_type: string;
  amount: string;
  currency: string;
  reference: string | null;
  narration: string | null;
  transaction_date: string;
  posted: boolean;
  performer?: { id: number; name: string };
  approver?: { id: number; name: string } | null;
}

export interface CallOverReport {
  teller_id?: number;
  vault_id?: number;
  from_date: string;
  to_date: string;
  count: number;
  total_debits: string;
  total_credits: string;
  transactions: CallOverTransaction[];
}

export interface LedgerEntry {
  id: number;
  reference_no: string;
  transaction_type: string;
  narration: string | null;
  debit: string;
  credit: string;
  running_balance: string;
  status: string;
  transaction_date: string;
}

export interface LedgerStatement {
  teller_id?: number;
  vault_id?: number;
  from_date: string;
  to_date: string;
  opening_balance: string;
  closing_balance: string;
  count: number;
  entries: LedgerEntry[];
}

@Injectable({ providedIn: 'root' })
export class ReportApiService {
  private readonly base = `${environment.apiUrl}/v1/reports`;

  constructor(private http: HttpClient) {}

  tellerTransactions(params: {
    teller_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<CallOverReport>> {
    return this.http.get<ApiResponse<CallOverReport>>(`${this.base}/teller-transactions`, {
      params: params as unknown as Record<string, string | number>,
    });
  }

  vaultTransactions(params: {
    vault_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<CallOverReport>> {
    return this.http.get<ApiResponse<CallOverReport>>(`${this.base}/vault-transactions`, {
      params: params as unknown as Record<string, string | number>,
    });
  }

  tellerLedger(params: {
    teller_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<LedgerStatement>> {
    return this.http.get<ApiResponse<LedgerStatement>>(`${this.base}/teller-ledger`, {
      params: params as unknown as Record<string, string | number>,
    });
  }

  vaultLedger(params: {
    vault_id: number;
    from_date: string;
    to_date: string;
  }): Observable<ApiResponse<LedgerStatement>> {
    return this.http.get<ApiResponse<LedgerStatement>>(`${this.base}/vault-ledger`, {
      params: params as unknown as Record<string, string | number>,
    });
  }
}
