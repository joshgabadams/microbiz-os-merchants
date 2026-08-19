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

## Phase 2: dashboard and transaction history

Phase 2 uses the local `merchant_balances` and `merchant_transactions` tables as
the source of truth. The backend should not call Fineract for every dashboard or
transaction-history request. Fineract is only needed when validating or posting
the linked customer account; MicroBiz OS owns merchant collections, settlement
state, GL posting state, and transaction history.

All Phase 2 routes require the Phase 1 merchant Bearer token. The backend must
derive the merchant from that token and must never accept a merchant ID from the
browser.

### Dashboard

`GET /api/v1/merchant/dashboard`

```json
{
  "success": true,
  "message": "Merchant dashboard retrieved successfully.",
  "data": {
    "balance": {
      "currency": "NGN",
      "ledger_balance": "2011750.00",
      "available_balance": "1842750.00",
      "locked_balance": "169000.00"
    },
    "today": {
      "successful_count": 2,
      "successful_value": "192250.00",
      "pending_count": 1
    },
    "settlement_account": "000000002",
    "recent_transactions": []
  }
}
```

`today` must be calculated using the application/business timezone and only
the authenticated merchant's transactions. `successful_value` should sum
successful collection transactions, not settlements, reversals, or failed
transactions. Return at most five recent transactions.

### Transaction list and history

`GET /api/v1/merchant/transactions`

Supported query parameters:

```text
search, status, transaction_type, from, to, page, per_page
```

- `search` matches `transaction_no`, `reference`, or `narration`.
- `status` matches the transaction lifecycle status.
- `transaction_type` supports `QR_COLLECTION`, `POS_COLLECTION`,
  `SETTLEMENT`, `REVERSAL`, and `ADJUSTMENT`.
- `from` and `to` are inclusive `YYYY-MM-DD` dates.
- `per_page` is limited to `10`, `25`, or `50`, with a server maximum of 100.
- Results are ordered by `transaction_date` descending.

Use the standard API envelope with the Laravel paginator inside `data`:

```json
{
  "success": true,
  "message": "Merchant transactions retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 41,
        "transaction_no": "MCH-Q3F8K2",
        "transaction_type": "QR_COLLECTION",
        "status": "SUCCESSFUL",
        "amount": "48500.00",
        "currency": "NGN",
        "reference": "ORDER-1048",
        "narration": "QR collection for order 1048",
        "transaction_date": "2026-08-19T10:42:00Z",
        "posted": true,
        "is_reversed": false
      }
    ],
    "last_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```

Do not expose `performed_by`, `approved_by`, idempotency keys, GL account keys,
or internal user records to merchant users.

### Transaction detail

`GET /api/v1/merchant/transactions/{transaction}`

Return the same transaction fields as the list. Route-model binding must be
scoped through the authenticated merchant's `transactions()` relationship so a
merchant cannot retrieve another merchant's transaction by changing the ID.
Return `404`, not `403`, when it is outside the authenticated merchant scope.

### Filtered CSV export

`GET /api/v1/merchant/transactions/export`

Accept the same filters as the transaction list, excluding pagination. Return
`text/csv` with a downloadable filename. The export must include all matching
transactions for the authenticated merchant, not only the visible page.

CSV columns expected by the frontend:

```text
Transaction Number, Reference, Type, Narration, Amount, Currency, Status,
Posted, Reversed, Transaction Date
```

Apply an export row limit or queue large exports. Escape spreadsheet formula
prefixes (`=`, `+`, `-`, `@`) in user-controlled reference and narration fields.

### Phase 2 performance and authorization requirements

- Index `merchant_transactions` for merchant/date, merchant/status/date, and
  merchant/type/date filter paths.
- Calculate summary aggregates in SQL rather than loading all transactions.
- Validate dates, enum filters, and pagination values and return `422` errors.
- Rate-limit CSV exports separately from normal reads.
- Every query must be scoped to the authenticated merchant before filters or
  route binding are applied.
- Preserve decimal amounts as strings in JSON; the frontend converts them only
  for display.

The existing staff route
`GET /api/v1/merchants/{merchant}/transactions` remains available to operations
staff but is not used by the merchant portal.

## Phase 3: collections and settlements

