# MicroBiz OS Frontend

Angular 22 frontend for the MicroBiz OS Laravel application. The Angular and
Laravel applications live in the same repository and run together locally.

For merchant implementation status and continuation instructions, read
[`MERCHANT_PORTAL_HANDOFF.md`](MERCHANT_PORTAL_HANDOFF.md). For exact backend
contracts, read [`MERCHANT_API_CONTRACT.md`](MERCHANT_API_CONTRACT.md).

## Local setup

From the repository root, start Laravel:

```bash
composer install
php artisan migrate --seed
php artisan serve
```

In a second terminal, start Angular:

```bash
cd frontend
npm install
npm start
```

Open `http://127.0.0.1:4200`. The Angular development server proxies `/api/*`
to `http://127.0.0.1:8000` using `proxy.conf.json`.

## Merchant portal

The merchant-facing application begins at:

```text
http://127.0.0.1:4200/login/merchants
```

Implemented areas:

- Existing MicroBiz account lookup and verification
- OTP/onboarding flow with per-capability live/mock switches
- Merchant registration and profile
- Dashboard and balance summaries
- Transaction search, filters, details, pagination, and CSV export
- QR/POS collection flows
- Settlement requests and history
- Business locations and terminal/device requests
- Support tickets and status tracking
- Notifications, preferences, password management, and MFA setup
- Analytics, statements, and CSV/PDF export flows
- Reusable payment links and QR payment assets
- Reconciliation summaries and transaction disputes
- Merchant team invitations and role-based access controls
- Merchant session guard and logout

Only account preview currently uses the live backend. Other merchant features
remain mocked until the singular merchant self-service endpoints described in
`MERCHANT_API_CONTRACT.md` are implemented.

## Production build

```bash
cd frontend
npm run build
```

Build output is written to `frontend/dist/microbiz-os-frontend`.

## Architecture

- Components are standalone Angular components.
- API calls and response mapping belong in `MerchantPortalApiService`.
- Backend HTTP contracts use `snake_case`.
- Merchant self-service calls use singular `/api/v1/merchant/*` routes and a
  merchant-scoped Bearer token.
- Staff operations remain under plural `/api/v1/merchants/*` routes.
- Angular never calls Fineract directly; Laravel owns Fineract integration.
- Live/mock behavior is controlled centrally in `src/environments/environment.ts`.

Do not place credentials in frontend environment files or commit the repository
root `.env` file.
