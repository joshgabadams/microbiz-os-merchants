# Merchant Portal Implementation Handoff

This is the continuation guide for a developer or AI taking over the merchant
frontend. Read this file first, then read `MERCHANT_API_CONTRACT.md` for exact
backend payloads and responses.

## Repository and branch

- Repository: `A4GTHUB/microbiz-os`
- Working branch: `josh-frontend`
- Frontend: Angular 22 in `frontend/`
- Backend: Laravel in the repository root
- Local frontend: `http://127.0.0.1:4200`
- Local backend: `http://127.0.0.1:8000`
- Angular `/api` requests proxy to Laravel through `frontend/proxy.conf.json`

Do not commit `.env` or real Fineract credentials. There may be unrelated local
changes in `.env.example`, Composer lockfiles, or npm lockfiles; inspect the
worktree and stage only files belonging to the current phase.

## Completed merchant phases

### Phase 1: access and onboarding

Routes:

```text
/login/merchants
/login/merchants/account
/login/merchants/verify
/login/merchants/otp
/login/merchants/accounts
/reg/merchant
/merchant/profile
```

Implemented login role switch, real Fineract-backed account preview, account
review, mocked OTP (`0000`), eligible account/product confirmation,
backend-aligned registration fields, onboarding
state machine, merchant session guard, profile, and logout.

Commit: `26d2864 feat: complete merchant portal phase one flow`

### Phase 2: dashboard and transaction history

Routes:

```text
/merchant/dashboard
/merchant/transactions
```

Implemented balance/daily summaries, recent transactions, real backend schema
mapping, filters, search, date ranges, pagination, detail expansion, posting and
reversal states, filtered CSV export, and merchant Bearer-token interception.

Commit: `e59864f feat: build merchant dashboard and transaction history`

### Phase 3: collections and settlements

Routes:

```text
/merchant/collections
/merchant/settlements
```

Implemented QR/POS collection forms, confirmation, receipts, idempotency keys,
mock balance mutation, settlement summary, manual settlement confirmation,
history, filters, pagination, and details.

Commit: `369c488 feat: add merchant collections and settlements`

### Phase 4: business operations and account settings

Routes:

```text
/merchant/business
/merchant/support
/merchant/settings
```

Implemented merchant location creation, terminal inventory and device-request
flow, support ticket creation/tracking, notification inbox and preferences,
password change, security summary, and MFA setup/confirm/disable. All features
have responsive loading, empty, success, validation, and error states. Terminal
activation and suspension remain staff-only operations.

### Phase 5: reporting and operating control

Routes:

```text
/merchant/reports
/merchant/operations
```

Implemented date-filtered analytics, CSS-native sales/channel/location charts,
statement preview and CSV/PDF download flow, reconciliation summaries,
transaction disputes, and role-based merchant team management. Live APIs are
feature-disabled; typed mocks allow complete UI testing while the backend
implements the Phase 5 contract.

The Payment Tools UI and typed service contracts remain in source for a future
release, but merchant navigation is hidden and `/merchant/payment-tools`
redirects to the dashboard. Its backend endpoints are not required for the
current merchant release.

Mobile responsiveness has been finalized across the merchant shell, onboarding,
forms, filters, tables, modals, actions, and Tessa. Tables scroll horizontally
where their data cannot safely collapse, and form controls retain mobile-safe
font sizing.

## Architecture and important files

| File | Responsibility |
| --- | --- |
| `src/app/core/merchant-portal-api.service.ts` | Only boundary for live/mock merchant APIs and DTO mapping |
| `src/app/core/merchant-portal-session.service.ts` | Onboarding state and merchant session persistence |
| `src/app/core/auth.interceptor.ts` | Bearer token for singular `/api/v1/merchant/*`; development Basic Auth elsewhere |
| `src/app/core/models/merchant-portal.models.ts` | HTTP DTOs and frontend presentation models |
| `src/environments/environment.ts` | Per-capability live/mock switches |
| `src/app/features/merchant-portal/` | Merchant pages and shell |
| `MERCHANT_API_CONTRACT.md` | Required backend contracts and acceptance criteria |

