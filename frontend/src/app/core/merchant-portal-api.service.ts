import { Injectable } from '@angular/core';
import { Observable, delay, of, throwError } from 'rxjs';
import {
  MerchantDashboardSummary,
  MerchantPortalProfile,
  MerchantPortalSession,
  MerchantRegistrationRequest,
  MerchantRegistrationResult,
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

  verifyAccount(accountNumber: string, draft: PortalLoginDraft): Observable<MerchantPortalSession> {
    if (!/^\d{10}$/.test(accountNumber)) {
      return throwError(() => new Error('Enter a valid 10-digit MicroBiz account number.')).pipe(delay(450));
    }

    if (draft.role === 'agent') {
      return throwError(() => new Error('The agent workspace is not available in this build yet.')).pipe(delay(500));
    }

    return of({
      accessToken: `mock-merchant-${Date.now()}`,
      accountNumber,
      merchantId: this.profile.merchantId,
      businessName: this.profile.businessName,
      email: draft.email,
      expiresAt: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
    }).pipe(delay(700));
  }

  register(payload: MerchantRegistrationRequest): Observable<MerchantRegistrationResult> {
    void payload;
    return of({
      applicationReference: `MBR-${Date.now().toString().slice(-8)}`,
      status: 'RECEIVED' as const,
    }).pipe(delay(900));
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
    return of({ ...this.profile }).pipe(delay(450));
  }

  updateProfile(changes: Pick<MerchantPortalProfile, 'contactName' | 'email' | 'phone'>): Observable<MerchantPortalProfile> {
    this.profile = { ...this.profile, ...changes };
    return of({ ...this.profile }).pipe(delay(650));
  }

  endSession(): Observable<void> {
    return of(undefined).pipe(delay(250));
  }
}
