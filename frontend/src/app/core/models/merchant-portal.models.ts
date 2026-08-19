export type PortalRole = 'merchant' | 'agent';

export interface PortalLoginDraft {
  role: PortalRole;
  email: string;
}

/** Exact response contract from POST /api/v1/reg/merchant/preview. */
export interface MerchantAccountPreview {
  fincore_client_id: number;
  account_no: string;
  external_id: string | null;
  display_name: string | null;
  mobile_no: string | null;
  email: string | null;
  office_name: string | null;
  legal_form: string | null;
  status: string | null;
  active: boolean;
}

/** Temporary frontend contract until send/verify OTP endpoints are exposed. */
export interface MerchantOtpChallenge {
  challenge_id: string;
  masked_phone: string;
  expires_at: string;
}

export interface MerchantOtpVerification {
  verified: boolean;
  verification_token?: string;
}

/** Exact payload naming for POST /api/v1/reg/merchant. */
export interface ConfirmMerchantRegistrationPayload {
  fincore_client_id: number;
  legal_name: string;
  trading_name?: string;
  business_type?: string;
  registration_number: string;
  contact_name: string;
  phone: string;
  email?: string;
  branch_id: number;
  verification_token?: string;
}

export interface MerchantRegistrationConfirmation {
  merchant_id: number;
  merchant_code: string;
  business_name: string;
  account_number: string;
  status: string;
}

/** Fields consumed from merchant registration and the proposed self-profile response. */
export interface MerchantApiRecord {
  id: number;
  merchant_code: string;
  business_name: string;
  legal_name: string | null;
  contact_name: string | null;
  email: string | null;
  phone: string | null;
  registration_number: string | null;
  status: string;
  customer_account?: {
    account_no: string;
  } | null;
  balance?: {
    currency: string;
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
  } | null;
}

export interface MerchantPortalSession {
  accessToken: string;
  accountNumber: string;
  merchantId: number;
  businessName: string;
  email: string;
  expiresAt: string;
}

export interface MerchantSessionApiResponse {
  access_token: string;
  token_type: string;
  expires_at: string;
  merchant: {
    id: number;
    merchant_code: string;
    business_name: string;
    account_number: string;
    email: string;
    status: string;
  };
}

export interface MerchantDashboardSummary {
  currency: string;
  availableBalance: number;
  ledgerBalance: number;
  lockedBalance: number;
  transactionValueToday: number;
  transactionCountToday: number;
  pendingTransactionCount: number;
  settlementAccount: string;
}

export type PortalTransactionStatus = 'INITIATED' | 'PENDING' | 'SUCCESSFUL' | 'FAILED';
export type PortalTransactionType = 'QR_COLLECTION' | 'POS_COLLECTION' | 'SETTLEMENT' | 'REVERSAL' | 'ADJUSTMENT';

export interface PortalTransaction {
  id: number;
  transactionNo: string;
  reference: string | null;
  type: PortalTransactionType;
  amount: number;
  currency: string;
  status: PortalTransactionStatus;
  narration: string | null;
  transactionDate: string;
  posted: boolean;
  isReversed: boolean;
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

export interface MerchantDashboardData {
  summary: MerchantDashboardSummary;
  recentTransactions: PortalTransaction[];
}

/** Exact record shape expected from merchant transaction APIs. */
export interface MerchantTransactionApiRecord {
  id: number;
  transaction_no: string;
  transaction_type: PortalTransactionType;
  status: PortalTransactionStatus;
  amount: string | number;
  currency: string;
  reference: string | null;
  narration: string | null;
  transaction_date: string;
  posted: boolean;
  is_reversed: boolean;
}

export interface MerchantDashboardApiResponse {
  balance: {
    currency: string;
    ledger_balance: string | number;
    available_balance: string | number;
    locked_balance: string | number;
  };
  today: {
    successful_count: number;
    successful_value: string | number;
    pending_count: number;
  };
  settlement_account: string;
  recent_transactions: MerchantTransactionApiRecord[];
}

export interface LaravelPaginator<T> {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
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
  status: string;
  settlementBank: string;
  settlementAccountName: string;
  settlementAccountNumber: string;
}
