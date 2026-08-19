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
