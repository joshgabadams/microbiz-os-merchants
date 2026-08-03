# Merchant Settlement/Payout — Changelog

## Product decisions this was built against

- **Settlement method:** credits the merchant's own `customer_account`
  within this system (not an external bank transfer).
- **Timing/batching:** not yet decided. This is deliberately built as the
  **manual/on-demand core movement** -- whatever scheduling/batching gets
  decided later would just call this same `MerchantSettlementService::settle()`
  on a cron, not require rebuilding the money-movement logic itself.

## New files

| File | Purpose |
|---|---|
| `database/migrations/..._add_customer_account_id_to_merchants_table.php` | Links each merchant to the `customer_account` settlement credits land in (nullable -- not every merchant has one set up yet) |
| `database/migrations/..._add_settlement_to_merchant_transaction_type_enum.php` | Adds `SETTLEMENT` to `merchant_transactions.transaction_type`, mirroring your existing enum-update migration style |
| `app/Services/Payments/MerchantSettlementService.php` | The core logic |
| `app/Http/Requests/Merchant/SettleMerchantRequest.php` | Real validation (`authorize() => true`, real rules -- verified convention) |

## Modified files

| File | Change |
|---|---|
| `app/Models/Merchant.php` | Added `customer_account_id` to fillable, added `customerAccount()` relationship |
| `app/Http/Controllers/Api/MerchantController.php` | Added `settle()` endpoint |
| `routes/api.php` | Added `POST /merchants/settle`, gated by its **own** permission (`merchants.settle`), separate from `merchants.onboard` -- settlement moves real money and deserves its own access boundary |
| `database/seeders/PaymentsRbacSeeder.php` | Added `merchants.settle` permission and a dedicated `merchant-settlement-officer` role (not folded into `merchant-support`, which only handles onboarding/collection) |

## How settlement actually works

1. Validates the merchant has a linked `customer_account` and sufficient
   `MerchantBalance.available_balance`.
2. Reduces the merchant's balance, records a `MerchantTransaction`
   (`type: SETTLEMENT`) -- fires `FinancialTransactionCreated`.
3. Credits the linked `CustomerAccountBalance` directly and records a
   `CustomerAccountTransaction` (`type: TRANSFER_IN`, **not** `CASH_DEPOSIT`
   -- deliberately not reusing `CustomerAccountService::deposit()`, since
   that hardcodes `CASH_DEPOSIT`, which would misrepresent an internal
   transfer as new cash entering the bank) -- fires `FinancialTransactionCreated`.
4. Posts a `CashLedger` entry: **debits `MERCHANT_LIABILITY`**, **credits
   `CUSTOMER_SAVINGS` or `CUSTOMER_CURRENT`** (based on the account's
   `account_type`) -- an internal transfer between two liability accounts,
   not new cash in or out of the bank. Goes through
   `GlPostingService::postFromCashLedger()` properly (not the
   bypass-the-service pattern seen in some older code), so
   `FinancialTransactionPosted` fires automatically.

## How to merge

```bash
cd /Users/user/microbiz/microbiz-os
cp -r ~/Downloads/microbiz-os-settlement/* .
php artisan migrate
php artisan db:seed --class="Database\Seeders\PaymentsRbacSeeder"
```

## Testing it

You'll need a merchant with a linked `customer_account_id`. In Tinker:
```php
$merchant = \App\Models\Merchant::first();
$account = \App\Models\CustomerAccount::first();
$merchant->update(['customer_account_id' => $account->id]);

$user = \App\Models\User::first();
$branch = \App\Models\Branch::first();

$result = app(\App\Services\Payments\MerchantSettlementService::class)
    ->settle($merchant, 1000, $branch->id, $user->id);

$result['merchant_balance'];       // available_balance should drop by 1000
$result['customer_account_balance']; // available_balance should rise by 1000
```

Then verify no double-firing (this project has hit that bug once already,
in `UpdateAuditTrail`/`NotifyFinancialUsers` -- already fixed, but worth
re-confirming on new code paths):
```php
\App\Models\AuditLog::latest()->take(3)->get(['action', 'subject_type']);
```
Should show exactly 3 new rows: `merchant_transaction.created`,
`customer_account_transaction.created`, `cash_ledger.posted` -- each once.

## Still open

- **Batching/scheduling** -- not decided yet, as noted above. When you
  decide, it's a thin layer that calls `settle()` per merchant, not a
  rewrite.
- **Partial vs. full settlement policy** -- this allows settling any amount
  up to the available balance; if you want "settle everything" or a
  minimum-threshold rule, that's a product decision layered on top.
- **Reversal of a settlement** -- not built. Would follow the same pattern
  as `TransactionReversalService`, but for merchant settlements specifically.
