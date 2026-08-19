import { Injectable, computed, signal } from '@angular/core';
import { MerchantPortalSession, PortalLoginDraft, PortalRole } from './models/merchant-portal.models';

const SESSION_KEY = 'microbiz_merchant_session';
const LOGIN_DRAFT_KEY = 'microbiz_portal_login_draft';

@Injectable({ providedIn: 'root' })
export class MerchantPortalSessionService {
  private readonly sessionState = signal<MerchantPortalSession | null>(this.readSession());
  private readonly loginDraftState = signal<PortalLoginDraft | null>(this.readLoginDraft());

  readonly session = this.sessionState.asReadonly();
  readonly loginDraft = this.loginDraftState.asReadonly();
  readonly isAuthenticated = computed(() => {
    const session = this.sessionState();
    return !!session && new Date(session.expiresAt).getTime() > Date.now();
  });

  setLoginDraft(role: PortalRole, email: string): void {
    const draft = { role, email };
    sessionStorage.setItem(LOGIN_DRAFT_KEY, JSON.stringify(draft));
    this.loginDraftState.set(draft);
  }

  startSession(session: MerchantPortalSession): void {
    localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    sessionStorage.removeItem(LOGIN_DRAFT_KEY);
    this.loginDraftState.set(null);
    this.sessionState.set(session);
  }

  clear(): void {
    localStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(LOGIN_DRAFT_KEY);
    this.sessionState.set(null);
    this.loginDraftState.set(null);
  }

  getAccessToken(): string | null {
    return this.isAuthenticated() ? this.sessionState()?.accessToken ?? null : null;
  }

  private readSession(): MerchantPortalSession | null {
    return this.parseStorage<MerchantPortalSession>(localStorage.getItem(SESSION_KEY));
  }

  private readLoginDraft(): PortalLoginDraft | null {
    return this.parseStorage<PortalLoginDraft>(sessionStorage.getItem(LOGIN_DRAFT_KEY));
  }

  private parseStorage<T>(raw: string | null): T | null {
    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw) as T;
    } catch {
      return null;
    }
  }
}

