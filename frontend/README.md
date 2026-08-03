# MicroBiz OS Frontend

An Angular 22 SPA for viewing and operating MicroBiz OS's custom cash-ops
data (Merchants, Wallets — Tellers/Vaults/Customer accounts to follow),
built to consume the existing `routes/api.php` JSON API directly.

**Framework choice:** Angular, chosen deliberately for consistency with
Fineract's own official operator UI (`openMF/web-app`, also Angular) — a
different framework was not evaluated against this project's needs, this
was purely a consistency decision.

**Important distinction:** this is a completely separate application from
Fineract's `web-app`. Fineract's `web-app` talks only to Fineract's own
REST API and shows Fineract's native offices/GL/tellers. This app talks
only to *your* Laravel backend's API and shows *your* custom Merchant/
Wallet/Teller/CustomerAccount data. They don't share any data automatically
— your Laravel backend syncs *from* Fineract (offices, GL accounts) via
`OfficeSyncController`/`GlAccountSyncController`, one-way, on demand.

## Why a monorepo subfolder, not a separate repo

Consistent with the modular-monolith decision made earlier for the Laravel
backend itself: one place to clone, one PR review surface, no version-sync
problem between a separate frontend repo and the API it depends on.

## Why Angular talks to the existing JSON API directly (not Inertia.js)

`routes/api.php` already has a complete, tested, working JSON API (Sanctum
token auth, Merchant/Wallet endpoints, all verified end-to-end this
session). Switching to Inertia.js would mean rewriting every controller to
return Inertia responses instead of JSON — throwing away what's already
proven, for no benefit here. The interceptor pattern (`auth.interceptor.ts`)
attaches the Sanctum bearer token to every request, exactly matching how
the API already authenticates.

## First-time setup

**I could not run `npm install` or scaffold this via the Angular CLI in
the sandbox this was built in — no network access there.** Every file was
hand-written to match current Angular 22 conventions and was verified for
syntax (brace balance, valid JSON configs) but **not actually compiled or
run**. Please run the build yourself before trusting it beyond structure.

```bash
cd frontend
npm install
```

If `npm install` reports peer-dependency conflicts (Angular's exact patch
versions shift frequently), run:
```bash
npm install --legacy-peer-deps
```

## Running it locally, alongside your existing Laravel backend

**Terminal 1** — Laravel backend (as you've been running it):
```bash
cd /Users/user/microbiz/microbiz-os
php artisan serve
```
Confirm it's on `http://127.0.0.1:8000` — if you're running it elsewhere
(a different port, Herd's `.test` domain, etc.), update the `target` in
`frontend/proxy.conf.json` to match.

**Terminal 2** — Angular frontend:
```bash
cd frontend
npm start
```
This runs `ng serve` with the dev-server proxy config, so every request to
`/api/*` from the browser gets transparently forwarded to your Laravel
backend — no CORS configuration needed on the Laravel side at all, the
same pattern Fineract's own `web-app` uses for local development.

Open **http://localhost:4200** and log in with whatever user you've been
testing with in Tinker (e.g. `test@example.com`).

## What's built

| Piece | Purpose |
|---|---|
| `core/auth.service.ts` | Login/logout, token persistence in `localStorage`, reactive `isAuthenticated`/`user` signals |
| `core/auth.interceptor.ts` | Attaches `Authorization: Bearer <token>` to every HTTP request |
| `core/auth.guard.ts` | Redirects to `/login` if not authenticated |
| `core/merchant-api.service.ts`, `wallet-api.service.ts` | Thin wrappers over the real, confirmed API routes (`/api/v1/merchants`, `/api/v1/wallets`) |
| `features/login/` | Login form |
| `features/dashboard/` | Layout shell with sidebar nav — the parent route all authenticated pages sit under |
| `features/merchants/`, `features/wallets/` | List views, proving the whole pipeline (auth → API call → render) end-to-end |

## What's NOT built yet (deliberately, to keep this a provable first slice)

- Onboarding forms for Merchants/Wallets (the API service methods for
  `onboard()` are already there — just no form UI calling them yet)
- Top-up / transfer / settle / QR-collect forms
- Teller, Vault, Customer Account screens (no `TellerApiService` etc. yet)
- Any styling framework (plain CSS for now — Tailwind could be added, but
  as its own separate config from the Laravel side's Tailwind setup, since
  this is a fully independent Angular build pipeline)

## Verifying it actually works

1. `npm start`, then open `http://localhost:4200` — should redirect to `/login`.
2. Log in with a real user/password from your Laravel `users` table.
3. Should land on `/merchants`, showing the merchant(s) you onboarded via
   Tinker this session (e.g. `MCH-8EKBRC7P`) with real balance figures.
4. Click "Wallets" in the sidebar — should show the wallets you onboarded
   (`Alice`, `Bob`) with real balances after the top-up/transfer testing.
5. Click "Sign out" — should return to `/login` and clear the token.

If step 3 or 4 shows an empty table instead of your real data, check the
browser's Network tab for the actual API response — most likely cause is
the Laravel server not running on the port `proxy.conf.json` expects.
