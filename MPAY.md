# M-Pay Module — Build Log & Developer Guide

| | |
|---|---|
| Status | Foundation in progress — Phase 1 (per the M-Pay Backend Build Guide) |
| Location | `app/Domain/MPay/` (code) + `database/migrations/mpay/` (schema), inside the `microbiz-os` repo |
| Last updated | 2026-07-29 |
| Related docs | `CHANGELOG.md` (core backend bug tracking), `AUDIT.md` (capability gap audit) |

This file is a record of what's actually built and verified, not a plan of what's intended. If something below doesn't match the code, the code wins — update this file, don't trust it blindly.

## 0. The rule everything here follows

M-Pay orchestrates payment-channel transactions. It **never** owns balances, ledgers, or identity —
that's FinCore's job, accessed only through MicroBiz OS. Any code that gives M-Pay a direct write into
OS/FinCore tables, a private ledger, or its own identity store is wrong, regardless of how convenient
it seems in the moment.

## 1. Isolation model

M-Pay has its own logical database, completely separate from the main app:

- **Connection name:** `mpay` (defined in `config/database.php`)
- **Local default:** a dedicated SQLite file at `database/mpay.sqlite`
- **Migrations:** live in `database/migrations/mpay/`, *not* the main `database/migrations/` folder — Laravel doesn't scan subfolders by default, so `app/Providers/MPayServiceProvider.php` explicitly registers this path
- **Code:** lives under `app/Domain/MPay/`, following this repo's existing `app/Domain/{Name}/` convention (see `app/Domain/Finance/` for the pattern this copies)

Every migration in `database/migrations/mpay/` must declare `protected $connection = 'mpay';` and use
`Schema::connection($this->connection)->...` — without that, it would silently create the table in the
*main* database instead, defeating the whole point of isolation.

## 2. Local setup

Nothing extra is required by default — `database/mpay.sqlite` already exists and `php artisan migrate`
picks up the `mpay` migrations automatically. To verify it's working:

```
php artisan tinker --execute="print_r(Schema::connection('mpay')->getTableListing());"
```

**If you want to point your own local `mpay` connection at a different database engine** (e.g. MySQL,
Postgres, or SQL Server) instead of SQLite, set these in your own `.env` — this only affects your
machine, nobody else's:

```
MPAY_DB_CONNECTION=sqlsrv   # or mysql, pgsql — defaults to sqlite if unset
MPAY_DB_HOST=...
MPAY_DB_PORT=...
MPAY_DB_DATABASE=...
MPAY_DB_USERNAME=...
MPAY_DB_PASSWORD=...
```

All the M-Pay migrations use Laravel's schema builder (no raw SQL), so they *should* translate correctly
to any of these engines — but only SQLite has actually been run and verified so far. Treat any other
engine as untested until someone runs it and confirms.

## 3. What exists today, verified working

**Code:**
- `app/Domain/MPay/Enums/TransactionType.php` — `WALLET_TO_WALLET`, `WALLET_TO_BANK`, `POS_CASH_IN`, `POS_CASH_OUT`, `MERCHANT_PAYMENT`
- `app/Domain/MPay/Enums/TransactionStatus.php` — the full state machine (`RECEIVED` → ... → `SUCCESSFUL`, plus `FAILED`/`REVERSED`/`EXPIRED`/`CANCELLED`) — never collapse this to just pending/success/failed
- `app/Domain/MPay/Contracts/FineractGateway.php` — the interface any code needing FinCore data calls through. Not backed by a real implementation yet (see Section 5) — mock data is next.
- `app/Providers/MPayServiceProvider.php` — registers the `mpay` migrations path

**Tables** (all confirmed to exist only in `mpay.sqlite`, confirmed absent from the main database):

| Table | Purpose |
|---|---|
| `payment_parties` | Generic party record (`party_type`: CUSTOMER/AGENT/MERCHANT) referencing OS/FinCore identities — not a duplicate customer master |
| `wallets` | Points at a FinCore account; `available_balance_cache` is a display cache only, never the source of truth; `version` column for optimistic locking |
| `payment_transactions` | Central transaction record — `transaction_uuid`, `idempotency_key`, wallet references, amounts in integer minor units, `status`, `channel` |
| `wallet_holds` | Reservation against a wallet while a transaction is in flight, so concurrent requests can't both overdraw the same wallet |
| `transaction_status_history` | Append-only log of every status change — `created_at` only, no `updated_at`, rows are never edited |
| `transaction_legs` | Breakdown of a transaction into its component movements (debit/credit/fee/commission), each mapping to a future FinCore ledger entry |
| `api_idempotency_keys` | Replay guard — same idempotency key + same request → same cached response, never processed twice |

**Money rule enforced throughout:** all amounts are stored as integers in minor units (kobo, not naira)
plus a 3-letter currency code. No floats or decimals anywhere in this schema.

## 4. Not built yet — next steps in order

1. `outbox_events` table — events written in the same DB transaction as the state change, so nothing gets silently lost if a crash happens between saving state and publishing an event
2. `pos_terminals` and `pos_agents` tables — admin-created terminals, agents modeled as "cashiers" tied to a location and a terminal
3. A simple, clearly-labeled **staging-only** authorization check for agent actions — explicitly *not* full RBAC (see Section 5)
4. A fake `FineractGateway` implementation returning realistic mock data, so wallet balance/transaction work can proceed without real Fineract/Interswitch access
5. The actual POS agent cash-in/cash-out endpoints
6. The admin "create POS terminal" endpoint

## 5. Deliberate simplifications — staging only, revisit before production

These were conscious decisions to avoid stalling on prerequisites that don't exist yet at the OS level.
Don't mistake any of these for "done":

- **No real multi-tenancy.** Several tables have a nullable `tenant_id` column, but nothing enforces
  tenant isolation yet — it's a placeholder so the schema doesn't need reshaping later, not a working feature.
- **No full RBAC.** The OS has no roles/permissions system yet (`spatie/laravel-permission` isn't
  installed, no Gate/Policy classes exist). The POS module will use a deliberately simple, staging-only
  check instead of the Build Guide's `can:mpay.transfer.create`-style permission gates. "POS admin can be
  anyone for now" was an explicit, scoped decision — not an oversight.
- **No real FinCore connection for M-Pay yet.** `FineractGateway` is an interface with no implementation.
  Development will proceed against mock data behind that interface so it isn't blocked waiting on real
  vendor/Interswitch access — but nothing built against the mock has been validated against a real system,
  and that validation still has to happen before any of this touches real transactions.
- **Actor identity is proven, but only for internal staff so far.** The "always derive who performed an
  action from the authenticated session, never trust a client-supplied ID" pattern (fixed this session in
  `ApprovalController`/`CustomerCashController`) is the same discipline this module must follow for agents —
  but agents are a different kind of principal than internal staff, and that extension hasn't been built yet.

## 6. Where this fits in the bigger picture

This module is being built against the M-Pay Backend Build Guide (shared separately, not in this repo) and
sits underneath the CBN agent-banking/Peak Empowerment structure discussed alongside it — Peak operates as
a distribution/support layer, never touching balances or ledgers directly, which is exactly the same
isolation principle this module enforces in code.