Phase 3 adds money-moving merchant self-service operations. These endpoints
must require the Phase 1 merchant Bearer token, derive the merchant and actor
server-side, enforce active merchant status, and never accept `merchant_id` or
`performed_by` from the browser.

The existing staff endpoints remain separate:

```text
POST /api/v1/merchants/collect/qr
POST /api/v1/merchants/collect/pos
POST /api/v1/merchants/settle
```

### QR and POS collection

```text
POST /api/v1/merchant/collections/qr
POST /api/v1/merchant/collections/pos
```

Request:

```json
{
  "amount": "5000.00",
  "idempotency_key": "3df628cd-05d3-4c7d-ad86-053182467296",
  "reference": "ORDER-1050",
  "narration": "Payment for order 1050"
}
```

Successful response:

```json
{
  "success": true,
  "message": "QR payment collected successfully.",
  "data": {
    "transaction": {
      "id": 51,
      "transaction_no": "MCH-ABC12345",
      "transaction_type": "QR_COLLECTION",
      "status": "SUCCESSFUL",
      "amount": "5000.00",
      "currency": "NGN",
      "reference": "ORDER-1050",
      "narration": "Payment for order 1050",
      "transaction_date": "2026-08-19T18:30:00Z",
      "posted": true,
      "is_reversed": false
    },
    "balance": {
      "currency": "NGN",
      "ledger_balance": "2016750.00",
      "available_balance": "1842750.00",
      "locked_balance": "174000.00"
    }
  }
}
```

Collection requirements:

- Keep the existing database transaction, row lock, GL posting, and domain
  event behavior in `MerchantPaymentService`.
- Scope idempotency to the authenticated merchant. Retrying the same key and
  payload must return the original successful response without posting twice.
- Reusing a key with a different amount or operation must return `409`.
- Validate decimal precision and the configured merchant transaction limits.
- Return `422` for validation/business-rule failures and `409` for duplicate
  idempotency conflicts.
- Do not expose cash-ledger internals in the merchant response.

### Settlement summary

`GET /api/v1/merchant/settlements/summary`

```json
{
  "success": true,
  "data": {
    "balance": {
      "currency": "NGN",
      "ledger_balance": "2016750.00",
      "available_balance": "1842750.00",
      "locked_balance": "174000.00"
    },
    "available_to_settle": "174000.00",
    "settlement_account": "000000002",
    "settlement_frequency": "T_PLUS_1"
  }
}
```

`available_to_settle` must be calculated server-side. In the current service it
is the merchant `locked_balance`; the frontend must not be allowed to override
it. The linked settlement account must come from `customer_account_id`.

### Request settlement

`POST /api/v1/merchant/settlements`

Request:

```json
{
  "amount": "100000.00",
  "idempotency_key": "6c9f20ad-75b8-4dad-a201-e2bc8d914d5a",
  "reference": "SETTLE-0820",
  "narration": "Manual merchant settlement"
}
```

Successful response:

```json
{
  "success": true,
  "message": "Merchant settled successfully.",
  "data": {
    "merchant_transaction": {
      "id": 52,
      "transaction_no": "MST-ABC12345",
      "transaction_type": "SETTLEMENT",
      "status": "SUCCESSFUL",
      "amount": "100000.00",
      "currency": "NGN",
      "reference": "SETTLE-0820",
      "narration": "Manual merchant settlement",
      "transaction_date": "2026-08-19T18:35:00Z",
      "posted": true,
      "is_reversed": false
    },
    "merchant_balance": {
      "currency": "NGN",
      "ledger_balance": "1916750.00",
      "available_balance": "1842750.00",
      "locked_balance": "74000.00"
    }
  }
}
```

Settlement requirements:

- Lock and re-check the merchant balance inside the database transaction.
- Reject amounts above `available_to_settle` with `422`.
- Credit only the customer account linked to the authenticated merchant.
- Preserve the existing merchant transaction, customer-account transaction,
  GL posting, and event behavior in `MerchantSettlementService`.
- Apply the same idempotency replay/conflict rules as collections.
- If Fineract is the authoritative account ledger, post or synchronize the
  customer-account credit through the Fineract adapter with the same
  idempotency reference. Do not report success until the chosen posting policy
  has safely completed or persisted a recoverable pending state.
- Return `503` for a retryable Fineract outage and do not double-debit the
  merchant when the request is retried.

