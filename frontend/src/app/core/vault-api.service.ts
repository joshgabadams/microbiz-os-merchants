import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Vault } from './models/api.models';

@Injectable({ providedIn: 'root' })
export class VaultApiService {
  private readonly base = `${environment.apiUrl}/vaults`;

  constructor(private http: HttpClient) {}

  list(): Observable<Vault[]> {
    return this.http.get<Vault[]>(this.base);
  }

  show(id: number): Observable<Vault> {
    return this.http.get<Vault>(`${this.base}/${id}`);
  }

  create(payload: {
    branch_id: number;
    gl_account_id: number;
    name: string;
    active?: boolean;
  }): Observable<Vault> {
    return this.http.post<Vault>(this.base, payload);
  }
}
