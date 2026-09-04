import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';
import { merchantPortalGuard } from './core/merchant-portal.guard';

export const routes: Routes = [
  {
    path: 'login/merchants/verify',
    loadComponent: () =>
      import('./features/merchant-portal/account-verification.component').then(
        (m) => m.AccountVerificationComponent
      ),
  },
  {
    path: 'login/merchants/otp',
    loadComponent: () =>
      import('./features/merchant-portal/otp-verification.component').then(
        (m) => m.OtpVerificationComponent
      ),
  },
  {
    path: 'login/merchants/accounts',
    loadComponent: () =>
      import('./features/merchant-portal/merchant-account-selection.component').then(
        (m) => m.MerchantAccountSelectionComponent
      ),
  },
  {
    path: 'login/merchants/account',
    loadComponent: () =>
      import('./features/merchant-portal/account-lookup.component').then(
        (m) => m.AccountLookupComponent
      ),
  },
  {
    path: 'login/merchants',
    loadComponent: () =>
      import('./features/merchant-portal/portal-login.component').then(
        (m) => m.PortalLoginComponent
      ),
  },
  {
    path: 'reg/merchant',
    loadComponent: () =>
      import('./features/merchant-portal/merchant-registration.component').then(
        (m) => m.MerchantRegistrationComponent
      ),
  },
  {
    path: 'merchant',
    loadComponent: () =>
      import('./features/merchant-portal/merchant-shell.component').then(
        (m) => m.MerchantShellComponent
      ),
    canActivate: [merchantPortalGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-dashboard.component').then(
            (m) => m.MerchantDashboardComponent
          ),
      },
      {
        path: 'transactions',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-transactions.component').then(
            (m) => m.MerchantTransactionsComponent
          ),
      },
      {
        path: 'collections',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-collections.component').then(
            (m) => m.MerchantCollectionsComponent
          ),
      },
      {
        path: 'settlements',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-settlements.component').then(
            (m) => m.MerchantSettlementsComponent
          ),
      },
      {
        path: 'business',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-business.component').then(
            (m) => m.MerchantBusinessComponent
          ),
      },
      {
        path: 'support',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-support.component').then(
            (m) => m.MerchantSupportComponent
          ),
      },
      {
        path: 'settings',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-settings.component').then(
            (m) => m.MerchantSettingsComponent
          ),
      },
      {
        path: 'reports',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-reports.component').then(
            (m) => m.MerchantReportsComponent
          ),
      },
      {
        path: 'payment-tools',
        redirectTo: 'dashboard',
        pathMatch: 'full',
      },
      {
        path: 'operations',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-operations.component').then(
            (m) => m.MerchantOperationsComponent
          ),
      },
      {
        path: 'profile',
        loadComponent: () =>
          import('./features/merchant-portal/merchant-profile.component').then(
            (m) => m.MerchantProfileComponent
          ),
      },
    ],
  },
  {
    path: 'login',
    loadComponent: () =>
      import('./features/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    loadComponent: () =>
      import('./features/dashboard/dashboard.component').then((m) => m.DashboardComponent),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'merchants', pathMatch: 'full' },
      {
        path: 'agents',
        loadComponent: () =>
          import('./features/agents/agent-list.component').then(
            (m) => m.AgentListComponent
          ),
      },
      {
        path: 'agents/:id',
        loadComponent: () =>
          import('./features/agents/agent-detail.component').then(
            (m) => m.AgentDetailComponent
         ),
      },  

      {
        path: 'merchants',
        loadComponent: () =>
          import('./features/merchants/merchant-list.component').then(
            (m) => m.MerchantListComponent
          ),
      },
      {
        path: 'wallets',
        loadComponent: () =>
          import('./features/wallets/wallet-list.component').then(
            (m) => m.WalletListComponent
          ),
      },
      {
        path: 'vaults',
        loadComponent: () =>
          import('./features/vaults/vault-list.component').then(
            (m) => m.VaultListComponent
          ),
      },
      {
        path: 'tellers',
        loadComponent: () =>
          import('./features/tellers/teller-list.component').then(
            (m) => m.TellerListComponent
          ),
      },
      {
        path: 'approvals',
        loadComponent: () =>
          import('./features/approvals/approval-list.component').then(
            (m) => m.ApprovalListComponent
          ),
      },
      {
        path: 'float-transfer',
        loadComponent: () =>
          import('./features/float-transfer/float-transfer.component').then(
            (m) => m.FloatTransferComponent
          ),
      },
      {
        path: 'customer-cash',
        loadComponent: () =>
          import('./features/customer-cash/customer-cash.component').then(
            (m) => m.CustomerCashComponent
          ),
      },
      {
        path: 'balancing',
        loadComponent: () =>
          import('./features/balancing/balancing.component').then(
            (m) => m.BalancingComponent
          ),
      },
      {
        path: 'branch-eod',
        loadComponent: () =>
          import('./features/branch-eod/branch-eod.component').then(
            (m) => m.BranchEodComponent
          ),
      },
      {
        path: 'call-over-report',
        loadComponent: () =>
          import('./features/call-over-report/call-over-report.component').then(
            (m) => m.CallOverReportComponent
          ),
      },
      {
        path: 'ledger-statement',
        loadComponent: () =>
          import('./features/ledger-statement/ledger-statement.component').then(
            (m) => m.LedgerStatementComponent
          ),
      },
      {
        path: 'terminal-callup',
        loadComponent: () =>
          import('./features/terminal-callup/terminal-callup.component').then(
            (m) => m.TerminalCallupComponent
          ),
      },
      {
        path: 'fixed-deposits',
        loadComponent: () =>
          import('./features/fixed-deposits/fixed-deposit-list.component').then(
            (m) => m.FixedDepositListComponent
          ),
      },
      {
        path: 'tessa',
        loadComponent: () =>
          import('./features/tessa/tessa-dashboard.component').then(
            (m) => m.TessaDashboardComponent
          ),
      },
      {
        path: 'complaints',
        loadComponent: () =>
          import('./features/complaints/complaint-list.component').then(
            (m) => m.ComplaintListComponent
          ),
      },
      {
        path: 'inspections',
        loadComponent: () =>
          import('./features/inspections/inspection-list.component').then(
            (m) => m.InspectionListComponent
          ),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
