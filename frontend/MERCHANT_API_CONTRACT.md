# Merchant Portal API Contract

This document is the frontend/backend boundary for the existing-customer
merchant flow. All HTTP fields use `snake_case`. The merchant-facing API must
resolve the merchant from the authenticated session; the browser must not be
trusted to choose a merchant ID.

## Phase 1: access, verification, registration, and profile

Frontend route flow:

```text
/login/merchants
  -> /login/merchants/account
  -> /login/merchants/verify
  -> /login/merchants/otp
  -> /reg/merchant
  -> /merchant/dashboard
```

The frontend persists incomplete onboarding data in `sessionStorage` and the
authenticated merchant session in `localStorage`. Routes below `/merchant` are
guarded. The current Basic Auth credential is strictly a local-development
bridge and must be replaced by the merchant token/session contract below.

### Integration status

| Capability | Backend status | Frontend status |
| --- | --- | --- |
| Existing account lookup | Available | Live |
| Account review | No additional API | Complete |
| Send/verify OTP | Required | Mocked with `0000` |
| Confirm registration | Staff-scoped endpoint exists | Wired but feature-disabled |
| Merchant session | Required | Local preview session |
| Merchant self-profile | Required | Mocked |
| Logout/revoke session | Required | Local logout |

Each live capability is controlled by `merchantPortalLiveFeatures` in
`src/environments/environment.ts`. This prevents an incomplete backend endpoint
from breaking the rest of the test flow.

## Available backend API

All currently available routes are under `/api/v1` and use
`auth.basic.once`. They also require staff permissions, so they are not yet a
production merchant authentication model.

### Look up an existing MicroBiz account

`POST /api/v1/reg/merchant/preview`

Request:

```json
{
  "account_number": "000000002"
}
```

Successful `data` fields:

```text
fincore_client_id, account_no, external_id, display_name, mobile_no,
email, office_name, legal_form, status, active
```

The frontend uses this response only for review. Registration must re-fetch the
Fincore customer server-side using `fincore_client_id`, as the current
controller already does.

### Confirm merchant registration

`POST /api/v1/reg/merchant`

Current required request fields:

```text
fincore_client_id, legal_name, registration_number, contact_name, phone,
branch_id
```

Optional fields currently used by the frontend:

```text
trading_name, business_type, email
```

The successful response is expected to include:

```json
{
  "success": true,
  "data": {
    "id": 15,
    "merchant_code": "MCH-ABC12345",
    "business_name": "Example Stores",
    "status": "DRAFT"
  }
}
```

Current blocker: `branch_id` requires a record in the local `branches` table,
but the test database has no branches. A customer should not enter an internal
database ID. The backend should map `office_name`/Fincore office ID to a local
branch, or return selectable branches with stable IDs. Registration stays
feature-disabled until this is resolved and the route is usable without the
staff-only `merchants.onboard` permission.

## Required Phase 1 backend API

These are the contracts the frontend service is prepared to consume.

### Send OTP

`POST /api/v1/reg/merchant/otp/send`

```json
{
  "fincore_client_id": 2,
  "account_number": "000000002"
}
```

```json
{
  "success": true,
  "data": {
    "challenge_id": "otp_01K...",
    "masked_phone": "******1234",
    "expires_at": "2026-08-19T15:10:00Z"
  }
}
```

The backend must look up the phone itself, rate-limit requests, expire the
challenge, and never return the OTP. Suggested errors are `404` account not
found, `422` account has no phone, and `429` rate limited.

### Verify OTP

`POST /api/v1/reg/merchant/otp/verify`

```json
{
  "challenge_id": "otp_01K...",
  "otp": "123456"
}
```

```json
{
  "success": true,
  "data": {
    "verified": true,
    "verification_token": "short_lived_signed_token"
  }
}
```

`verification_token` should be single-use, short-lived, bound to the Fincore
client, and required by registration. Use `422` for an incorrect code, `410`
for an expired challenge, and `429` after too many attempts.

### Create or restore a merchant session

After registration, or for an already registered account, the backend should
return a merchant-scoped access token/session:

`POST /api/v1/merchant/session`

```json
{
  "verification_token": "short_lived_signed_token"
}
```

```json
{
  "success": true,
  "data": {
    "access_token": "token",
    "token_type": "Bearer",
    "expires_at": "2026-08-20T15:00:00Z",
    "merchant": {
      "id": 15,
      "merchant_code": "MCH-ABC12345",
      "business_name": "Example Stores",
      "account_number": "000000002",
      "email": "owner@example.com",
      "status": "ACTIVE"
    }
  }
}
```

The token must be scoped to that merchant. Return `401` for invalid or expired
credentials and `403` when the merchant is not permitted to access the portal.

### Merchant self-profile

- `GET /api/v1/merchant/profile`
- `PATCH /api/v1/merchant/profile`

The singular path is intentional: the backend derives the merchant from the
Bearer token. Do not expose `/merchants/{merchant}` to merchant users because a
client-supplied ID creates an avoidable cross-merchant authorization risk.

PATCH request:

```json
{
  "contact_name": "Joshua Adeyemi",
  "email": "owner@example.com",
  "phone": "+2348035550142"
}
```

Both GET and PATCH should return the same merchant profile shape, including the
linked `customer_account.account_no` and balance when available. Validation
errors should use Laravel's standard `422` error bag.

### End merchant session

`POST /api/v1/merchant/session/logout`

Revoke the current token and return `204 No Content`. The frontend clears local
session state even if revocation fails, so an offline logout still signs the
user out locally.

## Existing staff APIs kept separate

The following routes are useful to operations staff but must not be used as
merchant self-service routes:

```text
GET   /api/v1/merchants/{merchant}
PATCH /api/v1/merchants/{merchant}
GET   /api/v1/merchants/{merchant}/transactions
```

Phase 2 will define the merchant-scoped dashboard, transaction, and transaction
history endpoints after Phase 1 contracts are confirmed.
