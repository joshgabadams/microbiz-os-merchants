# M-Pay Module — Build Log & Developer Guide

| | |
|---|---|
| Status | Foundation in progress — Agent AG-01 through AG-04 done (Registry/KYC/Locations/Agreements/Operators/Terminals+geo-fence); Merchant registry/KYC/locations/terminals/payment-bug-fixes done (see §4 and §8) |
| Location | `app/Domain/MPay/` (code) + `database/migrations/mpay/` (schema), inside the `microbiz-os` repo |
| Last updated | 2026-08-10 |
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
- `app/Domain/MPay/Contracts/FineractGateway.php` — the interface any code needing FinCore data calls through. Two implementations exist: `App\Services\Fineract\FineractClient` (real, calls Fineract's savings-account API) and `App\Domain\MPay\Services\FakeFineractGateway` (in-memory, local/testing only). `MPayServiceProvider` binds whichever fits the current environment.
- `app/Providers/MPayServiceProvider.php` — registers the `mpay` migrations path, the `agent` migrations path (main-database Agent registry tables), and the `FineractGateway` environment binding

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

## 4. Agent module — built against the real M-PAY Agency Banking Blueprint

The Agent/POS work in this repo is **not** ad hoc — it's built against two real spec documents:
`M-PAY Agency Banking Blueprint.pdf` and `M-PAY Merchant Management Services.pdf` (the latter not yet
read/applied). See `project_agent_module_blueprint.md` in the auto-memory system for the full extracted
directives and how to re-read the PDFs (Chrome can't render them directly here — see that memory for the
local-http-server workaround). An earlier build pass (2026-08-05) collapsed Agent/Location/Operator/Terminal
into a single `pos_agents` table before the Blueprint was actually read; that was reworked on 2026-08-06
once the real spec was found, and the old design is gone (no dead files left behind).

**The Blueprint's own delivery roadmap (§21) is sprint-based, and its implementation-sequence section
(§22) is explicit: don't start with cash-in/cash-out.** The real order is:

Agent governance → Agent KYC → Location verification → Agreement → Operator → Terminal and geo-fence →
Float → Cash-in → Cash-out → Transfer and bills → Commission → Reconciliation → TESSA

**Sprint AG-01 — Agent Registry: done.** Matches Blueprint §8.1 (schema) and §6 (lifecycle) exactly:
- `agents` table (main database, `database/migrations/agent/`) — `agent_code`, `agent_type`, `legal_name`,
  `trading_name`, `registration_number`, `tax_identification_number`, `phone`, `email`, `branch_id`
  (real FK), `supervisor_id`, `status`, `kyc_status`, `risk_rating`, `exclusive_relationship`,
  `principal_reference`, three limit columns, `next_review_date`, `created_by`/`approved_by` (real FKs),
  timestamps, soft deletes
- `App\Domain\MPay\Enums\AgentStatus` — full lifecycle: `PROSPECT, DRAFT, PENDING_KYC,
  PENDING_LOCATION_VERIFICATION, PENDING_COMPLIANCE_REVIEW, PENDING_APPROVAL, APPROVED,
  AGREEMENT_PENDING, TRAINING_PENDING, TERMINAL_PENDING, ACTIVE`, plus `REJECTED, RESTRICTED, SUSPENDED,
  DORMANT, TERMINATED, EXPIRED, BLACKLISTED`
- Services (named to match Blueprint §9): `AgentRegistrationService`, `AgentApprovalService`
  (submit/approve/reject, maker-checker enforced), `AgentActivationService` (activate/restrict/suspend/
  reactivate/terminate)
- `AgentController` + routes matching Blueprint §11 exactly (`POST /api/v1/agents` for create, not
  `/agents/onboard`), permissions matching §12 (`agents.view/create/edit/submit/approve/reject/activate/
  restrict/suspend/reactivate/terminate`) via `PaymentsRbacSeeder`
- Frontend test page at `frontend/src/app/features/agents/` (Angular, mirrors the existing Merchant page)
- Verified end-to-end locally via tinker: register → submit → self-approve correctly blocked → approve
  (different user) → activate → suspend → reactivate all work; caught and fixed a real bug in the
  process (`approved_at`/`activated_at`/`suspended_at`/`suspension_reason` were missing from `Agent`'s
  `$fillable`, so those fields silently failed to persist)

**AG-02 — KYC and Approval: done** (merged from `main`, 2026-08-09/10). `AgentKycService::completeKyc()`
enforces real gates: agent must be `PENDING_KYC`, reviewer must differ from the registering officer
(maker-checker), and at least one owner **and** one document must be on file before status advances to
`PENDING_LOCATION_VERIFICATION` and `kyc_status` flips to `COMPLETED`. `agent_beneficial_owners` and
`agent_documents` tables, list/add endpoints.

**AG-03 — Locations and Agreements: done** (merged from `main`, 2026-08-09/10). `agent_locations` (address,
GPS, `verification_status`/`status`, `verified_by`/`verified_at`) with `createLocation`/`verifyLocation`/
`rejectLocation` in `AgentLocationService` — verification requires a different reviewer than whoever
registered the location, and successful verification advances the agent to `PENDING_COMPLIANCE_REVIEW`.
`AgentApprovalService::completeComplianceReview()` then requires an active verified location before moving
to `PENDING_APPROVAL`. `agent_agreements` table + `AgentAgreementService` (create/execute).

**AG-04 — Operators and Terminals: done** (built + verified 2026-08-10, this session). Two separate
tables, matching Blueprint §8.4/§8.5 exactly:
- `agent_operators` — links an existing platform `user_id` to a specific `agent_id` + `agent_location_id`
  with a `role` string; unique constraint on the (agent, location, user) triple prevents duplicate
  assignment. `AgentOperatorService` create/activate/suspend, with an ownership check (location must
  belong to the agent) and a duplicate-assignment check.
- `agent_terminals` — `terminal_id`/`serial_number` (unique), `registered_latitude`/`registered_longitude`/
  `geo_fence_radius_metres` (all required, not nullable — a terminal must declare its geo-fence at creation),
  `last_latitude`/`last_longitude`/`geo_fence_compliant`/`last_heartbeat_at`. `AgentTerminalService` adds
  `heartbeat()` and `checkLocation()`, both running a Haversine distance calculation between the terminal's
  registered point and its reported coordinates against `geo_fence_radius_metres` — verified correctly
  distinguishing an ~11km-away point (non-compliant) from a ~7cm-offset point (compliant) at a 100m radius.
  A dedicated `assign()`/`assignLocation()` endpoint handles *documented relocation* to a different location
  under the *same* agent (Blueprint §2.3 explicitly requires relocation to be documented, not silent) —
  moving a terminal to a different agent entirely is out of scope by design (§2.3's exclusivity rules
  explicitly prohibit sharing terminals between unrelated agents).
- Both verified end-to-end via live API calls (not just tinker) — full create/list/duplicate-rejection/
  activate/re-activate-rejection/suspend/suspend-rejection/reactivate cycles, plus relocate for terminals.

**Agent Locator — IP-based monitoring layer (issue #10, built 2026-08-11).** Not part of the Blueprint's
own module list — this is a GitHub-issue-driven addition, scoped after direct clarification from the issue
author (muayyat): physical geofence *enforcement* happens on the terminal's own hardware/binary, which this
backend never controls in real time; what we own here is a second, independent *monitoring* signal, not a
replacement for the GPS geo-fence in AG-04 above.

- `App\Services\GeoIp\GeoIpLookupService` resolves an IP to an approximate location via a local MaxMind
  GeoLite2 database file (`geoip2/geoip2` PHP library). Private/reserved IPs (localhost, LAN ranges) are
  filtered out before lookup and return `null` cleanly rather than throwing — verified this doesn't break
  local dev, where every request IP is `127.0.0.1`.
- Database files (`GeoLite2-ASN/City/Country.mmdb`, ~85MB total) are fetched via MaxMind's official
  `geoipupdate` binary, driven by `storage/app/GeoIP.conf` — generated at `scripts/generate-geoip-conf.sh`
  from `.env` values (`MAXMIND_ACCOUNT_ID`/`MAXMIND_LICENSE_KEY`/`MAXMIND_EDITION_IDS`/`MAXMIND_DB_PATH`),
  never hand-edited or committed (real license key). `php artisan geoip:update` re-runs it; scheduled daily
  in `routes/console.php` (same "needs real cron wired to `schedule:run`" caveat as the existing TESSA job).
- `AgentTerminalService::heartbeat()`/`checkLocation()` now also accept the requesting IP, look up its
  approximate position, and compare it against the terminal's `registered_latitude`/`registered_longitude`
  via the same Haversine helper used for the GPS geo-fence check — but into new, separate columns
  (`last_ip_address`, `ip_latitude`/`ip_longitude`/`ip_city`/`ip_state`/`ip_country`, `ip_location_mismatch`,
  `ip_checked_at`) that never feed `geo_fence_compliant`. Mismatch threshold defaults to 100km
  (`config('geoip.mismatch_threshold_km')`) — deliberately wide, since city-level IP geolocation is not
  precise enough to be a "does this look wrong" flag at anything tighter.
- Verified: a real public IP (`8.8.8.8`) resolving to the US against a terminal registered in Lagos
  correctly set `ip_location_mismatch = true`; the local-dev `127.0.0.1` path correctly stored the IP and
  timestamp but left the derived-location fields null (no crash, no false positive) since private IPs
  can't be geolocated.
- **Not built:** any actual alerting/case-management on top of `ip_location_mismatch` — it's a stored flag
  today, nothing surfaces it yet (a natural fit for a future TESSA rule, matching the pattern already used
  for teller variance/high reversal/unusual approval, but that wiring wasn't in scope for this issue).
- Basic Auth (issue #9) is a separate, still-open item — deliberately not started, since the issue has no
  body/detail beyond its title and scope (whole-app auth vs. a specific endpoint) hasn't been confirmed.

**Not built yet, in Blueprint order** (AG-05 through AG-13 — see Blueprint §7/§21 for full field lists):
1. **AG-05 — Geo-Fencing and Security**: the heartbeat/location-check *mechanics* exist (built as part of
   AG-04 above, since the schema and geo-fence math are one piece), but the broader security layer — device
   certificates, request signing, remote-suspension-on-security-event — is not built.
2. **AG-06 — Float**: `agent_balances` table (Blueprint §8.6) not built at all yet. **This is the first
   module that touches real money movement — see §9 below before starting it.**
3. AG-07/AG-08 (cash-in/cash-out), AG-09 (transfers/bills), AG-10 (commission), AG-11
   (reconciliation/EOD), AG-12 (supervision/complaints), AG-13 (TESSA agent intelligence) — none started.

**Critical directive for when Float/Cash-in/Cash-out are eventually built (Blueprint §22):** *"The
existing cash-control work in FINCORE360 should be reused for: Balance locking, Transaction numbering,
Cash ledger, GL posting, Reversal, Approval, Audit, Notifications, Reconciliation. Do not build a second
independent cash engine inside M-PAY."* Reuse `CashLedger`/`GlPostingService`/`TransactionNumberService`/
vault-style balance locking (already used by Vault/Teller) — do not build M-Pay-specific equivalents.

## 5. Deliberate simplifications — staging only, revisit before production

These were conscious decisions to avoid stalling on prerequisites that don't exist yet at the OS level.
Don't mistake any of these for "done":

- **No real multi-tenancy.** Several tables have a nullable `tenant_id` column, but nothing enforces
  tenant isolation yet — it's a placeholder so the schema doesn't need reshaping later, not a working feature.
- **No full RBAC.** The OS has no roles/permissions system yet (`spatie/laravel-permission` isn't
  installed, no Gate/Policy classes exist). The POS module will use a deliberately simple, staging-only
  check instead of the Build Guide's `can:mpay.transfer.create`-style permission gates. "POS admin can be
  anyone for now" was an explicit, scoped decision — not an oversight.
- **Real FinCore connection exists now, but only reachable in staging so far.** `FineractClient` calls the
  actual `fincore360` Fineract instance (confirmed working over the internal Docker network on the Contabo
  staging box). Nothing built against it has been validated in production, and the demo Fineract credentials
  (`mifos`/`password`) are still default — must be rotated and the connection re-verified before this
  touches real transactions.
- **Actor identity is proven, but only for internal staff so far.** The "always derive who performed an
  action from the authenticated session, never trust a client-supplied ID" pattern (fixed this session in
  `ApprovalController`/`CustomerCashController`) is the same discipline this module must follow for agents —
  but agents are a different kind of principal than internal staff, and that extension hasn't been built yet.
- **AgentApprovalService/AgentActivationService skip gates that don't exist yet.** `submit()` goes straight
  DRAFT → PENDING_APPROVAL rather than stopping at PENDING_KYC (KYC review is AG-02, not built). `activate()`
  goes straight APPROVED → ACTIVE rather than requiring AGREEMENT_PENDING/TRAINING_PENDING/TERMINAL_PENDING
  to clear (agreements/training/terminals are AG-03/AG-04, not built). Both are flagged in code comments.
  **Must not reach production** until AG-02–AG-04 exist and `AgentOperationGuard` (Blueprint §10) is built
  to enforce the real gates.

## 6. Where this fits in the bigger picture

This module is being built against the M-Pay Backend Build Guide (shared separately, not in this repo) and
sits underneath the CBN agent-banking/Peak Empowerment structure discussed alongside it — Peak operates as
a distribution/support layer, never touching balances or ledgers directly, which is exactly the same
isolation principle this module enforces in code.

## 7. Known architectural divergence — a Merchant module already exists on `main`

As of 2026-07-29, `main` gained its own payment-collection feature (`app/Models/Merchant.php`,
`MerchantBalance.php`, `MerchantTransaction.php`, `app/Services/Payments/MerchantPaymentService.php`,
migrations for `merchants`/`merchant_balances`/`merchant_transactions`), introduced in a commit titled
"changes for bug fixes, and m-pay merger." This was built independently of the module described in this
file, and the two are **not the same thing** — but they represent a real fork in direction that needs a
decision, not silent parallel development. **Since then (2026-08-07) this Merchant module has had real
work done against the actual Merchant Management Services Blueprint — see §8 for current status.**

**What was true on `main` as of 2026-07-29** (some of this is now fixed, see §8):
- Lives entirely in the *main* database — `merchants`/`merchant_balances`/`merchant_transactions` have real
  foreign keys straight into `branches`, `users`, and `CashLedger`. It's built as a direct extension of
  FinCore, not an isolated payments layer. **Still true, and now a deliberate choice, not an oversight** —
  matches the Agent registry's own main-database placement decided independently later.
- Amounts are `decimal(24,2)` — actual decimals, not integer minor units. This violates the Build Guide's
  own money rule. **Still true, not addressed.**
- `MerchantPaymentService::collect()` writes to `CashLedger` and calls
  `GlPostingService::postFromCashLedger()` **synchronously**, in the same request that creates the
  transaction. **Still true, not addressed** — a real processor integration would need this to be async.
- ~~No idempotency handling~~ **Fixed 2026-08-07** — see §8.

**Immediate issue, historical:** `collect()`'s synchronous GL post used to hit the `gl_journals` schema bug
tracked as bug #1 in `CHANGELOG.md`. A fix migration for that (`add_missing_columns_to_gl_journals_table`)
exists in the codebase now, added independently of this module's own work — verify it's actually been run
before assuming Merchant collection posts cleanly end to end.

**Long-term risk if this isn't reconciled:**
1. **Two incompatible philosophies for handling money in the same codebase.** This module treats FinCore
   integration as something to reach via an interface (`FineractGateway`), keep isolated, and validate
   before trusting. The Merchant module treats FinCore's ledger as directly writable from application code,
   synchronously, with no isolation boundary. A developer picking up either one as "the pattern to follow"
   for a new feature will build something incompatible with whichever pattern the other one used.
2. **Split financial truth.** If merchant collections post to the real GL/CashLedger but M-Pay wallet
   transactions (once real FinCore integration exists) post through a different path, reconciling "what
   does the ledger actually say happened" requires checking two disconnected systems instead of one —
   directly undermining the "FinCore as single source of truth" principle both the Build Roadmap and the
   M-Pay Build Guide state explicitly.
3. **Precedent for duplicated effort.** This is structurally the same situation as the `till_transactions`
   vs. `teller_transactions` duplication found earlier in this repo's bug audit (`CHANGELOG.md` #6) — two
   systems independently solving the same problem, one of which will eventually become dead code once the
   team picks a direction. The longer both are built out in parallel, the more expensive that eventual
   consolidation gets.

**Not an urgent blocker today** — moving the app onto a shared server (so everyone develops against the
same environment and can actually see each other's work as it lands) will surface this kind of overlap
faster in the future, which helps, but it doesn't substitute for the two builders agreeing on one direction.
That conversation is still owed, just not blocking anything immediate.

## 8. Merchant module — actual build status against the real Blueprint (2026-08-07)

Like Agent, this is built against a real spec: `M-PAY Merchant Management Services.pdf`. Unlike Agent, this
wasn't a from-scratch rework — the Merchant module already existed (§7) and this was a targeted pass to
(a) fix real bugs the Blueprint's §5 explicitly flags as critical, and (b) build the schema/KYC fields a
real, separately-built frontend (Peak Empowerment — a React POS Admin Console, built by another developer
directly against this same Blueprint) is already waiting on.

**Fixed — Blueprint §5 critical bugs, all in `MerchantPaymentService`/`MerchantSettlementService`:**
- §5.2: branch was trusted from client input in collect/settle — now derived server-side from
  `$merchant->branch_id`, with a guard that fails cleanly (not a raw DB error) if a merchant has no branch
- §5.3: no idempotency — `idempotency_key` now required on collect/settle; a retry with the same key
  returns the original result instead of creating a duplicate transaction
- §5.4: collection increased `ledger_balance` and `available_balance` together — now collection increases
  `ledger_balance` + `locked_balance` (reusing the existing, previously-unused `locked_balance` column,
  matching Vault's own `ledger = available + locked` pattern); `available_balance` stays untouched until a
  real hold/reserve system exists (that's Module 8/Settlement batching, still not built — see below)
- §5.6: added a real `status` column (`INITIATED`/`SUCCESSFUL`) on `merchant_transactions`, separate from
  `posted` (which only ever describes GL posting)
- Also fixed opportunistically: routes for merchant `suspend`/`reactivate`/`deactivate` existed from an
  earlier merge, but the controller methods and permissions didn't — calling them threw undefined-method
  errors. Implemented properly with status guards, matching Agent's activation-service pattern.

**Built — schema enhancement + KYC (Blueprint §8.1/8.2/8.3):** `merchants` table gained ~25 fields matching
both the Blueprint's schema and Peak Empowerment's actual onboarding wizard (`legal_name`, `trading_name`,
`registration_number`, `risk_rating`, `settlement_frequency`, `kyc_status`, etc.) New
`merchant_beneficial_owners` and `merchant_documents` tables with list/add endpoints, plus
`PATCH /merchants/:id` for edits. Verified end-to-end via curl against the real running API (onboard →
add owner → add document → list both → update → submit → approve (different user) → activate → suspend →
reactivate).

**Module-by-module status against the Blueprint's 11 modules (§7 of the Merchant PDF):**

1. **Merchant Registry** — done at the schema level. All of §8.1's fields exist except a separate `country`
   column (minor gap). Lifecycle/maker-checker approval already matched the Blueprint before this pass.
2. **Merchant KYC and Due Diligence** — data capture only, **no completion gate at all** (logged as a bug
   in `CHANGELOG.md`, 2026-08-10). Owners/documents can be added, but nothing ever moves `kyc_status` off
   `PENDING`, and `submit()`/`approve()` don't check owner/document counts — a merchant can be fully
   approved with zero KYC data on file. Compare to Agent's `AgentKycService::completeKyc()` (§4 above),
   which does this correctly with a real maker-checker gate. No sanctions/PEP screening integration either
   (just boolean fields filled in by hand), no `compliance-review` endpoint.
3. **Merchant Locations** — done (built 2026-08-07/09, part of the `main` merge). `merchant_locations`
   table + list/add endpoints, matching the pattern later reused for Terminals below.
4. **Merchant Users** — not built. No merchant-scoped user roles. **Flagged as needing a real access-model
   design decision before building** (not a copy-paste of the Locations/Terminals pattern) — unlike those,
   this introduces a *new* access-control dimension: a merchant's own staff (Owner/Admin/Finance/Cashier/
   Viewer/Reconciliation per §Module 4) needing scoped access to only their own merchant's data, which
   nothing else in this codebase does yet (everything else is internal-staff-facing, gated by the existing
   RBAC system).
5. **Merchant Devices and Terminals** — done (built + verified end-to-end 2026-08-10, this session).
   `m_terminals` table (renamed from `merchant_terminals` 2026-08-10; §8.5): `terminal_id`/`serial_number` (unique), `terminal_type`, `provider`,
   `model`, `status` (default `PENDING_ACTIVATION`), `application_version`, `activated_at`/
   `last_heartbeat_at`/`last_transaction_at`, `assigned_by`. `MerchantTerminalService` (assign/activate/
   suspend, mirroring the same status-guard pattern used everywhere else in this codebase). Verified via
   live API calls: assign, list, activate, re-activate rejection, suspend, suspend rejection, reactivate,
   duplicate `terminal_id` rejection.
6. **Merchant Payments** — partial. POS/QR channels are now *correct* (all four bugs above fixed), but
   still only 2 of 7 channels from the Blueprint's channel list exist (missing payment link, transfer
   collection, virtual account, web checkout).
7. **Merchant Fees and Pricing** — not built. No versioned/effective-dated pricing plans.
8. **Merchant Settlement** — partial. `settle()` is now correct (idempotent, branch-derived, balance-safe),
   but not the batch-generate → maker-submit → checker-approve → process workflow from §8/§14.
9. **Reconciliation** — not built.
10. **Disputes, Refunds and Reversals** — not built. No refund or reverse endpoints exist at all.
11. **Merchant Risk and Compliance** — partial. `risk_rating`/`daily_limit`/`monthly_limit` fields exist on
    the schema now, but no actual risk rules, velocity monitoring, or alerting logic runs against them.

**Not built yet, roughly in Blueprint sprint order (§24, MP-04 onward):** Merchant Users (MP-04, needs the
design decision noted above), pricing (MP-06), full Settlement batch workflow (part of MP-06),
reconciliation (MP-07), disputes/refunds (MP-08), TESSA merchant intelligence (MP-09).
