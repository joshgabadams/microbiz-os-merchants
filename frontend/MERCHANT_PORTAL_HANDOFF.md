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
/reg/merchant
/merchant/profile
```

Implemented login role switch, real Fineract-backed account preview, account
review, mocked OTP (`0000`), backend-aligned registration fields, onboarding
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
registration: false
merchantProfile: false
merchantSession: false
merchantDashboard: false
merchantTransactions: false
merchantCollections: false
merchantSettlements: false
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
8. Complete registration and test dashboard, transactions, collections,
   settlements, profile, and logout.
9. Run `npm run build` before committing.

The production build is currently passing. Existing `NG8102` warnings come
from `tessa-dashboard.component.ts` and are unrelated to the merchant portal.

## Known backend blockers

- OTP send/verify and a single-use verification token are not implemented.
- Registration is staff-scoped and requires a local `branch_id`; local branch
  mapping from the Fineract office is unresolved.
- A production merchant Bearer-token session is not implemented.
- Merchant self-profile, dashboard, transactions, export, collections, and
  settlements endpoints are not implemented.
- The current money services expect `performed_by -> users.id`; the backend must
  map an authenticated merchant principal to a valid audit actor.
- The backend team must confirm whether returning merchants authenticate with
  account number plus OTP or with separately created email/password credentials.

## Recommended next phase

Phase 4 should cover merchant business operations that already have partial
backend models/services:

```text
Locations and branches
Terminals/devices and activation status
Notifications and activity alerts
Support/complaints
Security settings and credential recovery
```

Before implementing Phase 4, audit current Laravel routes, requests, models,
permissions, and response fields. Continue the same pattern: define the
merchant-scoped contract, add typed service methods and feature switches, build
responsive UI states, run the production build, update this handoff, and commit
only phase-specific files.
