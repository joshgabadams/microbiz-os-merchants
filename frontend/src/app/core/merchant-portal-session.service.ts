import { Injectable, computed, signal } from '@angular/core';
import {
  MerchantAccountPreview,
  MerchantOtpChallenge,
  MerchantPortalSession,
  PortalLoginDraft,
  PortalRole,
} from './models/merchant-portal.models';

const SESSION_KEY = 'microbiz_merchant_session';
const LOGIN_DRAFT_KEY = 'microbiz_portal_login_draft';
const ACCOUNT_PREVIEW_KEY = 'microbiz_merchant_account_preview';
const OTP_CHALLENGE_KEY = 'microbiz_merchant_otp_challenge';

@Injectable({ providedIn: 'root' })
export class MerchantPortalSessionService {
  private readonly sessionState = signal<MerchantPortalSession | null>(this.readSession());
  private readonly loginDraftState = signal<PortalLoginDraft | null>(this.readLoginDraft());
  private readonly accountPreviewState = signal<MerchantAccountPreview | null>(
    this.parseStorage<MerchantAccountPreview>(sessionStorage.getItem(ACCOUNT_PREVIEW_KEY))
  );
  private readonly otpChallengeState = signal<MerchantOtpChallenge | null>(
    this.parseStorage<MerchantOtpChallenge>(sessionStorage.getItem(OTP_CHALLENGE_KEY))
  );

  readonly session = this.sessionState.asReadonly();
  readonly loginDraft = this.loginDraftState.asReadonly();
  readonly accountPreview = this.accountPreviewState.asReadonly();
  readonly otpChallenge = this.otpChallengeState.asReadonly();
  readonly isAuthenticated = computed(() => {
    const session = this.sessionState();
    return !!session && new Date(session.expiresAt).getTime() > Date.now();
  });

  setLoginDraft(role: PortalRole, email: string): void {
    const draft = { role, email };
    sessionStorage.setItem(LOGIN_DRAFT_KEY, JSON.stringify(draft));
    this.loginDraftState.set(draft);
  }

  setAccountPreview(preview: MerchantAccountPreview): void {
    sessionStorage.setItem(ACCOUNT_PREVIEW_KEY, JSON.stringify(preview));
    this.accountPreviewState.set(preview);
  }

  setOtpChallenge(challenge: MerchantOtpChallenge): void {
    sessionStorage.setItem(OTP_CHALLENGE_KEY, JSON.stringify(challenge));
    this.otpChallengeState.set(challenge);
  }

  startSession(session: MerchantPortalSession): void {
    localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    sessionStorage.removeItem(LOGIN_DRAFT_KEY);
    sessionStorage.removeItem(ACCOUNT_PREVIEW_KEY);
    sessionStorage.removeItem(OTP_CHALLENGE_KEY);
    this.loginDraftState.set(null);
    this.accountPreviewState.set(null);
    this.otpChallengeState.set(null);
    this.sessionState.set(session);
  }

  clear(): void {
    localStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(LOGIN_DRAFT_KEY);
    sessionStorage.removeItem(ACCOUNT_PREVIEW_KEY);
    sessionStorage.removeItem(OTP_CHALLENGE_KEY);
    this.sessionState.set(null);
    this.loginDraftState.set(null);
    this.accountPreviewState.set(null);
    this.otpChallengeState.set(null);
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