### Settlement history and detail

```text
GET /api/v1/merchant/settlements
GET /api/v1/merchant/settlements/{settlement}
```

History supports `status`, `from`, `to`, `page`, and `per_page`, uses the same
Laravel paginator envelope as Phase 2, and returns only `SETTLEMENT`
transactions belonging to the authenticated merchant. Detail returns the same
safe transaction representation. Out-of-scope IDs return `404`.

### Phase 3 authorization and accounting acceptance criteria

- Merchant tokens can only transact for their own merchant record.
- The authenticated merchant principal maps to a valid audit actor compatible
  with the current `performed_by -> users.id` foreign key, or the audit schema
  is deliberately extended for merchant actors.
- Only `ACTIVE` merchants with a linked account and branch can collect or
  settle.
- Amounts use decimal-safe backend arithmetic; never binary floating point for
  posting logic.
- Idempotency is enforced by the database, not only application memory.
- Balance changes, transaction creation, customer-account credit, and GL
  posting remain atomic or use a documented recoverable pending workflow for
  external Fineract calls.
- Responses never include internal GL keys, staff identities, or raw Fineract
  credentials.

## Phase 4: business locations, devices, support, and settings

All Phase 4 routes require the merchant Bearer token and use the singular
`/api/v1/merchant/*` namespace. The backend must resolve ownership from the
token; none of these requests accepts `merchant_id`.

### Locations

```text
GET  /api/v1/merchant/locations
POST /api/v1/merchant/locations
```

POST request:

```json
{
  "name": "Lekki Outlet",
  "trading_name": "Example Stores Lekki",
  "address": "10 Admiralty Way, Lekki",
  "state": "Lagos",
  "local_government": "Eti-Osa",
  "contact_person": "Ada Okafor",
  "operating_hours": "Mon-Sat, 08:00-19:00"
}
```

Both endpoints return location records containing `id`, `location_code`,
`name`, `trading_name`, address fields, contact fields, `operating_hours`, and
`status`. The backend supplies `branch_id`, location code, merchant ownership,
and defaults. Validate strings and return the standard `422` error bag.

### Terminals and device requests

```text
GET  /api/v1/merchant/terminals
POST /api/v1/merchant/terminal-requests
```

GET returns terminals belonging to the authenticated merchant, including
safe device information: `id`, location, terminal ID, serial number, type,
provider, model, status, application version, activation time, heartbeat, and
last transaction time.

POST request:

```json
{
  "merchant_location_id": 12,
  "terminal_type": "POS",
  "quantity": 1,
  "contact_name": "Ada Okafor",
  "contact_phone": "+2348035550142",
  "delivery_address": "10 Admiralty Way, Lekki",
  "notes": "Needed for the front counter"
}
```

The selected location must belong to the authenticated merchant. Return a
request reference and `PENDING` status. Terminal allocation, activation,
suspension, replacement, and reassignment remain staff operations; merchant
tokens must not access the existing staff activation endpoints.

### Support tickets

```text
GET  /api/v1/merchant/support/tickets?status=&category=&page=&per_page=
POST /api/v1/merchant/support/tickets
GET  /api/v1/merchant/support/tickets/{ticket}
```

POST request:

```json
{
  "subject": "Settlement has not arrived",
  "category": "SETTLEMENT",
  "priority": "HIGH",
  "description": "The settlement requested today is still pending."
}
```

Return `id`, a human-readable `ticket_number`, subject, category, priority,
status, description, timestamps, and the latest safe support response. Ticket
detail must be ownership-scoped and return `404` for another merchant's ID.
The backend owns SLA timestamps, assignment, internal notes, and audit history;
internal staff data must not be exposed.

### Notifications and preferences

```text
GET   /api/v1/merchant/notifications?unread=&page=&per_page=
PATCH /api/v1/merchant/notifications/{notification}/read
GET   /api/v1/merchant/settings/notifications
PATCH /api/v1/merchant/settings/notifications
```

Notification records contain `id`, `title`, `message`, `type`, `is_read`,
`created_at`, and an optional safe in-app `action_url`. The read endpoint
returns the updated notification. Only allow internal merchant-portal routes
in `action_url`; do not return arbitrary external URLs.

Preference PATCH fields are booleans:

