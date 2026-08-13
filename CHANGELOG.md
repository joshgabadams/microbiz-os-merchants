# MicroBiz OS — Bug Report

Original 10 issues found while testing the API in Postman on 2026-07-13. On 2026-07-14, a repo owner commit ("bugs fixes changes made") pulled in a new auth system (Sanctum) plus several new features, and claimed to fix the reported bugs — testing shows that's only partially true. Ordered worst-to-least-serious.

Every issue below has the same root shape: at some point a database table was changed (columns added, or a table left half-built), but the code that reads/writes to it — the model, or the checks on incoming data — was never updated to match. Laravel doesn't always error loudly when this happens; sometimes it just quietly throws away data instead of saving it, which is why these went unnoticed until now.

## Fixed (2026-08-13) — AgentOperationGuard could never find an executed agreement, so every real agent transaction it gates would have been blocked

**In plain terms:** When an agreement actually gets executed through the real workflow, the system marks it `EXECUTED`. But the shared guard that every future agent cash-in/cash-out/transfer is supposed to route through was checking for an agreement with status `ACTIVE` instead. Those two words were never the same value on the same record, so the guard's "does this agent have an active agreement" check would have failed for every agent that went through the real, correct execution flow — even one that's fully signed and legitimately in force. The only reason this hadn't been noticed in testing is that the one agreement sitting at `ACTIVE` in local data (`agent_agreements` id 1, under "Guard Test Agent", agent id 3) was inserted directly as a test fixture (see `attachActiveAgreement()` in the Guard's own test file), bypassing `AgentAgreementService::executeAgreement()` entirely — it never went through the real code path that actually sets `EXECUTED`.

**Where it was:** `app/Services/Payments/AgentOperationGuard.php:73` (`checkAgreementActive()`, was `where('status', 'ACTIVE')`) and `app/Models/Agent.php:93` (the `activeAgreement()` relationship, same wrong literal, currently unused elsewhere but scaffolded for this exact purpose) versus `app/Services/Payments/AgentAgreementService.php:638` (`executeAgreement()`, sets `'status' => 'EXECUTED'`). Also visible on the frontend: `frontend/src/app/features/agents/agent-detail.component.ts` gates the agreement's expanded "Manage" panel on `@if (agreement.status === 'EXECUTED')`, so a real executed agreement — genuinely stored as `EXECUTED` — was showing a blank expanded panel too (confirmed visually before the fix: agent id 3's V1 agreement expanded to nothing).

**Fix applied:** `EXECUTED` was kept as the one canonical value (both the execution service and the frontend template already agreed on it; only the guard and the model relationship disagreed) — changed `AgentOperationGuard::checkAgreementActive()` and `Agent::activeAgreement()` to check `'EXECUTED'` instead of `'ACTIVE'`, and updated `AgentOperationGuardTest::attachActiveAgreement()`'s fixture to match so the test still exercises a realistic record. Also corrected the one already-seeded local dev row (agreement id 1) from `ACTIVE` to `EXECUTED` directly, since it was bad fixture data from before this fix, not something a migration should touch. Confirmed in the browser: the badge now reads "Executed" and the "Manage" panel now renders "Executed ... This agreement is now frozen." instead of a blank row. **Not run against the real PHPUnit suite** — this environment has no local MySQL/Docker available and the suite is hard-configured to require it (`phpunit.xml`); `php -l` confirms no syntax errors on all three changed files, but someone with a MySQL-backed environment should run `php artisan test tests/Feature/AgentOperationGuardTest.php` before this ships.

## Unresolved — cross-team report of a "geoip migration" blocking fresh setups; not reproducible against the shared codebase as of three separate tests (reported ~2026-08-12, investigated 2026-08-13)

**In plain terms:** A concurrent contributor working on the same branch reported that a migration involving `geo_fence_compliant` blocks a plain `php artisan migrate` on a fresh setup, and worked around it locally with a targeted `--path` migration rather than fixing the file itself. This most likely refers to `2026_08_11_000001_add_geoip_fields_to_agent_terminals_table.php` (the Agent Locator migration, which adds columns `->after('geo_fence_compliant')`), since that's the only migration in the codebase referencing that column.

