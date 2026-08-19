import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, delay, map, of, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models/api.models';
import {
  ConfirmMerchantRegistrationPayload,
  MerchantAccountPreview,
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
  MerchantPortalProfile,
  MerchantPortalSession,
  MerchantRegistrationConfirmation,
  MerchantSessionApiResponse,
  MerchantSettlementApiResponse,
  MerchantSettlementSummary,
  MerchantSettlementSummaryApiResponse,
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
