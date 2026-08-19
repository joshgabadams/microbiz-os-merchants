import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, delay, map, of, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';
import {
  ConfirmMerchantRegistrationPayload,
  MerchantAccountPreview,
  MerchantApiRecord,
  MerchantDashboardSummary,
  MerchantOtpChallenge,
  MerchantOtpVerification,
  MerchantPortalProfile,
  MerchantPortalSession,
  MerchantRegistrationConfirmation,
  MerchantSessionApiResponse,
  MerchantTransactionPage,
  MerchantTransactionQuery,
  PortalLoginDraft,
  PortalTransaction,
} from './models/merchant-portal.models';

/**
 * Mock-backed contract boundary for merchant self-service APIs that are not
 * exposed by Laravel yet. Components only depend on these typed methods, so
 * each implementation can be replaced with HttpClient calls as contracts land.
 */
@Injectable({ providedIn: 'root' })
export class MerchantPortalApiService {
  private readonly registrationBase = `${environment.apiUrl}/v1/reg/merchant`;
  private readonly merchantSelfBase = `${environment.apiUrl}/v1/merchant`;

  constructor(private readonly http: HttpClient) {}

  private profile: MerchantPortalProfile = {
    merchantId: 204891,
    merchantCode: 'MER-204891',
    businessName: 'Northstar Retail Limited',
    contactName: 'Joshua Adeyemi',
    email: 'merchant@microbiz.test',
    phone: '+234 803 555 0142',
    registrationNumber: 'RC 1948201',
    accountNumber: '2048910037',
    status: 'ACTIVE',
    settlementBank: 'MicroBiz MFB',
    settlementAccountName: 'Northstar Retail Limited',
    settlementAccountNumber: '2048910037',
  };

