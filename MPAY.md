# M-Pay Module — Build Log & Developer Guide

| | |
|---|---|
| Status | Foundation in progress — Sprint AG-01 (Agent Registry) complete per the M-PAY Agency Banking Blueprint |
| Location | `app/Domain/MPay/` (code) + `database/migrations/mpay/` (schema), inside the `microbiz-os` repo |
| Last updated | 2026-08-06 |
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

**Not built yet, in Blueprint order** (AG-02 through AG-13 — see Blueprint §7/§21 for full field lists):
1. **AG-02 — KYC and Approval**: identity/business docs, beneficial owners, sanctions/PEP screening —
   currently `kyc_status` is just a column with no real workflow behind it
2. **AG-03 — Locations and Agreements**: `agent_locations` and `agent_agreements` tables (Blueprint §8.2/§8.3)
3. **AG-04 — Operators and Terminals**: `agent_operators` and `agent_terminals` tables (Blueprint §8.4/§8.5)
   — note `agent_terminals`' real schema (geo-fence radius, device cert, heartbeat, etc.) is much richer
   than the old `pos_terminals` table that was removed in the rework
4. AG-05 (geo-fencing/security), AG-06 (float), AG-07/AG-08 (cash-in/cash-out), AG-09 (transfers/bills),
   AG-10 (commission), AG-11 (reconciliation/EOD), AG-12 (supervision/complaints), AG-13 (TESSA)

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

As of 2026-07-29, `main` has its own payment-collection feature (`app/Models/Merchant.php`,
`MerchantBalance.php`, `MerchantTransaction.php`, `app/Services/Payments/MerchantPaymentService.php`,
migrations for `merchants`/`merchant_balances`/`merchant_transactions`), introduced in a commit titled
"changes for bug fixes, and m-pay merger." This was built independently of the module described in this
file, and the two are **not the same thing** — but they represent a real fork in direction that needs a
decision, not silent parallel development.

**What exists on `main`, factually:**
- Lives entirely in the *main* database — `merchants`/`merchant_balances`/`merchant_transactions` have real
  foreign keys straight into `branches`, `users`, and `CashLedger`. It's built as a direct extension of
  FinCore, not an isolated payments layer.
- Amounts are `decimal(24,2)` — actual decimals, not integer minor units. This violates the Build Guide's
  own money rule (the same guide this feature's commit message references).
- `MerchantPaymentService::collect()` writes to `CashLedger` and calls
  `GlPostingService::postFromCashLedger()` **synchronously**, in the same request that creates the
  transaction.
- No idempotency handling — no replay guard against a duplicate/retried request.

**Immediate issue:** `collect()`'s synchronous GL post means every merchant collection attempt hits the
*exact* `gl_journals` schema bug already tracked as bug #1 in `CHANGELOG.md`. This feature is not
functional right now, for the same root cause as everything else that touches GL posting on this
database. Fixing that bug fixes this too — it isn't a separate problem to solve twice.

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
