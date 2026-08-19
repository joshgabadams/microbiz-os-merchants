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

export interface MerchantEligibleAccount {
  fincore_account_id: number;
  account_no: string;
  product_id: number;
  product_name: string;
  currency: string;
  status: string;
  balance: string | number | null;
}

/** Exact payload naming for POST /api/v1/reg/merchant. */
export interface ConfirmMerchantRegistrationPayload {
  fincore_client_id: number;
  fincore_account_id: number;
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

export type PortalTransactionStatus = 'INITIATED' | 'PROCESSING' | 'PENDING' | 'SUCCESSFUL' | 'FAILED';
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
  direction?: 'CREDIT' | 'DEBIT';
  channel?: string | null;
  providerReference?: string | null;
  counterpartyName?: string | null;
  counterpartyAccount?: string | null;
  bankName?: string | null;
  terminalId?: string | null;
  locationName?: string | null;
  completedAt?: string | null;
  reversalReference?: string | null;
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
  direction?: 'CREDIT' | 'DEBIT';
  channel?: string | null;
  provider_reference?: string | null;
  counterparty_name?: string | null;
  counterparty_account?: string | null;
  bank_name?: string | null;
  terminal_id?: string | null;
  location_name?: string | null;
  completed_at?: string | null;
  reversal_reference?: string | null;
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

export interface MerchantMoneyRequest {
  amount: string;
  idempotency_key: string;
  reference?: string;
  narration?: string;
}

export interface MerchantBalanceApiRecord {
  currency: string;
  ledger_balance: string | number;
  available_balance: string | number;
  locked_balance: string | number;
}

export interface MerchantCollectionApiResponse {
  transaction: MerchantTransactionApiRecord;
  balance: MerchantBalanceApiRecord;
}

export interface MerchantSettlementApiResponse {
  merchant_transaction: MerchantTransactionApiRecord;
  merchant_balance: MerchantBalanceApiRecord;
}

export interface MerchantMoneyOperationResult {
  transaction: PortalTransaction;
  balance: {
    currency: string;
    ledgerBalance: number;
    availableBalance: number;
    lockedBalance: number;
  };
}

export interface MerchantSettlementSummary {
  currency: string;
  ledgerBalance: number;
  lockedBalance: number;
  availableToSettle: number;
  settlementAccount: string;
  settlementFrequency: string;
}

export interface MerchantSettlementSummaryApiResponse {
  balance: MerchantBalanceApiRecord;
  available_to_settle: string | number;
  settlement_account: string;
  settlement_frequency: string;
}

export interface MerchantLocationApiRecord {
  id: number;
  location_code: string;
  name: string;
  trading_name: string | null;
  address: string;
  state: string | null;
  local_government: string | null;
  contact_person: string | null;
  operating_hours: string | null;
  status: string;
}

export interface MerchantLocation {
  id: number;
  locationCode: string;
  name: string;
  tradingName: string;
  address: string;
  state: string;
  localGovernment: string;
  contactPerson: string;
  operatingHours: string;
  status: string;
}

export interface CreateMerchantLocationRequest {
  name: string;
  trading_name?: string;
  address: string;
  state?: string;
  local_government?: string;
  contact_person?: string;
  operating_hours?: string;
}

export interface MerchantTerminalApiRecord {
  id: number;
  merchant_location_id: number | null;
  terminal_id: string;
  serial_number: string;
  terminal_type: string;
  provider: string | null;
  model: string | null;
  status: string;
  application_version: string | null;
  activated_at: string | null;
  last_heartbeat_at: string | null;
  last_transaction_at: string | null;
}

export interface MerchantTerminal {
  id: number;
  locationId: number | null;
  terminalId: string;
  serialNumber: string;
  terminalType: string;
  provider: string;
  model: string;
  status: string;
  applicationVersion: string;
  activatedAt: string | null;
  lastHeartbeatAt: string | null;
  lastTransactionAt: string | null;
}

export interface MerchantTerminalRequest {
  terminal_type: string;
  merchant_location_id?: number;
  quantity: number;
  notes?: string;
}

export interface MerchantSupportTicket {
  id: number;
  ticketNo: string;
  category: string;
  subject: string;
  description: string;
  priority: string;
  status: string;
  createdAt: string;
  updatedAt: string;
}

export interface MerchantSupportTicketApiRecord {
  id: number;
  ticket_no: string;
  category: string;
  subject: string;
  description: string;
  priority: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface CreateMerchantSupportTicketRequest {
  category: string;
  subject: string;
  description: string;
  priority: string;
}

export interface MerchantNotification {
  id: number;
  title: string;
  message: string;
  type: 'PAYMENT' | 'SETTLEMENT' | 'SECURITY' | 'SYSTEM';
  read: boolean;
  createdAt: string;
}

export interface MerchantNotificationApiRecord {
  id: number;
  title: string;
  message: string;
  type: MerchantNotification['type'];
  read: boolean;
  created_at: string;
}

export interface MerchantNotificationPreferencesApiResponse {
  email: boolean;
  sms: boolean;
  push: boolean;
  whatsapp: boolean;
}

export interface MerchantSecuritySummaryApiResponse {
  mfa_enabled: boolean;
  last_password_change: string | null;
  active_session_count: number;
}

export interface MerchantMfaSetup {
  setupId: string;
  maskedDestination: string;
}

export interface MerchantMfaSetupApiResponse {
  setup_id: string;
  masked_destination: string;
}

export interface MerchantNotificationPreferences {
  email: boolean;
  sms: boolean;
  push: boolean;
  whatsapp: boolean;
}

export interface MerchantSecuritySummary {
  mfaEnabled: boolean;
  lastPasswordChange: string | null;
  activeSessionCount: number;
}

export interface MerchantAnalytics {
  currency: string;
  totalValue: number;
  totalCount: number;
  averageValue: number;
  successRate: number;
  changePercent: number;
  daily: { label: string; value: number; count: number }[];
  channels: { name: string; value: number; percent: number }[];
  locations: { name: string; value: number; count: number }[];
}

export interface MerchantAnalyticsApiResponse {
  currency: string;
  total_value: string | number;
  total_count: number;
  average_value: string | number;
  success_rate: string | number;
  change_percent: string | number;
  daily: { label: string; value: string | number; count: number }[];
  channels: { name: string; value: string | number; percent: string | number }[];
  locations: { name: string; value: string | number; count: number }[];
}

export interface MerchantStatement {
  statementNo: string;
  accountNumber: string;
  businessName: string;
  currency: string;
  from: string;
  to: string;
  openingBalance: number;
  totalCredits: number;
  totalDebits: number;
  closingBalance: number;
  entries: PortalTransaction[];
}

export interface MerchantStatementApiResponse {
  statement_no: string;
  account_number: string;
  business_name: string;
  currency: string;
  from: string;
  to: string;
  opening_balance: string | number;
  total_credits: string | number;
  total_debits: string | number;
  closing_balance: string | number;
  entries: MerchantTransactionApiRecord[];
}

export interface MerchantPaymentAsset {
  id: number;
  type: 'PAYMENT_LINK' | 'QR_CODE';
  name: string;
  slug: string;
  paymentUrl: string;
  amount: number | null;
  currency: string;
  status: 'ACTIVE' | 'INACTIVE';
  paymentCount: number;
  totalValue: number;
  createdAt: string;
}

export interface MerchantPaymentAssetApiRecord {
  id: number;
  type: MerchantPaymentAsset['type'];
  name: string;
  slug: string;
  payment_url: string;
  amount: string | number | null;
  currency: string;
  status: MerchantPaymentAsset['status'];
  payment_count: number;
  total_value: string | number;
  created_at: string;
}

export interface CreateMerchantPaymentAssetRequest {
  type: MerchantPaymentAsset['type'];
  name: string;
  amount?: string;
  description?: string;
}

export interface MerchantReconciliationSummary {
  currency: string;
  period: string;
  expectedValue: number;
  settledValue: number;
  variance: number;
  unmatchedCount: number;
  lastReconciledAt: string | null;
}

export interface MerchantReconciliationSummaryApiResponse {
  currency: string;
  period: string;
  expected_value: string | number;
  settled_value: string | number;
  variance: string | number;
  unmatched_count: number;
  last_reconciled_at: string | null;
}

export interface MerchantDispute {
  id: number;
  disputeNo: string;
  transactionNo: string;
  reason: string;
  description: string;
  amount: number;
  currency: string;
  status: 'OPEN' | 'UNDER_REVIEW' | 'RESOLVED' | 'REJECTED';
  createdAt: string;
  updatedAt: string;
}

export interface MerchantDisputeApiRecord {
  id: number;
  dispute_no: string;
  transaction_no: string;
  reason: string;
  description: string;
  amount: string | number;
  currency: string;
  status: MerchantDispute['status'];
  created_at: string;
  updated_at: string;
}

export interface CreateMerchantDisputeRequest {
  transaction_no: string;
  reason: string;
  description: string;
}

export interface MerchantTeamMember {
  id: number;
  name: string;
  email: string;
  role: 'OWNER' | 'ADMIN' | 'FINANCE' | 'OPERATOR' | 'VIEWER';
  status: 'ACTIVE' | 'INVITED' | 'SUSPENDED';
  lastActiveAt: string | null;
}

export interface MerchantTeamMemberApiRecord {
  id: number;
  name: string;
  email: string;
  role: MerchantTeamMember['role'];
  status: MerchantTeamMember['status'];
  last_active_at: string | null;
}

export interface InviteMerchantTeamMemberRequest {
  name: string;
  email: string;
  role: MerchantTeamMember['role'];
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
