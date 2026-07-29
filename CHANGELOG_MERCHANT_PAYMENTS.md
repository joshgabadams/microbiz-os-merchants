# Merchant Payments (M-PAY) — First Domain — Changelog

This is the first real capability inside the **Payments domain**, following
the modular monolith decision: no new Laravel app, just new folders and
tables inside this same `microbiz-os` repo, reusing the RBAC/audit/GL
infrastructure already built for Finance.

## Two things corrected before writing any code

1. **`app/Domain/Finance` is unused scaffold** (confirmed: nothing else in
   the codebase references `TransactionProcessor`, `FinancialTransactionService`,
   or `FinancialTransactionData`). Per your decision, this Merchant Payments
   code is built on the **proven pattern** instead — the same shape as
   `TellerTransactionService` / `VaultTransactionService` / `CustomerCashService`:
   explicit Eloquent creates, `DB::transaction()`, domain events. The
   `Domain/Finance` files are left untouched, just not built upon.

2. **I initially misreported a "live 403 bug"** in `OpenTellerRequest` /
   `CloseTellerRequest` — that was wrong. I'd checked a different, unrelated
   file (`Customer/DepositRequest.php`, which genuinely is broken but also
   genuinely unused) and incorrectly generalized. I verified properly this
   time: the **only four** `FormRequest` classes actually wired into a live
   controller (`OpenTellerRequest`, `CloseTellerRequest`, `StoreVaultRequest`,
   `UpdateVaultRequest`) are all correctly implemented. No live bug existed.
   My apologies for the false alarm — flagging it here so the record's clear.

## What's new

| File | Purpose |
|---|---|
| `database/migrations/..._create_merchants_table.php` | Merchant registry: code, business name, contact info, branch, status |
| `database/migrations/..._create_merchant_balances_table.php` | Per-merchant running balance, mirrors `teller_balances` exactly (`ledger_balance`/`available_balance`/`locked_balance`) |
| `database/migrations/..._create_merchant_transactions_table.php` | QR/POS collection records, mirrors `teller_transactions` + reversal fields |
| `app/Models/Merchant.php`, `MerchantBalance.php`, `MerchantTransaction.php` | Standard Eloquent models, same conventions as `Teller`/`TellerBalance`/`TellerTransaction` |
| `app/Services/Payments/MerchantOnboardingService.php` | Onboards a merchant, generates a unique `merchant_code`, creates its balance record |
| `app/Services/Payments/MerchantPaymentService.php` | The core logic: `collectQrPayment()` / `collectPosPayment()`, both routing through a shared `collect()` method |
| `app/Http/Requests/Merchant/*.php` | Real `authorize() => true` + real validation rules — verified against the working convention, not the broken one |
| `app/Http/Controllers/Api/MerchantController.php` | `index`, `show`, `onboard`, `collectQr`, `collectPos` — same `ApiResponse` trait / try-catch shape as `TellerController` |
| `database/seeders/PaymentsRbacSeeder.php` | `wallets.manage`, `merchants.onboard`, `payments.process`, `agency_banking.manage` permissions + 3 roles |

## GL treatment — the one real design decision made on your behalf

Money collected via QR/POS on a merchant's behalf is:
- **Debited to `SUSPENSE_ASSET`** (value received into the system, pending settlement/reconciliation — same account already used for other pending flows)
- **Credited to a new `MERCHANT_LIABILITY` account** (`gl_code` 400170) — what the bank now owes the merchant, pending payout. This mirrors how `WALLET_LIABILITY` already models money owed to wallet holders.

This required adding `MERCHANT_LIABILITY` to both `config/gl.php` and
`GlAccountSeeder.php`. Unlike `CustomerCashService`'s dangling
`CUSTOMER_DEPOSIT_CONTROL` key (which doesn't exist in `config/gl.php` at
all — harmless only because that code path never actually calls
`GlPostingService`), `MerchantPaymentService` **does** call
`GlPostingService::postFromCashLedger()` for real, so both GL keys it uses
had to actually exist and resolve correctly.

**Also found:** `GlAccountSeeder` isn't called from `DatabaseSeeder` at all
— it seems to be run manually/separately. You'll need to run it explicitly
to pick up the new `MERCHANT_LIABILITY` account:
```bash
php artisan db:seed --class="Database\Seeders\GlAccountSeeder"
```

## Event wiring

`MerchantPaymentService::collect()` fires `FinancialTransactionCreated`
right after the `MerchantTransaction` is created, then calls
`GlPostingService::postFromCashLedger()`, which fires
`FinancialTransactionPosted` automatically — the same two-listener chain
(`UpdateAuditTrail`, `NotifyFinancialUsers`) wired up for Finance now covers
Merchant Payments too, with zero additional listener code needed.

## Real enforcement, applied for the first time

The three new merchant routes use `permission:merchants.onboard` and
`permission:payments.process` middleware for real — this is the first
place in the project RBAC is actually enforced at the route level, since
it's new functionality with no existing behavior to break. Existing routes
(teller, vault, customer cash) are untouched and still unprotected — that's
a separate decision for you to make when ready.

## How to merge

```bash
cd /Users/user/microbiz/microbiz-os
cp -r ~/Downloads/microbiz-os-payments/* .
php artisan migrate
php artisan db:seed --class="Database\Seeders\PaymentsRbacSeeder"
php artisan db:seed --class="Database\Seeders\GlAccountSeeder"
```
(`PaymentsRbacSeeder` also now runs automatically as part of
`php artisan db:seed` / `migrate:fresh --seed`, since it's called from
`DatabaseSeeder`.)

## Testing it

Assign a user the `payments-officer` or `merchant-support` role (seeded by
`PaymentsRbacSeeder`), then:
```bash
php artisan tinker
```
```php
$user = \App\Models\User::first();
$user->roles()->attach(\App\Models\Role::where('name','merchant-support')->first());

// via Tinker directly, bypassing HTTP for a quick smoke test:
$merchant = app(\App\Services\Payments\MerchantOnboardingService::class)
    ->onboard(['business_name' => 'Test Merchant'], $user->id);

$result = app(\App\Services\Payments\MerchantPaymentService::class)
    ->collectQrPayment($merchant, 5000, branchId: 1, performedBy: $user->id);

\App\Models\AuditLog::latest()->first();
\App\Models\NotificationLog::latest()->first();
```
(Adjust `branchId: 1` to a real branch ID in your data.)

## Still open

- Merchant **settlement/payout** (moving money from `MERCHANT_LIABILITY` out
  to the merchant's actual bank account) isn't built — collection and
  settlement are different capabilities, and settlement needs its own
  product decision (payout rails, timing/batching) before I'd build it.
- Wallets and Agency Banking (the other two M-PAY capabilities from the
  handbook) haven't been started — Merchant Payments was the one you chose
  to build first.
