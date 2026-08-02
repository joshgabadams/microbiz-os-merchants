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

export interface Merchant {
  id: number;
  merchant_code: string;
  business_name: string;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  branch_id: number | null;
  customer_account_id: number | null;
  status: 'ACTIVE' | 'SUSPENDED' | 'DEACTIVATED';
  balance?: {
    ledger_balance: string;
    available_balance: string;
    locked_balance: string;
    currency: string;
  };
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
