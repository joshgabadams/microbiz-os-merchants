import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class BranchEodApiService {
  private readonly base = `${environment.apiUrl}/v1/branch`;

  constructor(private http: HttpClient) {}

  close(payload: {
    branch_id: number;
    business_date: string;
    closed_by: number;
    note?: string;
  }): Observable<ApiResponse<unknown>> {
    return this.http.post<ApiResponse<unknown>>(`${this.base}/eod`, payload);
  }
}