  private readonly transactions: PortalTransaction[] = [
    { id: 1, reference: 'MBZ-Q3F8K2', type: 'QR_PAYMENT', customer: 'Chinedu Okafor', amount: 48500, fee: 120, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-19T10:42:00Z' },
    { id: 2, reference: 'MBZ-P9D4L7', type: 'POS_PAYMENT', customer: 'Amina Bello', amount: 125000, fee: 250, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-19T09:18:00Z' },
    { id: 3, reference: 'MBZ-Q1A6V5', type: 'QR_PAYMENT', customer: 'Tunde Balogun', amount: 18750, fee: 50, currency: 'NGN', status: 'PENDING', createdAt: '2026-08-19T08:07:00Z' },
    { id: 4, reference: 'MBZ-S8N2C4', type: 'SETTLEMENT', customer: 'Northstar Retail', amount: 350000, fee: 0, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-18T16:31:00Z' },
    { id: 5, reference: 'MBZ-P7X9M1', type: 'POS_PAYMENT', customer: 'Ngozi Eze', amount: 76000, fee: 180, currency: 'NGN', status: 'FAILED', createdAt: '2026-08-18T14:05:00Z' },
    { id: 6, reference: 'MBZ-Q5T2B8', type: 'QR_PAYMENT', customer: 'Femi Lawal', amount: 32400, fee: 80, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-17T12:44:00Z' },
    { id: 7, reference: 'MBZ-P4R6J3', type: 'POS_PAYMENT', customer: 'Ifeoma Nwosu', amount: 91000, fee: 220, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-16T11:20:00Z' },
    { id: 8, reference: 'MBZ-Q8W1H6', type: 'QR_PAYMENT', customer: 'Sani Musa', amount: 15300, fee: 40, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-15T15:17:00Z' },
    { id: 9, reference: 'MBZ-S2E7P9', type: 'SETTLEMENT', customer: 'Northstar Retail', amount: 500000, fee: 0, currency: 'NGN', status: 'SUCCESSFUL', createdAt: '2026-08-14T16:00:00Z' },
  ];

  beginLogin(role: PortalLoginDraft['role'], email: string, password: string): Observable<PortalLoginDraft> {
    if (!email.trim() || password.length < 6) {
      return throwError(() => new Error('Enter a valid email and password.')).pipe(delay(450));
    }

    return of({ role, email: email.trim() }).pipe(delay(650));
  }

  previewAccount(account_number: string, draft: PortalLoginDraft): Observable<MerchantAccountPreview> {
    if (!/^\d{1,50}$/.test(account_number)) {
      return throwError(() => new Error('Enter a valid MicroBiz account number.'));
    }

    if (draft.role === 'agent') {
      return throwError(() => new Error('The agent workspace is not available in this build yet.'));
    }

    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.accountPreview) {
      return this.http
        .post<ApiResponse<MerchantAccountPreview>>(`${this.registrationBase}/preview`, {
          account_number,
        })
        .pipe(map((response) => response.data));
    }

    return of({
      fincore_client_id: 204891,
      account_no: account_number,
      external_id: 'MBZ-CUST-204891',
      display_name: this.profile.businessName,
      mobile_no: this.profile.phone,
      email: draft.email || this.profile.email,
      office_name: 'Victoria Island Branch',
      legal_form: 'ENTITY',
      status: 'Active',
      active: true,
    }).pipe(delay(700));
  }

  requestOtp(preview: MerchantAccountPreview): Observable<MerchantOtpChallenge> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.otp) {
      return this.http
        .post<ApiResponse<MerchantOtpChallenge>>(`${this.registrationBase}/otp/send`, {
          fincore_client_id: preview.fincore_client_id,
          account_number: preview.account_no,
        })
        .pipe(map((response) => response.data));
    }

    const digits = (preview.mobile_no ?? '').replace(/\D/g, '');
    const masked_phone = digits.length >= 4
      ? `******${digits.slice(-4)}`
      : 'the phone linked to your account';

    // TODO(API): replace with POST /reg/merchant/otp/send when exposed.
    return of({
      challenge_id: `mock-otp-${Date.now()}`,
      masked_phone,
      expires_at: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
    }).pipe(delay(550));
  }

  verifyOtp(challenge_id: string, otp: string): Observable<MerchantOtpVerification> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.otp) {
      return this.http
        .post<ApiResponse<MerchantOtpVerification>>(`${this.registrationBase}/otp/verify`, {
          challenge_id,
          otp,
        })
        .pipe(map((response) => response.data));
    }

    return otp === '0000'
      ? of({ verified: true, verification_token: `mock-verification-${Date.now()}` }).pipe(delay(600))
      : throwError(() => new Error('The verification code is incorrect. Use 0000 for this preview.'));
  }

  confirmRegistration(
    payload: ConfirmMerchantRegistrationPayload,
    preview: MerchantAccountPreview,
  ): Observable<MerchantRegistrationConfirmation> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.registration) {
      return this.http
        .post<ApiResponse<MerchantApiRecord>>(this.registrationBase, payload)
        .pipe(map((response) => this.mapRegistration(response.data, preview.account_no)));
    }

