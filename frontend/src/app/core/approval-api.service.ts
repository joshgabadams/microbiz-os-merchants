import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, ApprovalRequestModel } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class ApprovalApiService {
  private readonly base = `${environment.apiUrl}/v1`;

  constructor(private http: HttpClient) {}

  pending(): Observable<ApiResponse<ApprovalRequestModel[]>> {
    return this.http.get<ApiResponse<ApprovalRequestModel[]>>(`${this.base}/approvals/pending`);
  }

  approve(id: number, checkerNote?: string): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/approvals/${id}/approve`,
      { checker_note: checkerNote }
    );
  }

  reject(id: number, checkerNote?: string): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/approvals/${id}/reject`,
      { checker_note: checkerNote }
    );
  }

  requestAllocateFloat(payload: {
    vault_id: number;
    teller_id: number;
    amount: number;
    reference?: string;
    narration?: string;
    maker_note?: string;
  }): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/float/allocate/request`,
      payload
    );
  }

  requestReturnFloat(payload: {
    vault_id: number;
    teller_id: number;
    amount: number;
    reference?: string;
    narration?: string;
    maker_note?: string;
  }): Observable<ApiResponse<ApprovalRequestModel>> {
    return this.http.post<ApiResponse<ApprovalRequestModel>>(
      `${this.base}/float/return/request`,
      payload
    );
  }
}
