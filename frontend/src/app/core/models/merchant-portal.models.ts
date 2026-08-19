export type PortalRole = 'merchant' | 'agent';

export interface PortalLoginDraft {
  role: PortalRole;
  email: string;
}

export interface MerchantPortalSession {
  accessToken: string;
  accountNumber: string;
  merchantId: number;
  businessName: string;
  email: string;
  expiresAt: string;
}

export interface MerchantRegistrationRequest {
  businessName: string;
  contactName: string;
  email: string;
  phone: string;
  registrationNumber: string;
  password: string;
}

export interface MerchantRegistrationResult {
  applicationReference: string;
  status: 'RECEIVED';
}

export interface MerchantDashboardSummary {
  currency: string;
  availableBalance: number;
  ledgerBalance: number;
  pendingSettlement: number;
  transactionValueToday: number;
  transactionCountToday: number;
  settlementAccount: string;
}

export type PortalTransactionStatus = 'SUCCESSFUL' | 'PENDING' | 'FAILED';
export type PortalTransactionType = 'QR_PAYMENT' | 'POS_PAYMENT' | 'SETTLEMENT';

export interface PortalTransaction {
  id: number;
  reference: string;
  type: PortalTransactionType;
  customer: string;
  amount: number;
  fee: number;
  currency: string;
  status: PortalTransactionStatus;
  createdAt: string;
}

export interface MerchantTransactionQuery {
  search: string;
  status: '' | PortalTransactionStatus;
  type: '' | PortalTransactionType;
  dateFrom: string;
  dateTo: string;
  page: number;
  perPage: number;
}

export interface MerchantTransactionPage {
  items: PortalTransaction[];
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export interface MerchantPortalProfile {
  merchantId: number;
  merchantCode: string;
  businessName: string;
  contactName: string;
  email: string;
  phone: string;
  registrationNumber: string;
  accountNumber: string;
  status: 'ACTIVE';
  settlementBank: string;
  settlementAccountName: string;
  settlementAccountNumber: string;
}