**What was actually tested:** ran a full `php artisan migrate` against a completely fresh, empty SQLite database (isolated from any real dev data) with the full current codebase — three separate times, most recently 2026-08-13 after merging in AG-09/AG-10/the agreement-workflow hardening. All three runs completed with every migration, including this one, applying cleanly with zero errors.

**Where this leaves things:** this codebase's own test suite is explicitly configured to require real MySQL, not SQLite, because several *other* migrations use raw MySQL-only SQL (see the "MySQL-only migration" entries above) — so a clean SQLite result doesn't rule out something MySQL-specific breaking on whoever's actual fresh setup. Without the exact error text (and confirmation of which DB engine was in use), this can't be root-caused further from here — it's not safe to assume either "definitely fixed" or "definitely still broken." Next step is getting the real error message from whoever hit it.

## High — AgentActivationService::activate() is stale dead code now that agreement execution actually works (found 2026-08-11)

**In plain terms:** Once an agent's agreement is executed, their status correctly moves forward through the real lifecycle (`AGREEMENT_PENDING` → `TRAINING_PENDING`, per the rebuilt Agreement workflow). But `activate()` — the function that's supposed to make an agent fully live — still only accepts an agent whose status is exactly `APPROVED`. Since execution moves the agent's status *past* `APPROVED`, there's no path left for `activate()` to ever succeed for an agent who has actually gone through a real agreement. It was only reachable before because Agreement/Training/Terminal genuinely didn't exist yet and nothing advanced status past `APPROVED` — that condition is no longer true.

**Where:** `app/Services/Payments/AgentActivationService.php` — the class's own doc comment already flags this: *"activate() is a staging-only simplification... must not reach production before AG-03/AG-04 land and AgentOperationGuard (§10) is built."* AG-03, AG-04, and AgentOperationGuard have all since landed; this method was never updated to match.

**Fix:** Change the precondition from `status === APPROVED` to `status === TERMINAL_PENDING` (the correct final gate per Blueprint §6 — agreement executed, training completed, and at least one terminal assigned, in that order). Blocked on Training actually existing first (see next section) — currently nothing moves an agent out of `TRAINING_PENDING` at all, so this fix can't be meaningfully tested until that's built.

## Fixed and confirmed locally (2026-07-14)

These were fixed directly in the local environment (not committed/pushed) and verified working end-to-end:

