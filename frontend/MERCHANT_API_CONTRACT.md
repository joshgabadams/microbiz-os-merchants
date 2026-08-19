# Merchant Portal API Contract

This file records the frontend/backend boundary for the existing-customer
merchant registration flow. JSON bodies, response fields, and query parameters
use Laravel's `snake_case` names. Components may use presentation-specific
names internally, but HTTP DTOs must not rename backend fields.

## Implemented backend endpoints

All current endpoints are under `/api/v1` and currently inherit
`auth.basic.once` from the route group.

### Look up an existing MicroBiz account

`POST /api/v1/reg/merchant/preview`

```json
{
  "account_number": "2048910037"
}
```

The successful `data` object is represented by `MerchantAccountPreview` and
contains:

```text
fincore_client_id, account_no, external_id, display_name, mobile_no,
email, office_name, legal_form, status, active
```

The frontend uses this response on the account-verification screen. It never
uses returned customer data as authority when registration is confirmed; the
backend re-fetches the Fincore client by `fincore_client_id`.

### Confirm merchant registration

`POST /api/v1/reg/merchant`

The request is represented by `ConfirmMerchantRegistrationPayload`. Required
fields currently include:

```text
fincore_client_id, legal_name, registration_number, contact_name, phone,
branch_id
```

`fincore_client_id` identifies the external Fincore customer. It is not the
local `merchant.id`. The successful registration response must supply the local
merchant record before profile and transaction endpoints can be called.

### Merchant profile

- `GET /api/v1/merchants/{merchant}`
- `PATCH /api/v1/merchants/{merchant}`

The route parameter is the local `merchant.id`. The consolidated GET response
includes balance, customer account, owners, documents, locations, and terminals.

### Merchant transactions and history

`GET /api/v1/merchants/{merchant}/transactions`

Supported query names:

```text
status, transaction_type, from, to, per_page, page
```

The endpoint returns Laravel's standard paginator shape.

## Pending backend contracts

The frontend currently provides preview implementations for these steps:

- Send OTP to the phone linked to the account.
- Verify OTP. The preview code is `0000`.
- Create/restore/end a merchant user session.
- Resolve the authenticated session to the local `merchant.id`.
- Finalize the merchant registration fields that should be entered by the
  customer versus derived from Fincore.

Proposed names for discussion—not implemented backend contracts:

```text
POST /api/v1/reg/merchant/otp/send
POST /api/v1/reg/merchant/otp/verify
```

## Frontend API mode

`src/environments/environment.ts` contains `merchantPortalApiMode`:

- `mock`: local account, OTP, dashboard, profile, and transaction fixtures.
- `live`: account preview calls `/api/v1/reg/merchant/preview`.

Keep `mock` as the default until merchant authentication and OTP endpoints are
available. Do not add endpoint-specific conditionals to components; all live
and preview behavior belongs in `MerchantPortalApiService`.
