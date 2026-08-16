import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, tap } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse, AuthUser } from './models/api.models';

const CREDENTIAL_KEY = 'microbiz_credential';
const USER_KEY = 'microbiz_user';

/**
 * HTTP Basic Auth, stateless -- matches the backend's AuthenticateBasicOnce
 * middleware. There is no server-side token to issue or revoke: "login" is
 * verifying email/password once against GET /v1/me and remembering the
 * base64 credential to resend on every subsequent request (see
 * auth.interceptor.ts); "logout" is just discarding it locally.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly _user = signal<AuthUser | null>(this.loadStoredUser());
  private readonly _credential = signal<string | null>(
    localStorage.getItem(CREDENTIAL_KEY)
  );

  readonly user = this._user.asReadonly();
  readonly isAuthenticated = computed(() => this._credential() !== null);

  constructor(private http: HttpClient) {}

  login(email: string, password: string): Observable<ApiResponse<AuthUser>> {
    const credential = btoa(`${email}:${password}`);

    return this.http
      .get<ApiResponse<AuthUser>>(`${environment.apiUrl}/v1/me`, {
        headers: { Authorization: `Basic ${credential}` },
      })
      .pipe(
        tap((response) => {
          if (response.success) {
            this.setSession(credential, response.data);
          }
        })
      );
  }

  logout(): Observable<void> {
    this.clearSession();

    return of(undefined);
  }

  /** Clears local session immediately, without waiting on the API call --
   *  useful if the credential is already invalid/expired server-side. */
  clearSessionLocally(): void {
    this.clearSession();
  }

  getCredential(): string | null {
    return this._credential();
  }

  private setSession(credential: string, user: AuthUser): void {
    localStorage.setItem(CREDENTIAL_KEY, credential);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this._credential.set(credential);
    this._user.set(user);
  }

  private clearSession(): void {
    localStorage.removeItem(CREDENTIAL_KEY);
    localStorage.removeItem(USER_KEY);
    this._credential.set(null);
    this._user.set(null);
  }

  private loadStoredUser(): AuthUser | null {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  }
}