- **Vault creation (was "Creating a Vault through the API doesn't work" below).** `StoreVaultRequest`/`UpdateVaultRequest` were missing validation rules for `code`, `type`, `currency`, `minimum_balance`, `maximum_balance`. Added the rules; confirmed a vault now creates successfully with `code` set. This one was **not** touched by the repo owner's commit — still present in the pulled code until fixed just now.
- **A 500 crash instead of a clean 401 on any unauthenticated request without an `Accept: application/json` header.** Laravel's default behavior tries to redirect unauthenticated requests to a route named `login`; this app never defined one, so it crashed instead of returning a normal 401. Fixed with one line in `bootstrap/app.php` (`$middleware->redirectGuestsTo(null);`), telling the app to never attempt that redirect — always return a clean JSON 401 instead. This is unrelated to the repo owner's commit; it's a gap in the new auth system he just added.
- **Two migrations used MySQL-only syntax** (`ALTER TABLE ... MODIFY ... ENUM(...)`), which crashes on SQLite (this project's own documented default local setup) and blocked every migration after it from running — including the table Sanctum needs to store login tokens, meaning **login was completely non-functional** until this was fixed. Guarded both migrations to only run that statement on MySQL, matching a pattern already used elsewhere in the same set of migrations. Also part of the repo owner's new commit, also not something he tested outside his own MySQL setup.

## Security — actor identity can be spoofed in maker-checker/approval flows (found 2026-07-16, partially fixed 2026-07-22)

**In plain terms:** Several endpoints that record "who did this" — approving a float request, confirming a till balance, closing a branch's end-of-day — were trusting whatever user ID the caller *sent in the request*, instead of checking who was actually logged in. That meant any authenticated user could submit someone else's user ID and have the system record that person as the one who approved/balanced/closed something, even though they never made the call. This defeats the entire point of maker-checker separation (two different people are supposed to be involved) and is a financial-integrity risk, not just a bug.

**Status — partially fixed by the 2026-07-22 pull from main:**
- **Fixed:** `ApprovalController`/`ApprovalRequestService` (float allocate/return requests, approve, reject) — `maker_id`, `checker_id`, and `performed_by` are now all correctly derived from `$request->user()->id` (the authenticated session), never accepted as request fields. Verified directly in the current code.
- **Still open:** three other controllers accept the exact same kind of client-supplied identity field and were not touched by this pull:
  - `app/Http/Controllers/Api/CustomerCashController.php` — `performed_by` (lines 29, 59)
  - `app/Http/Controllers/Api/BalancingController.php` — `balanced_by` (lines 31, 58)
  - `app/Http/Controllers/Api/BranchEodController.php` — `closed_by` (line 26)

**Where (still open):** the three files above — each validates the actor ID as `'required|integer|exists:users,id'` from the request body instead of pulling it from the session.

**Fix (still needed):** apply the same pattern already used correctly in `ApprovalController` and in `TellerController::open()`/`close()` — replace the client-supplied field with `$request->user()->id` in all three remaining controllers.

## High — Merchant KYC has no completion gate at all; a merchant can be approved with zero KYC data on file (found 2026-08-10)

**In plain terms:** For merchants, adding a beneficial owner or a document is just plain data entry right now — nothing ever checks that this data was actually provided, and nothing ever marks KYC as "done." A merchant can be submitted for review and fully approved with no owners and no documents recorded at all. The `kyc_status` field on a merchant's record gets set to `PENDING` once at onboarding and is never touched again by anything in the codebase.

**Where this is different from the Agent module (which does it correctly):** `app/Services/Payments/AgentKycService::completeKyc()` requires the agent to be in `PENDING_KYC` status, requires the reviewer to be someone other than the person who registered the agent (maker-checker), and requires at least one owner **and** one document on file — only then does it advance the agent's status and set `kyc_status = 'COMPLETED'`. There is no equivalent method anywhere for merchants. `app/Services/Payments/MerchantApprovalService::submit()`/`approve()` don't check owner/document counts or `kyc_status` at all.

**Where:** `app/Services/Payments/MerchantKycService.php` (only has `listOwners`/`addOwner`/`listDocuments`/`addDocument`, no completion method), `app/Services/Payments/MerchantApprovalService.php` (`submit`/`approve` have no KYC-completeness check).

**Fix:** Add a `MerchantKycService::completeKyc()` method mirroring `AgentKycService::completeKyc()` exactly (status guard, maker-checker, owner/document count checks, sets `kyc_status = 'COMPLETED'`), and gate `submit()` (or introduce the Blueprint's separate `compliance-review` step) on it being completed first. This also matches a documented gap in the Merchant blueprint itself — Module 2 (KYC) and onboarding Step 4 ("Compliance review", `POST /merchants/{merchant}/compliance-review`) are both still unbuilt.

**Update (2026-08-10) — cross-checked against real CBN regulation, not just our own blueprints:** the "≥1 owner + ≥1 document" check this fix describes (and that `AgentKycService::completeKyc()` already does) isn't actually enough on its own. CBN's Customer Due Diligence Regulations 2023 require materially different documentation for an individual versus a corporate/business entity (board resolution, CAC certificate, MEMART, and a Status Report for corporate accounts, versus BVN/NIN/ID/address for an individual) — a corporate merchant or agent could satisfy our count-based check today with two arbitrary documents that aren't actually any of the CBN-required ones. Both the future Merchant gate and the existing Agent gate should become **type-aware** (branch on `business_type`/`agent_type`), not just count-based.

## Critical — a second SQLite database file the app depends on is never created anywhere, blocking wallet/payment migrations entirely on a fresh deploy (found 2026-08-10)

**In plain terms:** The app actually uses two separate local database files, not one — the main one everyone knows about, and a second one called `mpay.sqlite` that the newer wallet/payment-party tables live in. Nothing in the codebase or deployment process ever creates that second file automatically — no setup script, no migration, nothing. On a developer's own laptop it can silently work if that file happens to already exist from some earlier manual step nobody documented, but on a freshly deployed or rebuilt server, the file simply isn't there, and every migration for that second database fails immediately with "Database file... does not exist."

**Where:** `config/database.php` — the `mpay` connection (line ~47) points at `database_path('mpay.sqlite')` by default. Migrations under `database/migrations/mpay/` (e.g. `2026_07_28_212041_create_payment_parties_table.php`) use this connection. `docker-entrypoint.sh` runs `php artisan migrate --force` on container start but never ensures this file exists first. The main database file doesn't have this problem only because it's separately bind-mounted from persistent host storage (`/home/mohammed/microbiz-storage/database.sqlite`) — no equivalent mount exists for `mpay.sqlite`.

**Confirmed on Contabo (2026-08-10):** after rebuilding the `microbiz-os` container with the latest `main` (commit `e282ce1`), `php artisan migrate` failed immediately on `create_payment_parties_table` with exactly this error, blocking every wallet/payment-party migration after it.

**Fix (two parts, needs a decision before applying):**
1. Immediate unblock: create an empty file at the expected path (`touch database/mpay.sqlite` inside the container) so migrations can proceed right now. Not persistent — lost on the next container rebuild/recreate, same failure will resurface.
2. Proper fix: create the file on persistent host storage (mirroring the existing pattern, e.g. `/home/mohammed/microbiz-storage/mpay.sqlite`) and add a matching bind mount (`-v /home/mohammed/microbiz-storage/mpay.sqlite:/var/www/database/mpay.sqlite`) to the container's run command, so it survives rebuilds like the main database does. Ideally `docker-entrypoint.sh` should also be updated to `touch` this file (and the main one) if missing, so this can't silently block a future fresh deploy again.

## Critical — same MySQL-only migration bug resurfaced in newly-pulled code, blocking every migration after it (found 2026-08-10)

**In plain terms:** This is the exact same problem already fixed twice before (see "Two migrations used MySQL-only syntax" above): a migration writes raw `ALTER TABLE ... MODIFY ... ENUM(...)` SQL, which is MySQL-only syntax and crashes immediately on SQLite (this project's own documented local setup). Because Laravel runs migrations in date order and stops at the first failure, every migration dated after this one is currently stuck as "Pending" and cannot run locally — that includes the tables for wallets, fixed deposits, TESSA alerts, and the new agent beneficial-owners/documents/locations/agreements tables pulled in from `origin/main`. None of that new functionality can be exercised in this environment until it's fixed.

**Where:** `database/migrations/2026_07_30_000002_add_settlement_to_merchant_transaction_type_enum.php` — both `up()` and `down()` run the raw `MODIFY ... ENUM` statement unconditionally.

**Fix:** Guard both methods with the same check already used correctly in the two sibling migrations (`2026_07_05_144435_update_teller_transaction_type_enum.php` and `2026_07_06_222831_update_teller_transactions_transaction_type_enum.php`):
```php
if (DB::connection()->getDriverName() !== 'mysql') {
    return;
}
```
**Workaround:** none — this blocks `php artisan migrate` outright on SQLite; nothing after it in the migration order can be applied until it's fixed.

## High — neither Agent nor Merchant captures a BVN, which CBN requires for both identity verification and terminal traceability (found 2026-08-10, **field added and verified 2026-08-10** — enforcement at the KYC gate still pending)

**In plain terms:** CBN's Agent Banking Guidelines (6 Oct 2025) explicitly require every agent's terminal to be traceable back to that agent's BVN or TIN, and CBN's tiered-KYC/CDD framework requires BVN (and NIN, for individuals) as baseline identity fields generally. Our `agents` table has `registration_number` and `tax_identification_number`, but no `bvn` field at all. Same gap on `merchants`. This isn't a terminal-level schema problem (the terminal already correctly links to its agent) — it's that the agent/merchant record itself has nowhere to store the one identifier CBN specifically names for traceability.

**Where:** `app/Models/Agent.php` / `database/migrations/2026_08_06_000001_create_agents_table.php`, and `app/Models/Merchant.php` / the merchants registry-fields migration — neither has a `bvn` column.

**Fix:** Add a `bvn` field to both tables (nullable initially so it doesn't block existing test data, but should become required at the KYC-completion gate once that's built per the entry above).

**Status (2026-08-10):** field added to both tables (migrations, models, onboarding/update requests, registration services), verified end-to-end — a valid 11-digit BVN saves correctly on both `Agent` and `Merchant` update endpoints, a non-11-digit value is rejected. **Still open:** nothing enforces it's actually present yet — that's the KYC-completion gate, not built today.

## Medium — `agent_terminals.geo_fence_radius_metres` has no default or validation tied to the actual current CBN standard (found 2026-08-10, **fixed 2026-08-10**)

**In plain terms:** When we built the Agent Terminals module today, `geo_fence_radius_metres` was made a plain required integer with no default and no bounds — whoever registers a terminal can enter any number. A CBN circular dated 29 May 2026 revised the enforceable PoS geo-fence radius standard to 70 metres (up from an earlier 10-metre standard), with enforcement now due 1 August 2026. Our test data used 100m arbitrarily, with nothing in the code aware that 70m is the actual regulatory reference point.

**Where:** `app/Http/Requests/Agent/CreateAgentTerminalRequest.php` (`geo_fence_radius_metres` => `['required', 'integer', 'min:1']`, no default, no upper guidance).

**Fix:** Default new terminal registrations to 70m when not explicitly overridden, and consider flagging (not necessarily blocking) registrations that deviate significantly from that standard for a supervisor's attention. Low urgency today given enforcement isn't due until 1 August 2026, but worth fixing before then.

**Status (2026-08-10):** `AgentTerminalService::create()` now defaults `geo_fence_radius_metres` to 70 (a `DEFAULT_GEO_FENCE_RADIUS_METRES` constant) when the caller doesn't specify one; the FormRequest field is `nullable` rather than `required` for input, but the database column itself was never changed and stays `NOT NULL` — every saved row always has a real value, verified via a live API call that omitted the field and got back `"geo_fence_radius_metres":70`.

## High — neither Terminal module blocks assignment on KYC/compliance status; CBN explicitly requires due diligence before POS allocation (found 2026-08-10, **Agent side fixed 2026-08-10; Merchant side still open**)

**In plain terms:** Both CBN's own payments guidance and the Agent Banking Guidelines are explicit that due diligence must be complete *before* a POS terminal is allocated to a merchant or agent. Right now, neither of the Terminal modules built today checks this at all — a merchant or agent still sitting in `DRAFT` (zero KYC data on file) can have a terminal assigned to it with no error, because terminal assignment and KYC/lifecycle status are two completely disconnected pieces of code.

**Where:** `app/Services/Payments/MerchantTerminalService::assign()` and `app/Services/Payments/AgentTerminalService::create()` — neither checks the merchant's/agent's `status` or `kyc_status` before creating the terminal record.

**Fix:** Add a status guard to both (e.g. require the agent to be `ACTIVE`, or at minimum past compliance review; require the merchant to have completed the KYC gate once it's built per the entry above) — mirroring the same "throw a clean Exception if the precondition isn't met" pattern already used everywhere else in both modules.

**Status (2026-08-10) — Agent side fixed:** `AgentTerminalService::create()` now throws unless the agent's status is one of `PENDING_APPROVAL`/`APPROVED`/`AGREEMENT_PENDING`/`TRAINING_PENDING`/`TERMINAL_PENDING`/`ACTIVE` (an allow-list, not `ACTIVE` alone — terminal assignment is itself one of the preconditions for reaching `ACTIVE` per Blueprint §6, so requiring `ACTIVE` first would be circular). Verified: a freshly-registered `DRAFT` agent is correctly blocked ("has not cleared compliance review; cannot assign a terminal"), while the existing `ACTIVE` test agent still succeeds. **Merchant side still open** — blocked on the Merchant KYC-completion gate not existing yet (same dependency as the BVN-enforcement item above); deliberately deferred, not forgotten.

## Medium — PEP and sanctions-match flags are captured but trigger nothing (found 2026-08-10)

**In plain terms:** Both `merchant_beneficial_owners` and `agent_beneficial_owners` have `is_pep`/`sanctions_match` boolean columns, and they can be set to `true` — but nothing in the code reacts to that. There's no case, no block, no review queue, no different handling at all compared to a clean owner. Setting the flag today has exactly the same practical effect as not setting it.

**Where:** `app/Services/Payments/MerchantKycService.php` / `AgentKycService.php` — `addOwner()` in both just stores whatever booleans are passed, no downstream logic reads them anywhere else in the codebase.

**Fix:** At minimum, the future KYC completion gates (Merchant and Agent) should refuse to complete if any owner has `sanctions_match = true` without an explicit compliance override, and should flag (not necessarily block) a `true` `is_pep` for additional review. Not urgent to build the full case-management workflow today, but the gate should at least *notice*.

## Architecture — no unified identity between Agent and Merchant; the same real business can legitimately be both (raised 2026-08-10, deliberately parked, not being fixed today)

**In plain terms:** A real business can be an ordinary merchant (accepting card payments for goods) *and* a banking agent (doing cash-in/cash-out on behalf of MicroBiz MFB) at the same time — these are legally and operationally distinct roles under CBN's own framework, but nothing stops the same real business from being both. Our schema has no way to represent that: `Agent` and `Merchant` are two completely separate tables, each with their own beneficial-owners, documents, and KYC status, with zero shared identity concept. If the same legal business is onboarded as both today, it would be onboarded twice, with duplicated KYC data and no linkage between the two records — the system would have no idea they're the same entity.

**Where:** `app/Models/Agent.php` and `app/Models/Merchant.php` — no shared parent/identity table; `agent_beneficial_owners`/`merchant_beneficial_owners` and `agent_documents`/`merchant_documents` are fully separate, parallel schemas.

**Decision needed (not made yet):** whether to introduce a shared `Customer`/identity concept that `Agent` and `Merchant` both reference (roughly: one core identity record, with a Merchant Profile and/or Agent Profile hanging off it), versus keeping them deliberately separate as a product decision. This is a real architectural fork, not a bug to just patch — **explicitly out of scope for today's work**, parked here so it doesn't get silently forgotten while the smaller KYC-gate/BVN/terminal-gating fixes above get built.

## ⚠ Important caveat — the GL Journals "fix" doesn't actually apply to an existing database

The repo owner's commit edited `database/migrations/2026_06_28_205601_create_gl_journals_table.php` **directly**, adding the missing columns to it, instead of writing a new migration. That only works for someone setting up the database completely from scratch. For any database that had already run this migration before the pull (including ours), Laravel sees the filename is already recorded as "done" and skips it — so the file now looks correct, but the actual table in the database is still just `id`/`created_at`/`updated_at`, and GL posting is **still broken** in practice. Bug #1 below is still open as a result — see that entry for what fixing it properly (a new migration, not an edit) would look like. Reconciliation's migration was not touched at all and remains fully broken too.

---

## Critical — features that are completely broken right now

### 1. GL posting doesn't work at all
**In plain terms:** Whenever the system tries to post a financial entry to the general ledger, it crashes. The database table for ledger entries (`gl_journals`) was created basically empty — it has no columns for the actual entry data (account, amount, reference, etc.). The code that posts entries was written assuming those columns exist, so every single attempt fails. **Still broken** — see the caveat above about why the repo owner's attempted fix doesn't take effect on an existing database.

**Where:** `app/Services/Accounting/GlPostingService.php` (function `postFromCashLedger`), table defined in `database/migrations/2026_06_28_205601_create_gl_journals_table.php`

**Fix:** Write a **new** migration that adds the missing columns to `gl_journals` — `gl_account_id`, `entry_type`, `amount`, `reference`, `source_type`, `source_id`, `posted_at` — matching what `app/Models/GlJournal.php` already expects. Don't edit the original migration file again; anyone who already ran it (like this local setup) won't pick up further edits to it.

### 2. Till/teller reconciliation doesn't work at all
**In plain terms:** Same exact problem as #1, but for reconciling a teller's till at the end of a session. The `reconciliations` table was also created empty, so every reconciliation attempt crashes. **Still broken** — untouched by the recent commit.

**Where:** `app/Services/Accounting/ReconciliationService.php` (function `reconcileSession`), table defined in `database/migrations/2026_06_28_211328_create_reconciliations_table.php`

**Fix:** Add a **new** migration with the missing columns for `reconciliations` — `till_session_id`, `teller_id`, `branch_id`, `system_balance`, `physical_cash`, `variance`, `status`, `reconciled_by`, `approved_by`, `notes`, `reconciled_at` — matching `app/Models/Reconciliation.php`.

---

## High — a real endpoint that can silently break data or crash ungracefully

### 3. Editing a Teller has no safety checks at all
**In plain terms:** Every other "edit" action in the app checks that the data being submitted makes sense (right format, required fields present, etc.) before saving it. Editing a Teller skips this entirely — it takes whatever is sent and saves it directly. Someone could accidentally (or deliberately) submit bad data — like wiping out a teller's unique code — and it would either crash the server with a raw database error, or silently corrupt that teller's record.

**Where:** `app/Http/Controllers/Api/TellerController.php`, the `update` function

**Fix:** Add the same kind of validation that already exists for *creating* a teller (in the same file, the `store` function) to the `update` function too.

---

## Medium — will break the moment someone builds on top of them (not used by anything yet)

### 4. A vault balance update writes to a field that doesn't exist
**In plain terms:** The code that's supposed to update a vault's balance after a transaction has a typo — it tries to save to a field called `last_transaction_at`, but the real field is named `last_transaction_id`. Nothing calls this code yet, so it hasn't crashed in practice, but it will the moment deposits/withdrawals get wired up to it.

**Where:** `app/Services/Vault/VaultBalanceService.php`, lines 53, 78, 103, 128

**Fix:** Change `last_transaction_at` to `last_transaction_id` in all four places.

### 6. An old, seemingly-abandoned "till transactions" system is still set up for a table that was never finished
**In plain terms:** Same root issue as #1 and #2 (a table created without any of its real columns) — the `till_transactions` table was created without any of its actual columns, and the service that would use it (`TillTransactionService`) is an empty file. Note: the repo owner's recent commit added a **separate, similarly-named** `teller_transactions` table and a working `TellerTransactionService` (499 lines) alongside this — that new one looks properly built out. This old `till_transactions`/`TillTransactionService` pair looks like leftover/superseded code at this point rather than something still being actively developed, but it's still present and still broken if anything ever calls it.

**Where:** `database/migrations/2026_06_28_203323_create_till_transactions_table.php`, model at `app/Models/TillTransaction.php`, empty service at `app/Services/Teller/TillTransactionService.php`

**Fix:** Confirm with the repo owner whether `TillTransactionService`/`till_transactions` is dead code that should be deleted (superseded by the new `teller_transactions` system), or something still planned — then either remove it or add the missing columns.

### 7. Two models can't be saved through normal means at all
**In plain terms:** `CashAllocation` and `CashReturn` both have real, working database tables — but nobody finished writing the model code, so Laravel's default safety setting blocks *any* data from being saved to them. Nothing uses these yet, but the moment someone tries, it'll fail outright.

**Where:** `app/Models/CashAllocation.php` and `app/Models/CashReturn.php`

**Fix:** Add a `$fillable` list to both models covering their real columns: `vault_id`, `teller_id`, `amount`, `status`.

---

## Low — minor gaps, nothing crashes, just missing functionality

### 8. Creating a Teller can't set two optional fields
**In plain terms:** When creating a teller, there's no way to link them to a user account or give them a staff code through the API — those two fields are quietly ignored even if you send them. Not urgent since both are optional, but it means that functionality is currently unreachable from outside. **Confirmed still present** after the recent commit — `TellerController::store()` was re-checked and still doesn't accept these two fields.

**Where:** `app/Http/Controllers/Api/TellerController.php`, the `store` function

**Fix:** Add `user_id` and `staff_code` to the list of accepted fields.

### 9. A wrong file path reference (currently harmless)
**In plain terms:** One file points to the wrong location for a helper class. It would cause a crash if used, but the one place that would trigger it is currently commented out, so it's dormant.

**Where:** `app/Listeners/PostFinancialTransactionToGL.php`, line 6 (references `App\Services\Ledger\GlPostingService`, should be `App\Services\Accounting\GlPostingService`)

**Fix:** Correct the import path.

---

## What's confirmed working
`Branch`, `TillSession`, `VaultTransaction`, `VaultLedger`, `User`, and (as of the 2026-07-14 commit) `CashLedger` all have their model code correctly matching the database — no issues found in these.

## Not yet re-verified against the 2026-07-14 commit
Bug #7 (`CashAllocation`/`CashReturn`) above wasn't touched by the recent commit (confirmed via diff), so it's still accurate. Everything else in this list has now been explicitly re-checked against the current code. If more gets pulled from the repo owner later, this file should be re-audited again rather than assumed still accurate.