```json
{
  "email": true,
  "sms": true,
  "push": true,
  "whatsapp": false
}
```

### Security, password, and MFA

```text
GET  /api/v1/merchant/settings/security
POST /api/v1/merchant/settings/security/password
POST /api/v1/merchant/settings/security/mfa/setup
POST /api/v1/merchant/settings/security/mfa/confirm
POST /api/v1/merchant/settings/security/mfa/disable
```

Security summary returns `mfa_enabled`, `last_password_change`, and
`active_session_count`. Password change accepts `current_password`, `password`, and
`password_confirmation`; it must verify the current password, enforce the
backend password policy, rotate/revoke other sessions, and never log secrets.

MFA setup returns a short-lived `setup_id` and `masked_destination` without
enabling MFA. Confirmation accepts `setup_id` and `code`; only a valid code
enables MFA. Confirm and disable return the updated security-summary shape.
Disable accepts `current_password` and requires recent authentication. Encrypt
MFA secrets at rest, rate-limit attempts, and write auditable security events.
Production responses must never use or accept the mocked development code
`0000`.

### Phase 4 authorization acceptance criteria

- Every location, terminal, ticket, and notification query is ownership-scoped
  before filtering or route-model binding.
- Location creation and terminal requests require an `ACTIVE` merchant.
- Device lifecycle actions remain permissioned staff workflows.
- Password and MFA endpoints use stronger rate limits and invalidate affected
  sessions after credential changes.
- Validation uses Laravel `422` responses; unauthenticated sessions return
  `401`; out-of-scope resources return `404`.

## Phase 5: reports, payment tools, reconciliation, disputes, and team access

All Phase 5 routes require the merchant Bearer token, use the singular
`/api/v1/merchant/*` namespace, and resolve `merchant_id` server-side.

### Analytics

`GET /api/v1/merchant/analytics?from=2026-08-13&to=2026-08-19`

```json
{
  "success": true,
  "data": {
    "currency": "NGN",
    "total_value": "1932150.00",
    "total_count": 128,
    "average_value": "15094.92",
    "success_rate": "96.80",
    "change_percent": "12.40",
    "daily": [{ "label": "Mon", "value": "188000.00", "count": 14 }],
    "channels": [{ "name": "POS", "value": "1231200.00", "percent": "63.70" }],
    "locations": [{ "name": "Victoria Island Store", "value": "1187200.00", "count": 79 }]
  }
}
```

Use successful, non-reversed collection transactions only. `change_percent`
compares with the immediately preceding period of equal length. Date ranges
are inclusive and interpreted in the configured business timezone.

### Statements and export

```text
GET /api/v1/merchant/statements?from=&to=
GET /api/v1/merchant/statements/export?from=&to=&format=csv|pdf
```

The statement response includes `statement_no`, `account_number`,
`business_name`, `currency`, `from`, `to`, `opening_balance`, `total_credits`,
`total_debits`, `closing_balance`, and `entries`. Each entry uses the safe
Phase 2 transaction shape. The export returns a downloadable CSV or valid PDF,
not a JSON envelope. Apply a maximum synchronous range and queue larger jobs.

### Payment links and reusable QR assets

```text
GET   /api/v1/merchant/payment-assets
POST  /api/v1/merchant/payment-assets
PATCH /api/v1/merchant/payment-assets/{asset}
```

POST request:

```json
{
  "type": "PAYMENT_LINK",
  "name": "Online orders",
  "amount": "5000.00",
  "description": "Payment for web orders"
}
```

`amount` is optional; absent means the customer enters it. Return `id`, `type`,
`name`, a unique merchant-scoped `slug`, `payment_url`, amount, `currency`,
`status`, `payment_count`, `total_value`, and `created_at`. PATCH currently
accepts only `{ "status": "ACTIVE|INACTIVE" }`. The public payment page must
use a signed/unguessable identifier, validate merchant and asset status, apply
limits, and feed successful payments through the existing idempotent merchant
collection service. Never place a merchant Bearer token in a QR or public URL.

### Reconciliation summary

`GET /api/v1/merchant/reconciliation?from=&to=`