Rules to preserve:

- HTTP payloads and response DTOs use backend `snake_case`.
- Presentation models may use camelCase after mapping in the API service.
- Merchant self-service URLs use singular `/api/v1/merchant/*` and derive the
  merchant from the Bearer token. Never send `merchant_id` from these pages.
- Staff URLs use plural `/api/v1/merchants/*` and must remain separate.
- Keep unavailable APIs behind feature switches. Do not scatter environment
  checks through components.
- Do not call Fineract directly from Angular. Laravel owns Fineract credentials,
  tenant headers, validation, and synchronization.
- Money-moving requests require database-backed idempotency and a confirmation
  UI before submission.

## Current feature switches

`src/environments/environment.ts` currently uses live mode, but only account
preview is enabled:

```text
accountPreview: true
otp: false
accountEligibility: false
registration: false
merchantProfile: false
merchantSession: false
merchantDashboard: false
merchantTransactions: false
merchantCollections: false
merchantSettlements: false
merchantBusiness: false
merchantSupport: false
merchantSettings: false
merchantReports: false
merchantPaymentTools: false
merchantOperations: false
tessaAssistant: false
```

Enable a switch only after its endpoint matches `MERCHANT_API_CONTRACT.md` and
has been tested through the Angular proxy. A switch should be enabled and
verified in its own small commit.

## Local verification flow

1. Run Laravel: `php artisan serve` from the repository root.
2. Run Angular: `npm start` from `frontend/`.
3. Open `http://127.0.0.1:4200/login/merchants`.
4. Use the development credentials prefilled by the login component.
5. Use MicroBiz account `000000002`.
6. Confirm the returned Fineract customer (`CHiksaa`, client ID `2`).
7. Use OTP `0000` while the OTP feature remains mocked.
8. Confirm the eligible merchant account/product.
9. Complete registration and test dashboard, transactions, collections,
   settlements, reports, payment tools, reconciliation, disputes, team access,
   locations/devices, support, settings, profile, and logout.
10. Run `npm run build` before committing.

The production build is currently passing. Existing `NG8102` warnings come
from `tessa-dashboard.component.ts` and are unrelated to the merchant portal.

## Tessa floating assistant

The merchant shell mounts a reusable Tessa widget from `src/tessa/`. The folder
contains its component, typed service boundary, models, and transparent avatar
asset. Conversations use deterministic mocks, are capped at 30 messages, and
are stored in `sessionStorage` under a merchant-scoped key. Enable
`tessaAssistant` only when the read-only endpoint and safety requirements in
`MERCHANT_API_CONTRACT.md` are implemented.

## Known backend blockers

- OTP send/verify and a single-use verification token are not implemented.
- Verified-client eligible account/product lookup is not implemented.
- Registration is staff-scoped and requires a local `branch_id`; local branch
  mapping from the Fineract office is unresolved.
- A production merchant Bearer-token session is not implemented.
- Merchant self-profile, dashboard, transactions, export, collections, and
  settlements endpoints are not implemented.
- Merchant-scoped locations, terminal requests, support, notifications,
  notification preferences, password change, and MFA endpoints are not
  implemented.
- Merchant analytics, statements/export, payment assets, reconciliation,
  disputes, and team APIs are not implemented.
- The merchant-scoped Tessa messages endpoint is not implemented.
- The current money services expect `performed_by -> users.id`; the backend must
  map an authenticated merchant principal to a valid audit actor.
- The backend team must confirm whether returning merchants authenticate with
  account number plus OTP or with separately created email/password credentials.

## Recommended next phase

Phase 6 should prioritize backend integration and production hardening rather
than adding more merchant screens. Implement the singular merchant session and
Phase 1 endpoints first, then enable and test one feature switch at a time.
Add automated route/component tests, accessibility checks, permission-matrix
tests, and end-to-end coverage for money-moving and cross-merchant isolation.
