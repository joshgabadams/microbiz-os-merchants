<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Wallet top-up and wallet-to-wallet transfer, enforcing CBN's
 * three-tiered KYC daily-outflow and balance-cap limits (see config/wallet.php
 * -- confirm the exact figures with compliance before relying on them).
 *
 * GL treatment for top-up: debits SUSPENSE_ASSET (cash received pending
 * reconciliation, same pattern as Merchant Payments), credits
 * WALLET_LIABILITY (what the bank now owes the wallet holder).
 *
 * Wallet-to-wallet transfers do NOT post to the GL at all: both sides are
 * sub-accounts of the same WALLET_LIABILITY account, so the aggregate GL
 * balance is unchanged by a transfer between them. The full audit trail
 * lives in the two WalletTransaction rows and their
 * FinancialTransactionCreated events instead.
 */
class WalletService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function topUp(
        Wallet $wallet,
        float $amount,
        int $branchId,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Top-up amount must be greater than zero.');
        }

        if ($wallet->status !== 'ACTIVE') {
            throw new Exception("Wallet {$wallet->wallet_no} is not active.");
        }

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $branchId,
            $performedBy,
            $reference,
            $narration
        ) {
            $balance = WalletBalance::where('wallet_id', $wallet->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception("Balance record not found for wallet {$wallet->wallet_no}.");
            }

            $this->enforceBalanceCap($wallet, $balance->ledger_balance + $amount);

            $narration = $narration ?? 'Wallet top-up';

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->transactionNumberService->generate('WAL'),
                'transaction_type' => 'TOPUP',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($transaction));

            $balance->ledger_balance += $amount;
            $balance->available_balance += $amount;
            $balance->last_transaction_id = $transaction->id;
            $balance->save();

            $cashLedger = CashLedger::create([
                'reference_no' => $transaction->transaction_no,
                'branch_id' => $branchId,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'WALLET_TOPUP',
                'source_type' => WalletTransaction::class,
                'source_id' => $transaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'SUSPENSE_ASSET',
                'account_code' => 'SUSPENSE_ASSET',
                'debit_account_key' => 'SUSPENSE_ASSET',
                'credit_account_key' => 'WALLET_LIABILITY',
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $balance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $transaction->update(['posted' => true]);

            // Fires FinancialTransactionPosted internally once the GL entry balances.
            $this->glPostingService->postFromCashLedger($cashLedger);

            return [
                'transaction' => $transaction->fresh(),
                'balance' => $balance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }

    /**
     * @throws Exception
     */
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Transfer amount must be greater than zero.');
        }

        if ($fromWallet->id === $toWallet->id) {
            throw new Exception('Cannot transfer a wallet to itself.');
        }

        if ($fromWallet->status !== 'ACTIVE') {
            throw new Exception("Source wallet {$fromWallet->wallet_no} is not active.");
        }

        if ($toWallet->status !== 'ACTIVE') {
            throw new Exception("Destination wallet {$toWallet->wallet_no} is not active.");
        }

        return DB::transaction(function () use (
            $fromWallet,
            $toWallet,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            // Lock both balance rows in a consistent order (lower wallet_id
            // first) regardless of transfer direction, to avoid deadlocks
            // if two transfers between the same pair of wallets run at once.
            $orderedIds = $fromWallet->id < $toWallet->id
                ? [$fromWallet->id, $toWallet->id]
                : [$toWallet->id, $fromWallet->id];

            $balances = WalletBalance::whereIn('wallet_id', $orderedIds)
                ->where('currency', 'NGN')
                ->orderBy('wallet_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('wallet_id');

            $fromBalance = $balances->get($fromWallet->id);
            $toBalance = $balances->get($toWallet->id);

            if (! $fromBalance || ! $toBalance) {
                throw new Exception('Wallet balance not found for one or both wallets.');
            }

            if ($fromBalance->available_balance < $amount) {
                throw new Exception('Insufficient wallet balance.');
            }

            $this->enforceDailyOutflowLimit($fromWallet, $amount);
            $this->enforceBalanceCap($toWallet, $toBalance->ledger_balance + $amount);

            $narration = $narration ?? "Transfer to wallet {$toWallet->wallet_no}";

            $outTransaction = WalletTransaction::create([
                'wallet_id' => $fromWallet->id,
                'transaction_no' => $this->transactionNumberService->generate('WAL'),
                'transaction_type' => 'TRANSFER_OUT',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'counterparty_wallet_id' => $toWallet->id,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($outTransaction));

            $fromBalance->ledger_balance -= $amount;
            $fromBalance->available_balance -= $amount;
            $fromBalance->last_transaction_id = $outTransaction->id;
            $fromBalance->save();

            $inTransaction = WalletTransaction::create([
                'wallet_id' => $toWallet->id,
                'transaction_no' => $this->transactionNumberService->generate('WAL'),
                'transaction_type' => 'TRANSFER_IN',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'counterparty_wallet_id' => $fromWallet->id,
                'transaction_date' => now(),
                'posted' => true,
            ])->refresh();

            event(new FinancialTransactionCreated($inTransaction));

            $toBalance->ledger_balance += $amount;
            $toBalance->available_balance += $amount;
            $toBalance->last_transaction_id = $inTransaction->id;
            $toBalance->save();

            $outTransaction->update(['posted' => true]);

            return [
                'out_transaction' => $outTransaction->fresh(),
                'in_transaction' => $inTransaction->fresh(),
                'from_balance' => $fromBalance->fresh(),
                'to_balance' => $toBalance->fresh(),
            ];
        });
    }

    /**
     * @throws Exception
     */
    protected function enforceBalanceCap(Wallet $wallet, float $prospectiveBalance): void
    {
        $cap = config("wallet.tiers.{$wallet->kyc_tier}.balance_cap");

        if ($cap !== null && $prospectiveBalance > $cap) {
            throw new Exception("This would exceed the Tier {$wallet->kyc_tier} balance cap of {$cap}.");
        }
    }

    /**
     * @throws Exception
     */
    protected function enforceDailyOutflowLimit(Wallet $wallet, float $amount): void
    {
        $limit = config("wallet.tiers.{$wallet->kyc_tier}.daily_limit");

        if ($limit === null) {
            return;
        }

        $todayOutflow = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('transaction_type', 'TRANSFER_OUT')
            ->whereDate('transaction_date', now()->toDateString())
            ->sum('amount');

        if (($todayOutflow + $amount) > $limit) {
            throw new Exception("This would exceed the Tier {$wallet->kyc_tier} daily outflow limit of {$limit}.");
        }
    }
}
