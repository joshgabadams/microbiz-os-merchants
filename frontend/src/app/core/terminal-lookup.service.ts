import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface TerminalLookupResult {
  terminalid: string | null;
  serialnumber: string | null;
  manufacturer: string;
  status: string;
}

export type TerminalLookupType = 'terminal-id' | 'serial-number';

@Injectable({ providedIn: 'root' })
export class TerminalLookupService {
  private readonly baseUrl = `${environment.apiUrl}/v1/terminals/lookup`;

  constructor(private http: HttpClient) {}

  lookup(lookupType: TerminalLookupType, identifier: string): Observable<TerminalLookupResult> {
    return this.http.get<TerminalLookupResult>(
      `${this.baseUrl}/${lookupType}/${encodeURIComponent(identifier)}`
    );
  }
}