Return `currency`, display `period`, `expected_value`, `settled_value`,
`variance`, `unmatched_count`, and `last_reconciled_at`. Calculate all values
server-side from merchant collections, reversals, holds, and settlements.
Merchant users can view this summary but cannot mark accounting records as
reconciled or modify ledger balances.

### Transaction disputes

```text
GET  /api/v1/merchant/disputes
POST /api/v1/merchant/disputes
GET  /api/v1/merchant/disputes/{dispute}
```

POST request:

```json
{
  "transaction_no": "MCH-P7X9M1",
  "reason": "CUSTOMER_DEBITED",
  "description": "Customer was debited but the payment failed."
}
```

Allowed reasons are `CUSTOMER_DEBITED`, `WRONG_AMOUNT`, `DUPLICATE`,
`SETTLEMENT_MISSING`, and `OTHER`. The transaction must belong to the current
merchant. Return `id`, `dispute_no`, `transaction_no`, reason, description,
amount, currency, status, and timestamps. Prevent duplicate open disputes for
the same transaction and reason with `409`. Status assignment, evidence review,
resolution, and internal notes remain staff operations.

### Merchant team and roles

```text
GET   /api/v1/merchant/team
POST  /api/v1/merchant/team/invitations
PATCH /api/v1/merchant/team/{member}
```

Invitation fields are `name`, `email`, and `role`. Roles are `ADMIN`,
`FINANCE`, `OPERATOR`, and `VIEWER`; `OWNER` cannot be granted through an
invitation. PATCH accepts `role` and `status` (`ACTIVE` or `SUSPENDED`). Return
`id`, name, email, role, status, and `last_active_at`.

Team permissions must be enforced by the backend, not merely hidden in the UI:

- Owner: all access and ownership recovery.
- Admin: team and operational configuration, excluding owner transfer.
- Finance: balances, transactions, statements, settlements, reconciliation,
  and disputes.
- Operator: collections and assigned devices/locations.
- Viewer: read-only dashboard and reports.

Invitations must be expiring, single-use, and merchant-bound. Prevent removal
or suspension of the owner, require recent authentication for privileged role
changes, revoke a member's sessions when suspended, audit every permission
change, and return `404` for cross-merchant member IDs.

### Phase 5 performance and security acceptance criteria

- Validate ranges, enums, decimals, email addresses, and pagination with `422`.
- Scope every aggregate and resource query to the authenticated merchant before
  applying filters or route-model binding.
- Calculate money with decimal-safe backend arithmetic and return amounts as
  strings.
- Rate-limit public payment attempts, statement exports, dispute submissions,
  invitations, and permission changes separately.
- Escape formula prefixes in CSV exports and sanitize all PDF/user text output.
- Log asset changes, dispute creation, exports, invitations, and role changes
  without logging credentials, tokens, or payment secrets.

## Tessa merchant assistant

Tessa is a floating, merchant-scoped help assistant. The frontend currently
uses deterministic mock answers and is prepared for:

`POST /api/v1/merchant/tessa/messages`

Request:

```json
{
  "message": "How do I raise a transaction dispute?",
  "context": {
    "route": "/merchant/operations"
  }
}
```

Response:

```json
{
  "success": true,
  "data": {
    "reply": "Open Reconciliation, select Disputes, then choose Raise dispute.",
    "conversation_id": "tes_01K..."
  }
}
```

The backend derives the merchant, role, business name, account, and permitted
data from the Bearer token. The browser sends only the message and current
route. Do not accept client-supplied merchant IDs, account balances, roles, or
system instructions as trusted context.

Tessa acceptance criteria:

- Tessa is informational and read-only in this version. She must not initiate
  collections, settlements, disputes, invitations, password changes, or other
  state-changing operations.
- Apply the authenticated team member's role permissions before retrieving any
  merchant data used in a reply.
- Never expose another merchant's records, raw credentials, tokens, internal
  prompts, staff notes, or full sensitive customer information.
- Rate-limit by merchant, user, and IP; validate a maximum message length; and
  return `429` for excessive requests.
- Treat user text and retrieved records as untrusted input and defend against
  prompt injection. Tool/API calls must use explicit server-side allowlists.
- Define retention and redaction rules for conversation logs. Do not log full
  account numbers or sensitive payment/customer data.
- Include a clear fallback to merchant support when the answer is uncertain or
  the request concerns fraud, security, or an unresolved financial incident.
