import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, delay, map, of, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';
import {
  ConfirmMerchantRegistrationPayload,
  CreateMerchantDisputeRequest,
  CreateMerchantLocationRequest,
  CreateMerchantPaymentAssetRequest,
  CreateMerchantSupportTicketRequest,
  InviteMerchantTeamMemberRequest,
  MerchantAccountPreview,
  MerchantAnalytics,
  MerchantAnalyticsApiResponse,
  MerchantApiRecord,
  MerchantBalanceApiRecord,
  MerchantCollectionApiResponse,
  MerchantDashboardSummary,
  MerchantDashboardApiResponse,
  MerchantDashboardData,
  MerchantOtpChallenge,
  MerchantOtpVerification,
  MerchantMoneyOperationResult,
  MerchantMoneyRequest,
  MerchantMfaSetup,
  MerchantMfaSetupApiResponse,
  MerchantLocation,
  MerchantLocationApiRecord,
  MerchantNotification,
  MerchantNotificationApiRecord,
  MerchantNotificationPreferences,
  MerchantNotificationPreferencesApiResponse,
  MerchantPaymentAsset,
  MerchantPaymentAssetApiRecord,
  MerchantPortalProfile,
  MerchantPortalSession,
  MerchantRegistrationConfirmation,
  MerchantReconciliationSummary,
  MerchantReconciliationSummaryApiResponse,
  MerchantSessionApiResponse,
  MerchantSettlementApiResponse,
  MerchantSettlementSummary,
  MerchantSettlementSummaryApiResponse,
  MerchantSecuritySummary,
  MerchantSecuritySummaryApiResponse,
  MerchantSupportTicket,
  MerchantSupportTicketApiRecord,
  MerchantStatement,
  MerchantStatementApiResponse,
  MerchantDispute,
  MerchantDisputeApiRecord,
  MerchantTeamMember,
  MerchantTeamMemberApiRecord,
  MerchantTerminal,
  MerchantTerminalApiRecord,
  MerchantTerminalRequest,
  MerchantTransactionApiRecord,
  MerchantTransactionPage,
  MerchantTransactionQuery,
  PortalLoginDraft,
  PortalTransaction,
  LaravelPaginator,
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
    { id: 1, transactionNo: 'MCH-Q3F8K2', reference: 'ORDER-1048', type: 'QR_COLLECTION', amount: 48500, currency: 'NGN', status: 'SUCCESSFUL', narration: 'QR collection for order 1048', transactionDate: '2026-08-19T10:42:00Z', posted: true, isReversed: false },
    { id: 2, transactionNo: 'MCH-P9D4L7', reference: 'ORDER-1047', type: 'POS_COLLECTION', amount: 125000, currency: 'NGN', status: 'SUCCESSFUL', narration: 'POS collection', transactionDate: '2026-08-19T09:18:00Z', posted: true, isReversed: false },
    { id: 3, transactionNo: 'MCH-Q1A6V5', reference: 'ORDER-1046', type: 'QR_COLLECTION', amount: 18750, currency: 'NGN', status: 'PENDING', narration: 'QR collection awaiting posting', transactionDate: '2026-08-19T08:07:00Z', posted: false, isReversed: false },
    { id: 4, transactionNo: 'MST-S8N2C4', reference: 'SETTLE-0818', type: 'SETTLEMENT', amount: 350000, currency: 'NGN', status: 'SUCCESSFUL', narration: 'Settlement to linked MicroBiz account', transactionDate: '2026-08-18T16:31:00Z', posted: true, isReversed: false },
    { id: 5, transactionNo: 'MCH-P7X9M1', reference: 'ORDER-1045', type: 'POS_COLLECTION', amount: 76000, currency: 'NGN', status: 'FAILED', narration: 'POS collection failed', transactionDate: '2026-08-18T14:05:00Z', posted: false, isReversed: false },
    { id: 6, transactionNo: 'MCH-Q5T2B8', reference: 'ORDER-1044', type: 'QR_COLLECTION', amount: 32400, currency: 'NGN', status: 'SUCCESSFUL', narration: 'QR collection', transactionDate: '2026-08-17T12:44:00Z', posted: true, isReversed: false },
    { id: 7, transactionNo: 'MCH-P4R6J3', reference: 'ORDER-1043', type: 'POS_COLLECTION', amount: 91000, currency: 'NGN', status: 'SUCCESSFUL', narration: 'POS collection', transactionDate: '2026-08-16T11:20:00Z', posted: true, isReversed: false },
    { id: 8, transactionNo: 'MCH-Q8W1H6', reference: 'ORDER-1042', type: 'QR_COLLECTION', amount: 15300, currency: 'NGN', status: 'SUCCESSFUL', narration: 'QR collection reversed', transactionDate: '2026-08-15T15:17:00Z', posted: true, isReversed: true },
    { id: 9, transactionNo: 'MST-S2E7P9', reference: 'SETTLE-0814', type: 'SETTLEMENT', amount: 500000, currency: 'NGN', status: 'SUCCESSFUL', narration: 'Settlement to linked MicroBiz account', transactionDate: '2026-08-14T16:00:00Z', posted: true, isReversed: false },
  ];
  private mockLedgerBalance = 2011750;
  private mockAvailableBalance = 1842750;
  private mockLockedBalance = 169000;
  private readonly mockOperations = new Map<string, MerchantMoneyOperationResult>();
  private readonly mockLocations: MerchantLocation[] = [
    { id: 1, locationCode: 'LOC-VI001', name: 'Victoria Island Store', tradingName: 'Northstar VI', address: '14 Adeola Odeku Street', state: 'Lagos', localGovernment: 'Eti-Osa', contactPerson: 'Joshua Adeyemi', operatingHours: 'Mon-Sat, 8:00-20:00', status: 'ACTIVE' },
    { id: 2, locationCode: 'LOC-IK002', name: 'Ikeja Outlet', tradingName: 'Northstar Ikeja', address: '22 Allen Avenue', state: 'Lagos', localGovernment: 'Ikeja', contactPerson: 'Amaka Obi', operatingHours: 'Mon-Sun, 9:00-19:00', status: 'ACTIVE' },
  ];
  private readonly mockTerminals: MerchantTerminal[] = [
    { id: 1, locationId: 1, terminalId: 'TID-204891-01', serialNumber: 'SN-MBZ-800214', terminalType: 'ANDROID_POS', provider: 'MicroBiz', model: 'PAX A920', status: 'ACTIVE', applicationVersion: '2.4.1', activatedAt: '2026-08-02T10:00:00Z', lastHeartbeatAt: '2026-08-19T17:58:00Z', lastTransactionAt: '2026-08-19T17:42:00Z' },
    { id: 2, locationId: 2, terminalId: 'TID-204891-02', serialNumber: 'SN-MBZ-800215', terminalType: 'ANDROID_POS', provider: 'MicroBiz', model: 'PAX A920', status: 'SUSPENDED', applicationVersion: '2.4.0', activatedAt: '2026-08-04T09:30:00Z', lastHeartbeatAt: '2026-08-18T12:11:00Z', lastTransactionAt: '2026-08-18T11:55:00Z' },
  ];
  private readonly mockTickets: MerchantSupportTicket[] = [
    { id: 1, ticketNo: 'SUP-000041', category: 'TERMINAL', subject: 'Terminal not connecting', description: 'The Ikeja terminal is unable to connect.', priority: 'HIGH', status: 'IN_PROGRESS', createdAt: '2026-08-18T09:15:00Z', updatedAt: '2026-08-19T08:40:00Z' },
  ];
  private readonly mockNotifications: MerchantNotification[] = [
    { id: 1, title: 'Settlement completed', message: 'Your settlement of ₦350,000 was completed successfully.', type: 'SETTLEMENT', read: false, createdAt: '2026-08-19T16:32:00Z' },
    { id: 2, title: 'Terminal requires attention', message: 'Terminal TID-204891-02 has not sent a heartbeat recently.', type: 'SYSTEM', read: false, createdAt: '2026-08-19T12:20:00Z' },
    { id: 3, title: 'Successful QR collection', message: 'A QR collection of ₦48,500 was posted.', type: 'PAYMENT', read: true, createdAt: '2026-08-19T10:43:00Z' },
  ];
  private mockNotificationPreferences: MerchantNotificationPreferences = { email: true, sms: true, push: true, whatsapp: false };
  private mockSecuritySummary: MerchantSecuritySummary = { mfaEnabled: false, lastPasswordChange: '2026-07-20T08:00:00Z', activeSessionCount: 1 };
  private readonly mockPaymentAssets: MerchantPaymentAsset[] = [
    { id: 1, type: 'PAYMENT_LINK', name: 'Online orders', slug: 'northstar-orders', paymentUrl: 'https://pay.microbiz.test/northstar-orders', amount: null, currency: 'NGN', status: 'ACTIVE', paymentCount: 28, totalValue: 428500, createdAt: '2026-08-05T09:00:00Z' },
    { id: 2, type: 'QR_CODE', name: 'Victoria Island counter', slug: 'northstar-vi-counter', paymentUrl: 'https://pay.microbiz.test/northstar-vi-counter', amount: null, currency: 'NGN', status: 'ACTIVE', paymentCount: 94, totalValue: 1187200, createdAt: '2026-08-02T10:00:00Z' },
  ];
  private readonly mockDisputes: MerchantDispute[] = [
    { id: 1, disputeNo: 'DSP-000018', transactionNo: 'MCH-P7X9M1', reason: 'CUSTOMER_DEBITED', description: 'Customer was debited but the payment failed.', amount: 76000, currency: 'NGN', status: 'UNDER_REVIEW', createdAt: '2026-08-18T15:00:00Z', updatedAt: '2026-08-19T09:20:00Z' },
  ];
  private readonly mockTeamMembers: MerchantTeamMember[] = [
    { id: 1, name: 'Joshua Adeyemi', email: 'merchant@microbiz.test', role: 'OWNER', status: 'ACTIVE', lastActiveAt: '2026-08-19T18:00:00Z' },
    { id: 2, name: 'Amaka Obi', email: 'amaka@northstar.test', role: 'FINANCE', status: 'ACTIVE', lastActiveAt: '2026-08-19T15:44:00Z' },
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

  getDashboard(): Observable<MerchantDashboardData> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantDashboard
    ) {
      return this.http
        .get<ApiResponse<MerchantDashboardApiResponse>>(`${this.merchantSelfBase}/dashboard`)
        .pipe(map((response) => this.mapDashboard(response.data)));
    }

    return of({
      summary: {
        currency: 'NGN',
        availableBalance: this.mockAvailableBalance,
        ledgerBalance: this.mockLedgerBalance,
        lockedBalance: this.mockLockedBalance,
        transactionValueToday: 192250,
        transactionCountToday: 2,
        pendingTransactionCount: 1,
        settlementAccount: this.profile.settlementAccountNumber,
      },
      recentTransactions: this.transactions.slice(0, 5),
    }).pipe(delay(550));
  }

  getTransactions(query: MerchantTransactionQuery): Observable<MerchantTransactionPage> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantTransactions
    ) {
      return this.http
        .get<ApiResponse<LaravelPaginator<MerchantTransactionApiRecord>>>(
          `${this.merchantSelfBase}/transactions`,
          { params: this.transactionParams(query) },
        )
        .pipe(map((response) => this.mapTransactionPage(response.data)));
    }

    const filtered = this.filterTransactions(query);
    const start = (query.page - 1) * query.perPage;

    return of({
      items: filtered.slice(start, start + query.perPage),
      page: query.page,
      perPage: query.perPage,
      total: filtered.length,
      lastPage: Math.max(1, Math.ceil(filtered.length / query.perPage)),
    }).pipe(delay(450));
  }

  getTransaction(transactionId: number): Observable<PortalTransaction> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantTransactions
    ) {
      return this.http
        .get<ApiResponse<MerchantTransactionApiRecord>>(
          `${this.merchantSelfBase}/transactions/${transactionId}`,
        )
        .pipe(map((response) => this.mapTransaction(response.data)));
    }

    const transaction = this.transactions.find((item) => item.id === transactionId);
    return transaction
      ? of({ ...transaction }).pipe(delay(250))
      : throwError(() => new Error('Transaction not found.'));
  }

  exportTransactions(query: MerchantTransactionQuery): Observable<Blob> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantTransactions
    ) {
      return this.http.get(`${this.merchantSelfBase}/transactions/export`, {
        params: this.transactionParams(query),
        responseType: 'blob',
      });
    }

    const rows = this.filterTransactions(query);
    const csv = [
      ['Transaction Number', 'Reference', 'Type', 'Narration', 'Amount', 'Currency', 'Status', 'Posted', 'Reversed', 'Transaction Date'],
      ...rows.map((item) => [
        item.transactionNo,
        item.reference ?? '',
        item.type,
        item.narration ?? '',
        item.amount,
        item.currency,
        item.status,
        item.posted ? 'Yes' : 'No',
        item.isReversed ? 'Yes' : 'No',
        item.transactionDate,
      ]),
    ].map((row) => row.map((value) => this.csvCell(String(value))).join(',')).join('\n');

    return of(new Blob([csv], { type: 'text/csv;charset=utf-8' })).pipe(delay(300));
  }

  collectPayment(
    method: 'qr' | 'pos',
    payload: MerchantMoneyRequest,
  ): Observable<MerchantMoneyOperationResult> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantCollections
    ) {
      return this.http
        .post<ApiResponse<MerchantCollectionApiResponse>>(
          `${this.merchantSelfBase}/collections/${method}`,
          payload,
        )
        .pipe(map((response) => ({
          transaction: this.mapTransaction(response.data.transaction),
          balance: this.mapBalance(response.data.balance),
        })));
    }

    const previous = this.mockOperations.get(payload.idempotency_key);
    if (previous) return of(previous).pipe(delay(300));

    const amount = Number(payload.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      return throwError(() => new Error('Enter a valid collection amount.'));
    }

    this.mockLedgerBalance += amount;
    this.mockLockedBalance += amount;
    const transaction: PortalTransaction = {
      id: this.nextTransactionId(),
      transactionNo: `MCH-${Date.now().toString().slice(-8)}`,
      reference: payload.reference ?? null,
      type: method === 'qr' ? 'QR_COLLECTION' : 'POS_COLLECTION',
      amount,
      currency: 'NGN',
      status: 'SUCCESSFUL',
      narration: payload.narration ?? `${method.toUpperCase()} merchant collection`,
      transactionDate: new Date().toISOString(),
      posted: true,
      isReversed: false,
    };
    this.transactions.unshift(transaction);
    const result = { transaction, balance: this.mockBalance() };
    this.mockOperations.set(payload.idempotency_key, result);
    return of(result).pipe(delay(750));
  }

  getSettlementSummary(): Observable<MerchantSettlementSummary> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantSettlements
    ) {
      return this.http
        .get<ApiResponse<MerchantSettlementSummaryApiResponse>>(
          `${this.merchantSelfBase}/settlements/summary`,
        )
        .pipe(map((response) => this.mapSettlementSummary(response.data)));
    }

    return of({
      currency: 'NGN',
      ledgerBalance: this.mockLedgerBalance,
      lockedBalance: this.mockLockedBalance,
      availableToSettle: this.mockLockedBalance,
      settlementAccount: this.profile.settlementAccountNumber,
      settlementFrequency: 'T_PLUS_1',
    }).pipe(delay(450));
  }

  requestSettlement(payload: MerchantMoneyRequest): Observable<MerchantMoneyOperationResult> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantSettlements
    ) {
      return this.http
        .post<ApiResponse<MerchantSettlementApiResponse>>(
          `${this.merchantSelfBase}/settlements`,
          payload,
        )
        .pipe(map((response) => ({
          transaction: this.mapTransaction(response.data.merchant_transaction),
          balance: this.mapBalance(response.data.merchant_balance),
        })));
    }

    const previous = this.mockOperations.get(payload.idempotency_key);
    if (previous) return of(previous).pipe(delay(300));

    const amount = Number(payload.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      return throwError(() => new Error('Enter a valid settlement amount.'));
    }
    if (amount > this.mockLockedBalance) {
      return throwError(() => new Error('The settlement amount exceeds the available settlement balance.'));
    }

    this.mockLedgerBalance -= amount;
    this.mockLockedBalance -= amount;
    const transaction: PortalTransaction = {
      id: this.nextTransactionId(),
      transactionNo: `MST-${Date.now().toString().slice(-8)}`,
      reference: payload.reference ?? null,
      type: 'SETTLEMENT',
      amount,
      currency: 'NGN',
      status: 'SUCCESSFUL',
      narration: payload.narration ?? 'Settlement to linked MicroBiz account',
      transactionDate: new Date().toISOString(),
      posted: true,
      isReversed: false,
    };
    this.transactions.unshift(transaction);
    const result = { transaction, balance: this.mockBalance() };
    this.mockOperations.set(payload.idempotency_key, result);
    return of(result).pipe(delay(850));
  }

  getSettlements(query: MerchantTransactionQuery): Observable<MerchantTransactionPage> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantSettlements
    ) {
      return this.http
        .get<ApiResponse<LaravelPaginator<MerchantTransactionApiRecord>>>(
          `${this.merchantSelfBase}/settlements`,
          { params: this.transactionParams(query) },
        )
        .pipe(map((response) => this.mapTransactionPage(response.data)));
    }

    const filtered = this.filterTransactions({ ...query, type: 'SETTLEMENT' });
    const start = (query.page - 1) * query.perPage;
    return of({
      items: filtered.slice(start, start + query.perPage),
      page: query.page,
      perPage: query.perPage,
      total: filtered.length,
      lastPage: Math.max(1, Math.ceil(filtered.length / query.perPage)),
    }).pipe(delay(400));
  }

  getSettlement(settlementId: number): Observable<PortalTransaction> {
    if (
      environment.merchantPortalApiMode === 'live' &&
      environment.merchantPortalLiveFeatures.merchantSettlements
    ) {
      return this.http
        .get<ApiResponse<MerchantTransactionApiRecord>>(
          `${this.merchantSelfBase}/settlements/${settlementId}`,
        )
        .pipe(map((response) => this.mapTransaction(response.data)));
    }

    const settlement = this.transactions.find(
      (transaction) => transaction.id === settlementId && transaction.type === 'SETTLEMENT',
    );
    return settlement
      ? of({ ...settlement }).pipe(delay(250))
      : throwError(() => new Error('Settlement not found.'));
  }

  getLocations(): Observable<MerchantLocation[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantBusiness) {
      return this.http
        .get<ApiResponse<MerchantLocationApiRecord[]>>(`${this.merchantSelfBase}/locations`)
        .pipe(map((response) => response.data.map((location) => this.mapLocation(location))));
    }
    return of(this.mockLocations.map((location) => ({ ...location }))).pipe(delay(350));
  }

  createLocation(payload: CreateMerchantLocationRequest): Observable<MerchantLocation> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantBusiness) {
      return this.http
        .post<ApiResponse<MerchantLocationApiRecord>>(`${this.merchantSelfBase}/locations`, payload)
        .pipe(map((response) => this.mapLocation(response.data)));
    }
    const location: MerchantLocation = {
      id: Math.max(0, ...this.mockLocations.map((item) => item.id)) + 1,
      locationCode: `LOC-${Date.now().toString().slice(-6)}`,
      name: payload.name,
      tradingName: payload.trading_name ?? '',
      address: payload.address,
      state: payload.state ?? '',
      localGovernment: payload.local_government ?? '',
      contactPerson: payload.contact_person ?? '',
      operatingHours: payload.operating_hours ?? '',
      status: 'ACTIVE',
    };
    this.mockLocations.unshift(location);
    return of({ ...location }).pipe(delay(650));
  }

  getTerminals(): Observable<MerchantTerminal[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantBusiness) {
      return this.http
        .get<ApiResponse<MerchantTerminalApiRecord[]>>(`${this.merchantSelfBase}/terminals`)
        .pipe(map((response) => response.data.map((terminal) => this.mapTerminal(terminal))));
    }
    return of(this.mockTerminals.map((terminal) => ({ ...terminal }))).pipe(delay(400));
  }

  requestTerminal(payload: MerchantTerminalRequest): Observable<MerchantTerminal> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantBusiness) {
      return this.http
        .post<ApiResponse<MerchantTerminalApiRecord>>(`${this.merchantSelfBase}/terminal-requests`, payload)
        .pipe(map((response) => this.mapTerminal(response.data)));
    }
    const terminal: MerchantTerminal = {
      id: Math.max(0, ...this.mockTerminals.map((item) => item.id)) + 1,
      locationId: payload.merchant_location_id ?? null,
      terminalId: `REQUEST-${Date.now().toString().slice(-6)}`,
      serialNumber: 'Pending assignment',
      terminalType: payload.terminal_type,
      provider: 'MicroBiz',
      model: `${payload.quantity} device request`,
      status: 'REQUESTED',
      applicationVersion: '',
      activatedAt: null,
      lastHeartbeatAt: null,
      lastTransactionAt: null,
    };
    this.mockTerminals.unshift(terminal);
    return of({ ...terminal }).pipe(delay(700));
  }

  getSupportTickets(): Observable<MerchantSupportTicket[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSupport) {
      return this.http
        .get<ApiResponse<MerchantSupportTicketApiRecord[]>>(`${this.merchantSelfBase}/support/tickets`)
        .pipe(map((response) => response.data.map((ticket) => this.mapSupportTicket(ticket))));
    }
    return of(this.mockTickets.map((ticket) => ({ ...ticket }))).pipe(delay(400));
  }

  createSupportTicket(payload: CreateMerchantSupportTicketRequest): Observable<MerchantSupportTicket> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSupport) {
      return this.http
        .post<ApiResponse<MerchantSupportTicketApiRecord>>(`${this.merchantSelfBase}/support/tickets`, payload)
        .pipe(map((response) => this.mapSupportTicket(response.data)));
    }
    const now = new Date().toISOString();
    const ticket: MerchantSupportTicket = {
      id: Math.max(0, ...this.mockTickets.map((item) => item.id)) + 1,
      ticketNo: `SUP-${Date.now().toString().slice(-6)}`,
      category: payload.category,
      subject: payload.subject,
      description: payload.description,
      priority: payload.priority,
      status: 'OPEN',
      createdAt: now,
      updatedAt: now,
    };
    this.mockTickets.unshift(ticket);
    return of({ ...ticket }).pipe(delay(650));
  }

  getNotifications(): Observable<MerchantNotification[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .get<ApiResponse<MerchantNotificationApiRecord[]>>(`${this.merchantSelfBase}/notifications`)
        .pipe(map((response) => response.data.map((notification) => this.mapNotification(notification))));
    }
    return of(this.mockNotifications.map((notification) => ({ ...notification }))).pipe(delay(350));
  }

  markNotificationRead(notificationId: number): Observable<void> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .patch<ApiResponse<null>>(`${this.merchantSelfBase}/notifications/${notificationId}/read`, {})
        .pipe(map(() => undefined));
    }
    const notification = this.mockNotifications.find((item) => item.id === notificationId);
    if (notification) notification.read = true;
    return of(undefined).pipe(delay(150));
  }

  getNotificationPreferences(): Observable<MerchantNotificationPreferences> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .get<ApiResponse<MerchantNotificationPreferencesApiResponse>>(`${this.merchantSelfBase}/settings/notifications`)
        .pipe(map((response) => ({ ...response.data })));
    }
    return of({ ...this.mockNotificationPreferences }).pipe(delay(300));
  }

  updateNotificationPreferences(preferences: MerchantNotificationPreferences): Observable<MerchantNotificationPreferences> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .patch<ApiResponse<MerchantNotificationPreferencesApiResponse>>(`${this.merchantSelfBase}/settings/notifications`, preferences)
        .pipe(map((response) => ({ ...response.data })));
    }
    this.mockNotificationPreferences = { ...preferences };
    return of({ ...this.mockNotificationPreferences }).pipe(delay(450));
  }

  getSecuritySummary(): Observable<MerchantSecuritySummary> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .get<ApiResponse<MerchantSecuritySummaryApiResponse>>(`${this.merchantSelfBase}/settings/security`)
        .pipe(map((response) => ({ mfaEnabled: response.data.mfa_enabled, lastPasswordChange: response.data.last_password_change, activeSessionCount: response.data.active_session_count })));
    }
    return of({ ...this.mockSecuritySummary }).pipe(delay(300));
  }

  changeMerchantPassword(current_password: string, password: string, password_confirmation: string): Observable<void> {
    if (password !== password_confirmation) return throwError(() => new Error('The new passwords do not match.'));
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .post<ApiResponse<null>>(`${this.merchantSelfBase}/settings/security/password`, { current_password, password, password_confirmation })
        .pipe(map(() => undefined));
    }
    if (!current_password || password.length < 8) return throwError(() => new Error('Enter your current password and a new password of at least 8 characters.'));
    this.mockSecuritySummary = { ...this.mockSecuritySummary, lastPasswordChange: new Date().toISOString() };
    return of(undefined).pipe(delay(550));
  }

  beginMerchantMfaSetup(): Observable<MerchantMfaSetup> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .post<ApiResponse<MerchantMfaSetupApiResponse>>(`${this.merchantSelfBase}/settings/security/mfa/setup`, {})
        .pipe(map((response) => ({ setupId: response.data.setup_id, maskedDestination: response.data.masked_destination })));
    }
    return of({ setupId: `mock-mfa-${Date.now()}`, maskedDestination: '******0142' }).pipe(delay(350));
  }

  confirmMerchantMfa(setup_id: string, code: string): Observable<MerchantSecuritySummary> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .post<ApiResponse<MerchantSecuritySummaryApiResponse>>(`${this.merchantSelfBase}/settings/security/mfa/confirm`, { setup_id, code })
        .pipe(map((response) => ({ mfaEnabled: response.data.mfa_enabled, lastPasswordChange: response.data.last_password_change, activeSessionCount: response.data.active_session_count })));
    }
    if (code !== '0000') return throwError(() => new Error('The verification code is incorrect. Use 0000 for this preview.'));
    this.mockSecuritySummary = { ...this.mockSecuritySummary, mfaEnabled: true };
    return of({ ...this.mockSecuritySummary }).pipe(delay(450));
  }

  disableMerchantMfa(current_password: string): Observable<MerchantSecuritySummary> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantSettings) {
      return this.http
        .post<ApiResponse<MerchantSecuritySummaryApiResponse>>(`${this.merchantSelfBase}/settings/security/mfa/disable`, { current_password })
        .pipe(map((response) => ({ mfaEnabled: response.data.mfa_enabled, lastPasswordChange: response.data.last_password_change, activeSessionCount: response.data.active_session_count })));
    }
    if (!current_password) return throwError(() => new Error('Enter your current password to disable two-step verification.'));
    this.mockSecuritySummary = { ...this.mockSecuritySummary, mfaEnabled: false };
    return of({ ...this.mockSecuritySummary }).pipe(delay(450));
  }

  getMerchantAnalytics(from: string, to: string): Observable<MerchantAnalytics> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantReports) {
      const params = new HttpParams().set('from', from).set('to', to);
      return this.http.get<ApiResponse<MerchantAnalyticsApiResponse>>(`${this.merchantSelfBase}/analytics`, { params }).pipe(
        map(({ data }) => ({ currency: data.currency, totalValue: Number(data.total_value), totalCount: data.total_count, averageValue: Number(data.average_value), successRate: Number(data.success_rate), changePercent: Number(data.change_percent), daily: data.daily.map((item) => ({ ...item, value: Number(item.value) })), channels: data.channels.map((item) => ({ ...item, value: Number(item.value), percent: Number(item.percent) })), locations: data.locations.map((item) => ({ ...item, value: Number(item.value) })) })),
      );
    }
    return of({
      currency: 'NGN', totalValue: 1932150, totalCount: 128, averageValue: 15095, successRate: 96.8, changePercent: 12.4,
      daily: [{ label: 'Mon', value: 188000, count: 14 }, { label: 'Tue', value: 242500, count: 18 }, { label: 'Wed', value: 216300, count: 17 }, { label: 'Thu', value: 324800, count: 22 }, { label: 'Fri', value: 376050, count: 25 }, { label: 'Sat', value: 418500, count: 27 }, { label: 'Sun', value: 166000, count: 5 }],
      channels: [{ name: 'POS', value: 1231200, percent: 63.7 }, { name: 'QR', value: 700950, percent: 36.3 }],
      locations: [{ name: 'Victoria Island Store', value: 1187200, count: 79 }, { name: 'Ikeja Outlet', value: 744950, count: 49 }],
    }).pipe(delay(400));
  }

  getMerchantStatement(from: string, to: string): Observable<MerchantStatement> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantReports) {
      const params = new HttpParams().set('from', from).set('to', to);
      return this.http.get<ApiResponse<MerchantStatementApiResponse>>(`${this.merchantSelfBase}/statements`, { params }).pipe(map(({ data }) => ({ statementNo: data.statement_no, accountNumber: data.account_number, businessName: data.business_name, currency: data.currency, from: data.from, to: data.to, openingBalance: Number(data.opening_balance), totalCredits: Number(data.total_credits), totalDebits: Number(data.total_debits), closingBalance: Number(data.closing_balance), entries: data.entries.map((entry) => this.mapTransaction(entry)) })));
    }
    const entries = this.transactions.filter((item) => item.transactionDate.slice(0, 10) >= from && item.transactionDate.slice(0, 10) <= to);
    const credits = entries.filter((item) => item.type !== 'SETTLEMENT' && item.status === 'SUCCESSFUL' && !item.isReversed).reduce((sum, item) => sum + item.amount, 0);
    const debits = entries.filter((item) => item.type === 'SETTLEMENT' && item.status === 'SUCCESSFUL').reduce((sum, item) => sum + item.amount, 0);
    return of({ statementNo: `STM-${from.replaceAll('-', '')}-${to.replaceAll('-', '')}`, accountNumber: this.profile.accountNumber, businessName: this.profile.businessName, currency: 'NGN', from, to, openingBalance: this.mockLedgerBalance - credits + debits, totalCredits: credits, totalDebits: debits, closingBalance: this.mockLedgerBalance, entries }).pipe(delay(350));
  }

  downloadMerchantStatement(from: string, to: string, format: 'csv' | 'pdf'): Observable<Blob> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantReports) {
      const params = new HttpParams().set('from', from).set('to', to).set('format', format);
      return this.http.get(`${this.merchantSelfBase}/statements/export`, { params, responseType: 'blob' });
    }
    const content = `MicroBiz merchant statement\nAccount,${this.profile.accountNumber}\nPeriod,${from} to ${to}\nFormat preview,${format.toUpperCase()}`;
    return of(new Blob([content], { type: format === 'csv' ? 'text/csv' : 'application/pdf' })).pipe(delay(250));
  }

  getPaymentAssets(): Observable<MerchantPaymentAsset[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantPaymentTools) {
      return this.http.get<ApiResponse<MerchantPaymentAssetApiRecord[]>>(`${this.merchantSelfBase}/payment-assets`).pipe(map(({ data }) => data.map((item) => this.mapPaymentAsset(item))));
    }
    return of(this.mockPaymentAssets.map((item) => ({ ...item }))).pipe(delay(350));
  }

  createPaymentAsset(payload: CreateMerchantPaymentAssetRequest): Observable<MerchantPaymentAsset> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantPaymentTools) {
      return this.http.post<ApiResponse<MerchantPaymentAssetApiRecord>>(`${this.merchantSelfBase}/payment-assets`, payload).pipe(map(({ data }) => this.mapPaymentAsset(data)));
    }
    const slug = payload.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const item: MerchantPaymentAsset = { id: Date.now(), type: payload.type, name: payload.name, slug, paymentUrl: `https://pay.microbiz.test/${slug}`, amount: payload.amount ? Number(payload.amount) : null, currency: 'NGN', status: 'ACTIVE', paymentCount: 0, totalValue: 0, createdAt: new Date().toISOString() };
    this.mockPaymentAssets.unshift(item);
    return of({ ...item }).pipe(delay(450));
  }

  updatePaymentAssetStatus(id: number, status: MerchantPaymentAsset['status']): Observable<MerchantPaymentAsset> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantPaymentTools) {
      return this.http.patch<ApiResponse<MerchantPaymentAssetApiRecord>>(`${this.merchantSelfBase}/payment-assets/${id}`, { status }).pipe(map(({ data }) => this.mapPaymentAsset(data)));
    }
    const item = this.mockPaymentAssets.find((asset) => asset.id === id);
    if (!item) return throwError(() => new Error('Payment asset not found.'));
    item.status = status;
    return of({ ...item }).pipe(delay(250));
  }

  getReconciliationSummary(from: string, to: string): Observable<MerchantReconciliationSummary> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      const params = new HttpParams().set('from', from).set('to', to);
      return this.http.get<ApiResponse<MerchantReconciliationSummaryApiResponse>>(`${this.merchantSelfBase}/reconciliation`, { params }).pipe(map(({ data }) => ({ currency: data.currency, period: data.period, expectedValue: Number(data.expected_value), settledValue: Number(data.settled_value), variance: Number(data.variance), unmatchedCount: data.unmatched_count, lastReconciledAt: data.last_reconciled_at })));
    }
    return of({ currency: 'NGN', period: `${from} to ${to}`, expectedValue: 1932150, settledValue: 1856150, variance: 76000, unmatchedCount: 1, lastReconciledAt: '2026-08-19T17:00:00Z' }).pipe(delay(350));
  }

  getMerchantDisputes(): Observable<MerchantDispute[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      return this.http.get<ApiResponse<MerchantDisputeApiRecord[]>>(`${this.merchantSelfBase}/disputes`).pipe(map(({ data }) => data.map((item) => this.mapDispute(item))));
    }
    return of(this.mockDisputes.map((item) => ({ ...item }))).pipe(delay(300));
  }

  createMerchantDispute(payload: CreateMerchantDisputeRequest): Observable<MerchantDispute> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      return this.http.post<ApiResponse<MerchantDisputeApiRecord>>(`${this.merchantSelfBase}/disputes`, payload).pipe(map(({ data }) => this.mapDispute(data)));
    }
    const transaction = this.transactions.find((item) => item.transactionNo === payload.transaction_no);
    if (!transaction) return throwError(() => new Error('Enter a transaction number from your merchant history.'));
    const dispute: MerchantDispute = { id: Date.now(), disputeNo: `DSP-${String(this.mockDisputes.length + 19).padStart(6, '0')}`, transactionNo: transaction.transactionNo, reason: payload.reason, description: payload.description, amount: transaction.amount, currency: transaction.currency, status: 'OPEN', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() };
    this.mockDisputes.unshift(dispute);
    return of({ ...dispute }).pipe(delay(450));
  }

  getMerchantTeam(): Observable<MerchantTeamMember[]> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      return this.http.get<ApiResponse<MerchantTeamMemberApiRecord[]>>(`${this.merchantSelfBase}/team`).pipe(map(({ data }) => data.map((item) => this.mapTeamMember(item))));
    }
    return of(this.mockTeamMembers.map((item) => ({ ...item }))).pipe(delay(300));
  }

  inviteMerchantTeamMember(payload: InviteMerchantTeamMemberRequest): Observable<MerchantTeamMember> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      return this.http.post<ApiResponse<MerchantTeamMemberApiRecord>>(`${this.merchantSelfBase}/team/invitations`, payload).pipe(map(({ data }) => this.mapTeamMember(data)));
    }
    if (this.mockTeamMembers.some((item) => item.email.toLowerCase() === payload.email.toLowerCase())) return throwError(() => new Error('This email already belongs to the merchant team.'));
    const member: MerchantTeamMember = { id: Date.now(), ...payload, status: 'INVITED', lastActiveAt: null };
    this.mockTeamMembers.push(member);
    return of({ ...member }).pipe(delay(450));
  }

  updateMerchantTeamMember(id: number, role: MerchantTeamMember['role'], status: MerchantTeamMember['status']): Observable<MerchantTeamMember> {
    if (environment.merchantPortalApiMode === 'live' && environment.merchantPortalLiveFeatures.merchantOperations) {
      return this.http.patch<ApiResponse<MerchantTeamMemberApiRecord>>(`${this.merchantSelfBase}/team/${id}`, { role, status }).pipe(map(({ data }) => this.mapTeamMember(data)));
    }
    const member = this.mockTeamMembers.find((item) => item.id === id);
    if (!member || member.role === 'OWNER') return throwError(() => new Error('The business owner cannot be changed here.'));
    member.role = role; member.status = status;
    return of({ ...member }).pipe(delay(250));
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

  private transactionParams(query: MerchantTransactionQuery): HttpParams {
    let params = new HttpParams()
      .set('page', query.page)
      .set('per_page', query.perPage);

    if (query.search.trim()) params = params.set('search', query.search.trim());
    if (query.status) params = params.set('status', query.status);
    if (query.type) params = params.set('transaction_type', query.type);
    if (query.dateFrom) params = params.set('from', query.dateFrom);
    if (query.dateTo) params = params.set('to', query.dateTo);

    return params;
  }

  private filterTransactions(query: MerchantTransactionQuery): PortalTransaction[] {
    const normalizedSearch = query.search.trim().toLowerCase();
    return this.transactions.filter((transaction) => {
      const matchesSearch = !normalizedSearch || [
        transaction.transactionNo,
        transaction.reference ?? '',
        transaction.narration ?? '',
      ].some((value) => value.toLowerCase().includes(normalizedSearch));
      const matchesStatus = !query.status || transaction.status === query.status;
      const matchesType = !query.type || transaction.type === query.type;
      const timestamp = new Date(transaction.transactionDate).getTime();
      const matchesFrom = !query.dateFrom || timestamp >= new Date(`${query.dateFrom}T00:00:00`).getTime();
      const matchesTo = !query.dateTo || timestamp <= new Date(`${query.dateTo}T23:59:59`).getTime();
      return matchesSearch && matchesStatus && matchesType && matchesFrom && matchesTo;
    });
  }

  private mapDashboard(response: MerchantDashboardApiResponse): MerchantDashboardData {
    const summary: MerchantDashboardSummary = {
      currency: response.balance.currency,
      availableBalance: Number(response.balance.available_balance),
      ledgerBalance: Number(response.balance.ledger_balance),
      lockedBalance: Number(response.balance.locked_balance),
      transactionValueToday: Number(response.today.successful_value),
      transactionCountToday: response.today.successful_count,
      pendingTransactionCount: response.today.pending_count,
      settlementAccount: response.settlement_account,
    };

    return {
      summary,
      recentTransactions: response.recent_transactions.map((transaction) => this.mapTransaction(transaction)),
    };
  }

  private mapTransactionPage(
    paginator: LaravelPaginator<MerchantTransactionApiRecord>,
  ): MerchantTransactionPage {
    return {
      items: paginator.data.map((transaction) => this.mapTransaction(transaction)),
      page: paginator.current_page,
      perPage: paginator.per_page,
      total: paginator.total,
      lastPage: paginator.last_page,
    };
  }

  private mapTransaction(transaction: MerchantTransactionApiRecord): PortalTransaction {
    return {
      id: transaction.id,
      transactionNo: transaction.transaction_no,
      reference: transaction.reference,
      type: transaction.transaction_type,
      amount: Number(transaction.amount),
      currency: transaction.currency,
      status: transaction.status,
      narration: transaction.narration,
      transactionDate: transaction.transaction_date,
      posted: transaction.posted,
      isReversed: transaction.is_reversed,
    };
  }

  private csvCell(value: string): string {
    return /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
  }

  private mapBalance(balance: MerchantBalanceApiRecord): MerchantMoneyOperationResult['balance'] {
    return {
      currency: balance.currency,
      ledgerBalance: Number(balance.ledger_balance),
      availableBalance: Number(balance.available_balance),
      lockedBalance: Number(balance.locked_balance),
    };
  }

  private mockBalance(): MerchantMoneyOperationResult['balance'] {
    return {
      currency: 'NGN',
      ledgerBalance: this.mockLedgerBalance,
      availableBalance: this.mockAvailableBalance,
      lockedBalance: this.mockLockedBalance,
    };
  }

  private mapSettlementSummary(
    summary: MerchantSettlementSummaryApiResponse,
  ): MerchantSettlementSummary {
    return {
      currency: summary.balance.currency,
      ledgerBalance: Number(summary.balance.ledger_balance),
      lockedBalance: Number(summary.balance.locked_balance),
      availableToSettle: Number(summary.available_to_settle),
      settlementAccount: summary.settlement_account,
      settlementFrequency: summary.settlement_frequency,
    };
  }

  private nextTransactionId(): number {
    return Math.max(0, ...this.transactions.map((transaction) => transaction.id)) + 1;
  }

  private mapLocation(location: MerchantLocationApiRecord): MerchantLocation {
    return {
      id: location.id,
      locationCode: location.location_code,
      name: location.name,
      tradingName: location.trading_name ?? '',
      address: location.address,
      state: location.state ?? '',
      localGovernment: location.local_government ?? '',
      contactPerson: location.contact_person ?? '',
      operatingHours: location.operating_hours ?? '',
      status: location.status,
    };
  }

  private mapTerminal(terminal: MerchantTerminalApiRecord): MerchantTerminal {
    return {
      id: terminal.id,
      locationId: terminal.merchant_location_id,
      terminalId: terminal.terminal_id,
      serialNumber: terminal.serial_number,
      terminalType: terminal.terminal_type,
      provider: terminal.provider ?? '',
      model: terminal.model ?? '',
      status: terminal.status,
      applicationVersion: terminal.application_version ?? '',
      activatedAt: terminal.activated_at,
      lastHeartbeatAt: terminal.last_heartbeat_at,
      lastTransactionAt: terminal.last_transaction_at,
    };
  }

  private mapSupportTicket(ticket: MerchantSupportTicketApiRecord): MerchantSupportTicket {
    return {
      id: ticket.id,
      ticketNo: ticket.ticket_no,
      category: ticket.category,
      subject: ticket.subject,
      description: ticket.description,
      priority: ticket.priority,
      status: ticket.status,
      createdAt: ticket.created_at,
      updatedAt: ticket.updated_at,
    };
  }

  private mapNotification(notification: MerchantNotificationApiRecord): MerchantNotification {
    return {
      id: notification.id,
      title: notification.title,
      message: notification.message,
      type: notification.type,
      read: notification.read,
      createdAt: notification.created_at,
    };
  }

  private mapPaymentAsset(asset: MerchantPaymentAssetApiRecord): MerchantPaymentAsset {
    return { id: asset.id, type: asset.type, name: asset.name, slug: asset.slug, paymentUrl: asset.payment_url, amount: asset.amount === null ? null : Number(asset.amount), currency: asset.currency, status: asset.status, paymentCount: asset.payment_count, totalValue: Number(asset.total_value), createdAt: asset.created_at };
  }

  private mapDispute(dispute: MerchantDisputeApiRecord): MerchantDispute {
    return { id: dispute.id, disputeNo: dispute.dispute_no, transactionNo: dispute.transaction_no, reason: dispute.reason, description: dispute.description, amount: Number(dispute.amount), currency: dispute.currency, status: dispute.status, createdAt: dispute.created_at, updatedAt: dispute.updated_at };
  }

  private mapTeamMember(member: MerchantTeamMemberApiRecord): MerchantTeamMember {
    return { id: member.id, name: member.name, email: member.email, role: member.role, status: member.status, lastActiveAt: member.last_active_at };
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