    return of({
      merchant_id: 15,
      merchant_code: 'MER-000015',
      business_name: payload.trading_name || payload.legal_name,
      account_number: preview.account_no,
      status: 'DRAFT',
    }).pipe(delay(900));
  }

  createMerchantSession(
    registration: MerchantRegistrationConfirmation,
    email: string,
    verification_token?: string,
  ): Observable<MerchantPortalSession> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantSession &&
      verification_token
    ) {
      return this.http
        .post<ApiResponse<MerchantSessionApiResponse>>(`${this.merchantSelfBase}/session`, {
          verification_token,
        })
        .pipe(map((response) => this.mapSession(response.data)));
    }

    return of({
      accessToken: `mock-merchant-${Date.now()}`,
      accountNumber: registration.account_number,
      merchantId: registration.merchant_id,
      businessName: registration.business_name,
      email,
      expiresAt: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
    }).pipe(delay(250));
  }

  getDashboard(): Observable<MerchantDashboardSummary> {
    return of({
      currency: 'NGN',
      availableBalance: 1842750,
      ledgerBalance: 2011750,
      pendingSettlement: 169000,
      transactionValueToday: 192250,
      transactionCountToday: 3,
      settlementAccount: this.profile.settlementAccountNumber,
    }).pipe(delay(550));
  }

  getTransactions(query: MerchantTransactionQuery): Observable<MerchantTransactionPage> {
    const normalizedSearch = query.search.trim().toLowerCase();
    const filtered = this.transactions.filter((transaction) => {
      const matchesSearch = !normalizedSearch ||
        transaction.reference.toLowerCase().includes(normalizedSearch) ||
        transaction.customer.toLowerCase().includes(normalizedSearch);
      const matchesStatus = !query.status || transaction.status === query.status;
      const matchesType = !query.type || transaction.type === query.type;
      const timestamp = new Date(transaction.createdAt).getTime();
      const matchesFrom = !query.dateFrom || timestamp >= new Date(`${query.dateFrom}T00:00:00`).getTime();
      const matchesTo = !query.dateTo || timestamp <= new Date(`${query.dateTo}T23:59:59`).getTime();
      return matchesSearch && matchesStatus && matchesType && matchesFrom && matchesTo;
    });
    const start = (query.page - 1) * query.perPage;

    return of({
      items: filtered.slice(start, start + query.perPage),
      page: query.page,
      perPage: query.perPage,
      total: filtered.length,
      lastPage: Math.max(1, Math.ceil(filtered.length / query.perPage)),
    }).pipe(delay(450));
  }

  getRecentTransactions(): Observable<PortalTransaction[]> {
    return of(this.transactions.slice(0, 5)).pipe(delay(450));
  }

  getProfile(): Observable<MerchantPortalProfile> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantProfile
    ) {
      return this.http
        .get<ApiResponse<MerchantApiRecord>>(`${this.merchantSelfBase}/profile`)
        .pipe(map((response) => this.mapProfile(response.data)));
    }

    return of({ ...this.profile }).pipe(delay(450));
  }

  updateProfile(
    changes: Pick<MerchantPortalProfile, 'contactName' | 'email' | 'phone'>,
  ): Observable<MerchantPortalProfile> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantProfile
    ) {
      return this.http
        .patch<ApiResponse<MerchantApiRecord>>(`${this.merchantSelfBase}/profile`, {
          contact_name: changes.contactName,
          email: changes.email,
          phone: changes.phone,
        })
        .pipe(map((response) => this.mapProfile(response.data)));
    }

    this.profile = { ...this.profile, ...changes };
    return of({ ...this.profile }).pipe(delay(650));
  }

  endSession(): Observable<void> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSession) {
      return this.http.post<void>(`${this.merchantSelfBase}/session/logout`, {});
    }

    return of(undefined).pipe(delay(250));
  }

  private mapRegistration(
    merchant: MerchantApiRecord,
    account_number: string,
  ): MerchantRegistrationConfirmation {
    return {
      merchant_id: merchant.id,
      merchant_code: merchant.merchant_code,
      business_name: merchant.business_name,
      account_number,
      status: merchant.status,
    };
  }

  private mapProfile(merchant: MerchantApiRecord): MerchantPortalProfile {
    const account_number = merchant.customer_account?.account_no ?? this.profile.accountNumber;
    return {
      merchantId: merchant.id,
      merchantCode: merchant.merchant_code,
      businessName: merchant.business_name || merchant.legal_name || '',
      contactName: merchant.contact_name ?? '',
      email: merchant.email ?? '',
      phone: merchant.phone ?? '',
      registrationNumber: merchant.registration_number ?? '',
      accountNumber: account_number,
      status: merchant.status,
      settlementBank: 'MicroBiz MFB',
      settlementAccountName: merchant.business_name,
      settlementAccountNumber: account_number,
    };
  }

  private mapSession(response: MerchantSessionApiResponse): MerchantPortalSession {
    return {
      accessToken: response.access_token,
      accountNumber: response.merchant.account_number,
      merchantId: response.merchant.id,
      businessName: response.merchant.business_name,
      email: response.merchant.email,
      expiresAt: response.expires_at,
    };
  }
}
