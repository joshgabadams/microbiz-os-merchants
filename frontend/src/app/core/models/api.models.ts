export interface BranchSummary {
  id: number;
  name: string;
  code: string;
}

export interface GlAccountSummary {
  id: number;
  name: string;
  gl_code: string;
  type: string;
  usage: string;
}

export interface BalanceSummary {
  id: number;
  currency: string;
  ledger_balance: string;
  available_balance: string;
  locked_balance: string;
}

export interface Vault {
  id: number;
  branch_id: number;
  gl_account_id: number;
  code: string;
  name: string;
  type: string;
  currency: string;
  minimum_balance: string;
  maximum_balance: string;
  active: boolean;
  branch?: BranchSummary;
  glAccount?: GlAccountSummary;
  balance?: BalanceSummary;
}

export interface TellerModel {
  id: number;
  branch_id: number;
  vault_id: number | null;
  gl_account_id: number | null;
  user_id: number | null;
  teller_code: string;
  staff_code: string | null;
  display_name: string;
  daily_limit: string;
  opening_cash_limit: string;
  minimum_cash: string;
  maximum_cash: string;
  active: boolean;
  status: 'OPEN' | 'CLOSED' | 'SUSPENDED';
  branch?: BranchSummary;
  vault?: Vault;
  glAccount?: GlAccountSummary;
  balance?: BalanceSummary;
}

export interface ApprovalRequestModel {
  id: number;
  request_no: string;
  request_type: 'ALLOCATE_FLOAT' | 'RETURN_FLOAT';
  payload: Record<string, unknown>;
  amount: string;
  currency: string;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  maker_id: number;
  checker_id: number | null;
  approved_at: string | null;
  rejected_at: string | null;
  maker_note: string | null;
  checker_note: string | null;
  created_at: string;
}

export type MerchantStatus =
  | 'DRAFT'
  | 'PENDING_KYC'
  | 'PENDING_REVIEW'
  | 'APPROVED'
  | 'ACTIVE'
  | 'REJECTED'
  | 'SUSPENDED'
  | 'RESTRICTED'
  | 'DEACTIVATED'
  | 'CLOSED';

export interface Merchant {
  id: number;
  merchant_code: string;
  business_name: string;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  branch_id: number | null;
  customer_account_id: number | null;
  status: MerchantStatus;
  onboarded_by: number | null;
  submitted_at: string | null;
  approved_by: number | null;
  approved_at: string | null;
  rejection_reason: string | null;
  activated_at: string | null;
  balance?: {
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
    currency: string;
  };
}

export type AgentStatus =
  | 'PROSPECT'
  | 'DRAFT'
  | 'PENDING_KYC'
  | 'PENDING_LOCATION_VERIFICATION'
  | 'PENDING_COMPLIANCE_REVIEW'
  | 'PENDING_APPROVAL'
  | 'APPROVED'
  | 'AGREEMENT_PENDING'
  | 'TRAINING_PENDING'
  | 'TERMINAL_PENDING'
  | 'ACTIVE'
  | 'REJECTED'
  | 'RESTRICTED'
  | 'SUSPENDED'
  | 'DORMANT'
  | 'TERMINATED'
  | 'EXPIRED'
  | 'BLACKLISTED';

export type AgentType = 'INDIVIDUAL' | 'BUSINESS' | 'CORPORATE';

export interface Agent {
  id: number;
  agent_code: string;
  agent_type: AgentType;
  legal_name: string;
  trading_name: string | null;
  registration_number: string | null;
  tax_identification_number: string | null;
  phone: string;
  email: string | null;
  branch_id: number;
  supervisor_id: number | null;
  status: AgentStatus;
  kyc_status: string;
  risk_rating: string;
  exclusive_relationship: boolean;
  principal_reference: string | null;
  daily_transaction_limit: string | null;
  daily_cash_out_limit: string | null;
  single_transaction_limit: string | null;
  next_review_date: string | null;
  created_by: number;
  approved_by: number | null;
  approved_at: string | null;
  activated_at: string | null;
  suspended_at: string | null;
  suspension_reason: string | null;
}

export interface Wallet {
  id: number;
  wallet_no: string;
  owner_name: string;
  phone: string;
  kyc_tier: number;
  status: 'ACTIVE' | 'SUSPENDED' | 'DEACTIVATED';
  balance?: {
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
    currency: string;
  };
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  data: {
    user: AuthUser;
    token: string;
    token_type: string;
  };
}
