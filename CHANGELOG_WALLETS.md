# Wallets — Changelog

## Regulatory basis for the design decisions below

Before writing any code, I searched for current CBN regulation rather than
guess at money-movement/KYC rules for a live financial system. Two things
materially shaped this design:

1. **CBN Agent Banking Guidelines (Oct 6, 2025)** explicitly require agent
   banking to be clearly separated from merchant activity, with agents
   transacting only through a dedicated agent account/wallet. This is why
   Wallets here are built as a **third, distinct entity** -- not layered on
   top of `Merchant` or reusing its GL account. When Agency Banking gets
   built later, it needs its own account type too (the `AGENCY_FLOAT` GL
   account already exists in your seed data, suggesting this was already
   anticipated).

2. **CBN's three-tiered KYC framework** for wallets: Tier 1 (BVN or NIN),
   Tier 2/3 (both BVN and NIN), each with daily outflow limits and balance
   caps. **Sources gave slightly different exact figures for Tier 1/2** --
   the tiered *structure* is settled, but confirm the current numbers with
   compliance before relying on `config/wallet.php` in production. CBN
   circulars amend these periodically.

## Product decisions confirmed before building

- **Owner:** a separate, lighter identity (not `Customer`) -- Tier 1's
  purpose is onboarding people without a full bank account.
- **Top-up:** cash-in via teller, mirroring the proven Merchant Payments
  pattern. Agent-based top-up deferred until Agency Banking exists with
  its required regulatory separation.
- **Transfers:** wallet-to-wallet only, to start.

## New files

| File | Purpose |
|---|---|
| `config/wallet.php` | Tier limits (**confirm figures with compliance**) |
| `database/migrations/..._create_wallets_table.php` | `wallet_no`, `owner_name`, `phone`, `bvn`/`nin` (nullable, validated in the service layer), `kyc_tier` |
| `database/migrations/..._create_wallet_balances_table.php` | Mirrors `merchant_balances` exactly |
| `database/migrations/..._create_wallet_transactions_table.php` | `TOPUP`/`TRANSFER_OUT`/`TRANSFER_IN`/`REVERSAL`/`ADJUSTMENT`, plus `counterparty_wallet_id` so a wallet's own transaction list shows who money moved to/from |
| `app/Models/Wallet.php`, `WalletBalance.php`, `WalletTransaction.php` | `bvn`/`nin` are in `$hidden` on `Wallet` -- sensitive PII shouldn't leak into API JSON by default |
| `app/Services/Payments/WalletOnboardingService.php` | Enforces the BVN/NIN-per-tier rule at onboarding |
| `app/Services/Payments/WalletService.php` | `topUp()` and `transfer()`, both enforcing tier limits |
| `app/Http/Requests/Wallet/*.php`, `WalletController.php`, routes | Same verified conventions as Merchant Payments |

## Why wallet-to-wallet transfers post no GL entry

Both sides of a transfer are sub-accounts of the same `WALLET_LIABILITY`
GL account. A transfer moves value between two wallets' own sub-ledgers,
but the *aggregate* `WALLET_LIABILITY` balance the GL tracks doesn't
change at all -- so there's nothing meaningful to post. The full audit
trail lives in the two `WalletTransaction` rows (`TRANSFER_OUT` /
`TRANSFER_IN`, linked via `counterparty_wallet_id`) and their
`FinancialTransactionCreated` events. Top-ups **do** post to the GL
(debit `SUSPENSE_ASSET`, credit `WALLET_LIABILITY`), since that's real
cash entering the bank.

## Tier limit enforcement

- **Balance cap** (`enforceBalanceCap`): checked on both top-up and the
  *receiving* side of a transfer -- a wallet can never exceed its tier's
  cumulative balance cap regardless of how the money arrived.
- **Daily outflow limit** (`enforceDailyOutflowLimit`): checked on the
  *sending* side of a transfer only, summing today's `TRANSFER_OUT` rows
  for that wallet -- matches CBN's own framing of the limit as an
  "outflow" limit, not applying to top-ups.
- Both throw a clear `Exception` if the operation would breach the limit;
  neither silently caps or truncates the amount.

## How to merge

```bash
cd /Users/user/microbiz/microbiz-os
cp -r ~/Downloads/microbiz-os-wallets/* .
php artisan migrate
```
(No new seeder needed -- `wallets.manage` permission and the
`payments-officer` role that has it already exist from the earlier
Merchant Payments merge.)

## Testing it

```bash
php artisan tinker
```
```php
$user = \App\Models\User::first();
$branch = \App\Models\Branch::first();

// Onboard two Tier 1 wallets
$walletA = app(\App\Services\Payments\WalletOnboardingService::class)
    ->onboard(['owner_name' => 'Alice', 'phone' => '08010000001', 'bvn' => '12345678901'], $user->id);
$walletB = app(\App\Services\Payments\WalletOnboardingService::class)
    ->onboard(['owner_name' => 'Bob', 'phone' => '08010000002', 'nin' => '10987654321'], $user->id);

// Top up wallet A
$topup = app(\App\Services\Payments\WalletService::class)
    ->topUp($walletA, 20000, $branch->id, $user->id);
$topup['balance']->available_balance; // 20000.00

// Transfer A -> B
$transfer = app(\App\Services\Payments\WalletService::class)
    ->transfer($walletA, $walletB, 5000, $user->id);
$transfer['from_balance']->available_balance; // 15000.00
$transfer['to_balance']->available_balance;   // 5000.00

// Confirm tier limit enforcement actually works -- this should throw,
// since 20000 top-up already exceeds nothing, but try a top-up that
// would breach the Tier 1 balance cap (300,000):
app(\App\Services\Payments\WalletService::class)->topUp($walletA, 500000, $branch->id, $user->id);
// Exception: "This would exceed the Tier 1 balance cap of 300000."
```

Then confirm the audit trail (should be exactly 4 new rows for the setup
above: 2x `wallet.created` via onboarding -- wait, onboarding doesn't fire
an event; only `topUp`/`transfer` do -- so expect 1 for the top-up's
`WalletTransaction` created + 1 `cash_ledger.posted`, then 2 for the
transfer's `TRANSFER_OUT`/`TRANSFER_IN` `WalletTransaction` rows, no GL
entry for the transfer):
```php
\App\Models\AuditLog::latest()->take(4)->get(['action', 'subject_type'])->toArray();
```

## Still open

- **Wallet onboarding doesn't fire an audit event** -- unlike Merchant
  onboarding, `WalletOnboardingService::onboard()` doesn't dispatch
  `FinancialTransactionCreated`, since a `Wallet` isn't itself a
  transaction. If you want an audit trail on wallet *creation* too (not
  just its transactions), that's a small addition using `AuditLogger`
  directly rather than the financial-transaction event chain.
- **Agency Banking** -- still not built, and per the CBN guidelines
  research above, must not share a ledger/account structure with either
  Merchants or Wallets when it is built.
- **`permission:*` middleware on older Finance routes** -- still the one
  remaining item from the earlier list.
