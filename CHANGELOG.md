# MicroBiz OS — Bug Report

Original 10 issues found while testing the API in Postman on 2026-07-13. On 2026-07-14, a repo owner commit ("bugs fixes changes made") pulled in a new auth system (Sanctum) plus several new features, and claimed to fix the reported bugs — testing shows that's only partially true. Ordered worst-to-least-serious.

Every issue below has the same root shape: at some point a database table was changed (columns added, or a table left half-built), but the code that reads/writes to it — the model, or the checks on incoming data — was never updated to match. Laravel doesn't always error loudly when this happens; sometimes it just quietly throws away data instead of saving it, which is why these went unnoticed until now.

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
