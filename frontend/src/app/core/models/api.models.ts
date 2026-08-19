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
  training_records?: AgentTrainingRecord[];
  owners?: AgentBeneficialOwner[];
  documents?: AgentDocument[];
  locations?: AgentLocation[];
  agreements?: AgentAgreement[];
  operators?: AgentOperator[];
  terminals?: AgentTerminal[];
  transactions?: AgentTransaction[];
}
export type AgentLocationVerificationStatus =
  | 'PENDING'
  | 'VERIFIED'
  | 'REJECTED';

export interface AgentLocation {
  id: number;
  agent_id: number;
  address_line_1: string;
  address_line_2?: string | null;
  landmark?: string | null;
  city: string;
  local_government: string;
  state: string;
  latitude: string | number;
  longitude: string | number;
  approved_radius_metres: number;
  verification_status: AgentLocationVerificationStatus;
  status: string;
  created_by: number;
  verified_by?: number | null;
  verified_at?: string | null;
  verification_notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export type AgentAgreementStatus =
  | 'DRAFT'
  | 'PENDING_INTERNAL_REVIEW'
  | 'APPROVED_FOR_EXECUTION'
  | 'AWAITING_SIGNATURES'
  | 'EXECUTED'
  | 'REJECTED'
  | 'EXPIRED'
  | 'TERMINATED'
  | 'SUPERSEDED';

export interface AgentAgreementPermittedService {
  service: string;
  enabled: boolean;
  limit?: number | null;
  notes?: string | null;
}

export interface AgentAgreementCommercialTerm {
  service: string;
  customer_fee?: string | null;
  agent_commission?: string | null;
  settlement_timing?: string | null;
}

export interface AgentAgreement {
  id: number;
  agent_id: number;
  agreement_template_id?: number | null;
  version: number;
  status: AgentAgreementStatus;
  effective_date?: string | null;
  expiry_date?: string | null;
  renewal_due_date?: string | null;
  initial_term_months?: number | null;
  agent_termination_notice_days?: number | null;
  microbiz_termination_notice_days?: number | null;
  dispute_resolution_method?: string | null;
  arbitration_seat?: string | null;
  governing_law?: string | null;
  relationship_manager_id?: number | null;
  special_conditions?: string | null;
  permitted_services?: AgentAgreementPermittedService[];
  commercial_terms?: AgentAgreementCommercialTerm[];
  document_path?: string | null;
  created_by: number;
  executed_by?: number | null;
  executed_at?: string | null;
  created_at?: string;
  updated_at?: string;
  approvals?: AgentAgreementApproval[];
  signatories?: AgentAgreementSignatory[];
  template?: AgentAgreementTemplate | null;
}

export type AgentAgreementApprovalType =
  | 'RISK'
  | 'COMPLIANCE'
  | 'LEGAL'
  | 'BUSINESS_OWNER';

export interface AgentAgreementApproval {
  id: number;
  agent_agreement_id: number;
  approval_type: AgentAgreementApprovalType;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  approved_by?: number | null;
  approved_at?: string | null;
  notes?: string | null;
}

export type AgentAgreementSignatoryParty = 'MICROBIZ' | 'AGENT';

export interface AgentAgreementSignatory {
  id: number;
  agent_agreement_id: number;
  party: AgentAgreementSignatoryParty;
  signatory_name: string;
  signatory_title?: string | null;
  signature_method: 'WET_SIGNATURE_UPLOAD' | 'E_SIGNATURE';
  signature_evidence_path?: string | null;
  provider_reference_id?: string | null;
  signed_at?: string | null;
}

export interface AgentAgreementTemplate {
  id: number;
  name: string;
  version: string;
  status: string;
  governing_law?: string | null;
}

export interface AgentBeneficialOwner {
  id: number;
  agent_id: number;
  full_name: string;
  date_of_birth?: string | null;
  nationality?: string | null;
  identification_type?: string | null;
  identification_number?: string | null;
  ownership_percentage?: number | string | null;
  is_director?: boolean;
  is_pep?: boolean;
  sanctions_match?: boolean;
  screening_status?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface AgentDocument {
  id: number;
  agent_id: number;
  document_type: string;
  document_number?: string | null;
  storage_path?: string | null;
  issued_at?: string | null;
  expires_at?: string | null;
  verification_status?: string | null;
  verified_by?: number | null;
  verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
}
export interface TrainingDocument {
  id: number;
  name: string;
  version: string;
  status: string;
  file_path: string;
  created_by: number;
  created_at?: string;
  updated_at?: string;
}

export interface AgentTrainingRecord {
  id: number;
  agent_id: number;
  operator_id?: number | null;
  training_document_id: number;
  training_document_version: string;
  downloaded_at: string | null;
  acknowledged_at: string | null;
  acknowledged_by?: number | null;
  recorded_by: number;
  completion_method?: string | null;
  training_document?: TrainingDocument;
}

export interface AgentOperator {
  id: number;
  agent_id: number;
  agent_location_id: number;
  user_id: number;
  role: string;
  status: 'PENDING' | 'ACTIVE' | 'SUSPENDED';
  training_completed_at?: string | null;
  activated_at?: string | null;
  suspended_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface AgentTerminal {
  id: number;
  agent_id: number;
  agent_location_id: number;
  terminal_id: string;
  serial_number: string;
  device_model?: string | null;
  provider?: string | null;
  application_version?: string | null;
  status: 'PENDING_ACTIVATION' | 'ACTIVE' | 'SUSPENDED';
  registered_latitude: string | number;
  registered_longitude: string | number;
  geo_fence_radius_metres: number;
  activated_at?: string | null;
  last_heartbeat_at?: string | null;
  last_transaction_at?: string | null;
  geo_fence_compliant?: boolean | null;
  last_ip_address?: string | null;
  ip_city?: string | null;
  ip_state?: string | null;
  ip_country?: string | null;
  ip_location_mismatch?: boolean | null;
  ip_checked_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export type AgentTransactionType = 'CASH_IN' | 'CASH_OUT' | 'TRANSFER';

export interface AgentTransaction {
  id: number;
  transaction_no: string;
  idempotency_key: string;
  agent_id: number;
  agent_location_id?: number | null;
  agent_terminal_id: number;
  agent_operator_id: number;
  transaction_type: AgentTransactionType;
  status: string;
  amount: string | number;
  fee_amount?: string | number | null;
  commission_amount?: string | number | null;
  currency?: string;
  customer_account_id: number;
  customer_reference?: string | null;
  latitude?: string | number | null;
  longitude?: string | number | null;
  geo_fence_passed?: boolean | null;
  transaction_date?: string | null;
  created_at?: string;
  terminal?: AgentTerminal;
  operator?: AgentOperator;
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
  permissions?: string[];
  roles?: string[];
}

/**
 * agent-complaints and agent-inspections respond with Laravel's bare
 * default paginator shape (response()->json($query->paginate(...))),
 * not the {success,message,data} ApiResponse envelope used elsewhere.
 */
export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export type AgentComplaintStatus =
  | 'OPEN'
  | 'ACKNOWLEDGED'
  | 'IN_PROGRESS'
  | 'RESOLVED'
  | 'CLOSED'
  | 'ESCALATED';

export interface AgentComplaint {
  id: number;
  complaint_no: string;
  agent_id: number | null;
  branch_id: number | null;
  agent_location_id: number | null;
  agent_transaction_id: number | null;
  complainant_name: string;
  complainant_phone: string | null;
  complainant_email: string | null;
  channel: string;
  category: string;
  subject: string;
  description: string;
  disputed_amount: string | number | null;
  priority: string;
  status: AgentComplaintStatus;
  assigned_to: number | null;
  created_by: number | null;
  acknowledged_at: string | null;
  due_at: string | null;
  resolution_summary: string | null;
  resolved_at: string | null;
  closed_at: string | null;
  escalation_level: number;
  escalation_reason: string | null;
  escalated_at: string | null;
  agent?: Agent | null;
  assignedTo?: AuthUser | null;
  createdBy?: AuthUser | null;
}

export type AgentInspectionStatus =
  | 'SCHEDULED'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED';

export type AgentInspectionFollowUpStatus =
  | 'NOT_REQUIRED'
  | 'PENDING'
  | 'IN_PROGRESS'
  | 'COMPLETED';

export interface AgentInspection {
  id: number;
  inspection_no: string;
  agent_id: number;
  agent_location_id: number | null;
  inspector_id: number;
  inspection_type: string;
  inspection_date: string;
  status: AgentInspectionStatus;
  findings: string | null;
  compliance_outcome: string | null;
  corrective_action: string | null;
  corrective_action_deadline: string | null;
  follow_up_status: AgentInspectionFollowUpStatus;
  follow_up_notes: string | null;
  created_by: number | null;
  started_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  agent?: Agent | null;
  inspector?: AuthUser | null;
}

