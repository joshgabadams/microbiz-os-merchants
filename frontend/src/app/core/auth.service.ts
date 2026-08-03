import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthUser, LoginResponse } from './models/api.models';

const TOKEN_KEY = 'microbiz_token';
const USER_KEY = 'microbiz_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly _user = signal<AuthUser | null>(this.loadStoredUser());
  private readonly _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));

  readonly user = this._user.asReadonly();
  readonly isAuthenticated = computed(() => this._token() !== null);

  constructor(private http: HttpClient) {}

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${environment.apiUrl}/v1/login`, { email, password })
      .pipe(
        tap((response) => {
          if (response.success) {
            this.setSession(response.data.token, response.data.user);
          }
        })
      );
  }

  logout(): Observable<unknown> {
    return this.http.post(`${environment.apiUrl}/v1/logout`, {}).pipe(
      tap(() => this.clearSession())
    );
  }

  /** Clears local session immediately, without waiting on the API call --
   *  useful if the token is already invalid/expired server-side. */
  clearSessionLocally(): void {
    this.clearSession();
  }

  getToken(): string | null {
    return this._token();
  }

  private setSession(token: string, user: AuthUser): void {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    this._token.set(token);
    this._user.set(user);
  }

  private clearSession(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this._token.set(null);
    this._user.set(null);
  }

  private loadStoredUser(): AuthUser | null {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  }
}
