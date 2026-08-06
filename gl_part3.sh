#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Payments/MerchantSettlementService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\Merchant;
use App\Models\MerchantBalance;
use App\Models\MerchantTransaction;
use App\Services\Accounting\GlPostingService;
use App\Services\Accounting\CustomerAccountGlResolver;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class MerchantSettlementService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService,
        protected CustomerAccountGlResolver $glResolver
    ) {
    }

    /**
     * @throws Exception
     */
    public function settle(
        Merchant $merchant,
        float $amount,
        int $branchId,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Settlement amount must be greater than zero.');
        }

        if ($merchant->status !== 'ACTIVE') {
            throw new Exception("Merchant {$merchant->merchant_code} is not active.");
        }

        if (! $merchant->customer_account_id) {
            throw new Exception("Merchant {$merchant->merchant_code} has no linked customer account for settlement.");
        }

        return DB::transaction(function () use (
            $merchant,
            $amount,
            $branchId,
            $performedBy,
            $reference,
            $narration
        ) {
            $merchantBalance = MerchantBalance::where('merchant_id', $merchant->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->first();

            if (! $merchantBalance) {
                throw new Exception("Balance record not found for merchant {$merchant->merchant_code}.");
            }

            if ($merchantBalance->available_balance < $amount) {
                throw new Exception('Insufficient merchant balance for settlement.');
            }

            $account = $merchant->customerAccount()->lockForUpdate()->first();

            if (! $account) {
                throw new Exception('Linked customer account not found.');
            }

            $narration = $narration ?? "Settlement payout to merchant {$merchant->merchant_code}";

            $merchantTransaction = MerchantTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_no' => $this->transactionNumberService->generate('MST'),
                'transaction_type' => 'SETTLEMENT',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($merchantTransaction));

            $merchantBalance->ledger_balance -= $amount;
            $merchantBalance->available_balance -= $amount;
            $merchantBalance->last_transaction_id = $merchantTransaction->id;
            $merchantBalance->save();

            $accountBalance = CustomerAccountBalance::where('customer_account_id', $account->id)
                ->lockForUpdate()
                ->first();

            if (! $accountBalance) {
                $accountBalance = CustomerAccountBalance::create([
                    'customer_account_id' => $account->id,
                    'currency' => $account->currency ?? 'NGN',
                    'ledger_balance' => 0,
                    'available_balance' => 0,
                    'locked_balance' => 0,
                ])->refresh();
            }

            $customerAccountTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $account->id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_IN',
                'amount' => $amount,
                'currency' => $account->currency ?? 'NGN',
                'reference' => $reference,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => false,
            ])->refresh();

            event(new FinancialTransactionCreated($customerAccountTransaction));

            $accountBalance->ledger_balance += $amount;
            $accountBalance->available_balance += $amount;
            $accountBalance->last_transaction_id = $customerAccountTransaction->id;
            $accountBalance->save();

            $customerAccountTransaction->update(['posted' => true]);

            $creditKey = $this->glResolver->resolve($account);

            $cashLedger = CashLedger::create([
                'reference_no' => $merchantTransaction->transaction_no,
                'branch_id' => $branchId,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'MERCHANT_SETTLEMENT',
                'source_type' => MerchantTransaction::class,
                'source_id' => $merchantTransaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'MERCHANT_LIABILITY',
                'account_code' => 'MERCHANT_LIABILITY',
                'debit_account_key' => 'MERCHANT_LIABILITY',
                'credit_account_key' => $creditKey,
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $merchantBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $merchantTransaction->update(['posted' => true]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            return [
                'merchant_transaction' => $merchantTransaction->fresh(),
                'customer_account_transaction' => $customerAccountTransaction->fresh(),
                'merchant_balance' => $merchantBalance->fresh(),
                'customer_account_balance' => $accountBalance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }
}
MBOS_EOF

cat > app/Services/Deposits/FixedDepositService.php << 'MBOS_EOF'
<?php

namespace App\Services\Deposits;

use App\Events\FinancialTransactionCreated;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\CustomerAccountTransaction;
use App\Models\FixedDeposit;
use App\Services\Accounting\GlPostingService;
use App\Services\Accounting\CustomerAccountGlResolver;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class FixedDepositService
{
    protected const STANDARD_TENOR_BUCKETS = [60, 90, 180, 270, 365];

    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService,
        protected CustomerAccountGlResolver $glResolver
    ) {
    }

    /**
     * @throws Exception
     */
    public function book(
        CustomerAccount $sourceAccount,
        CustomerAccount $settlementAccount,
        float $principal,
        float $interestRate,
        float $preLiquidationRate,
        float $penaltyFee,
        int $tenorDays,
        int $bookedBy,
        ?string $narration = null
    ): FixedDeposit {
        if ($principal <= 0) {
            throw new Exception('Principal amount must be greater than zero.');
        }

        if ($interestRate < 0 || $preLiquidationRate < 0) {
            throw new Exception('Interest rates cannot be negative.');
        }

        if ($tenorDays < 1) {
            throw new Exception('Tenor must be at least 1 day.');
        }

        if ($sourceAccount->status !== 'ACTIVE') {
            throw new Exception('Source customer account is not active.');
        }

        if ($settlementAccount->status !== 'ACTIVE') {
            throw new Exception('Settlement customer account is not active.');
        }

        return DB::transaction(function () use (
            $sourceAccount,
            $settlementAccount,
            $principal,
            $interestRate,
            $preLiquidationRate,
            $penaltyFee,
            $tenorDays,
            $bookedBy,
            $narration
        ) {
            $sourceBalance = CustomerAccountBalance::where('customer_account_id', $sourceAccount->id)
                ->lockForUpdate()
                ->first();

            if (! $sourceBalance) {
                throw new Exception('Source account balance record not found.');
            }

            if ($sourceBalance->available_balance < $principal) {
                throw new Exception('Insufficient balance to fund this fixed deposit.');
            }

            $narration = $narration ?? 'Fixed deposit booking';
            $startDate = Carbon::today();
            $maturityDate = $startDate->copy()->addDays($tenorDays);

            $fixedDeposit = FixedDeposit::create([
                'fd_no' => $this->generateFdNo(),
                'customer_account_id' => $sourceAccount->id,
                'settlement_account_id' => $settlementAccount->id,
                'principal_amount' => $principal,
                'currency' => 'NGN',
                'interest_rate' => $interestRate,
                'pre_liquidation_rate' => $preLiquidationRate,
                'pre_liquidation_penalty_fee' => $penaltyFee,
                'tenor_days' => $tenorDays,
                'start_date' => $startDate,
                'maturity_date' => $maturityDate,
                'status' => 'ACTIVE',
                'booked_by' => $bookedBy,
                'narration' => $narration,
            ]);

            $debitTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $sourceAccount->id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_OUT',
                'amount' => $principal,
                'currency' => 'NGN',
                'reference' => $fixedDeposit->fd_no,
                'narration' => $narration,
                'performed_by' => $bookedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
            ])->refresh();

            event(new FinancialTransactionCreated($debitTransaction));

            $sourceBalance->ledger_balance -= $principal;
            $sourceBalance->available_balance -= $principal;
            $sourceBalance->last_transaction_id = $debitTransaction->id;
            $sourceBalance->save();

            $sourceGlKey = $this->glResolver->resolve($sourceAccount);
            $fdGlKey = 'FIXED_DEPOSIT_'.$this->tenorBucket($tenorDays);

            $cashLedger = CashLedger::create([
                'reference_no' => $fixedDeposit->fd_no,
                'branch_id' => null,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $bookedBy,
                'transaction_type' => 'FIXED_DEPOSIT_BOOKING',
                'source_type' => FixedDeposit::class,
                'source_id' => $fixedDeposit->id,
                'entry_type' => 'DEBIT',
                'account_type' => $sourceGlKey,
                'account_code' => $sourceGlKey,
                'debit_account_key' => $sourceGlKey,
                'credit_account_key' => $fdGlKey,
                'debit' => $principal,
                'credit' => 0,
                'running_balance' => $sourceBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            return $fixedDeposit->fresh(['sourceAccount', 'settlementAccount']);
        });
    }

    public function previewLiquidation(FixedDeposit $fixedDeposit, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        return $this->calculateLiquidation($fixedDeposit, $asOf);
    }

    /**
     * @throws Exception
     */
    public function liquidate(FixedDeposit $fixedDeposit, int $performedBy, ?string $narration = null): array
    {
        return DB::transaction(function () use ($fixedDeposit, $performedBy, $narration) {

            $fixedDeposit = FixedDeposit::query()
                ->lockForUpdate()
                ->findOrFail($fixedDeposit->id);

            if ($fixedDeposit->status !== 'ACTIVE') {
                throw new Exception("Fixed deposit {$fixedDeposit->fd_no} is already {$fixedDeposit->status}.");
            }

            $calc = $this->calculateLiquidation($fixedDeposit, Carbon::today());

            $settlementBalance = CustomerAccountBalance::where('customer_account_id', $fixedDeposit->settlement_account_id)
                ->lockForUpdate()
                ->first();

            if (! $settlementBalance) {
                throw new Exception('Settlement account balance record not found.');
            }

            $narration = $narration ?? ($calc['is_early']
                ? "Early liquidation of fixed deposit {$fixedDeposit->fd_no}"
                : "Maturity liquidation of fixed deposit {$fixedDeposit->fd_no}");

            $totalPayout = $calc['principal'] + $calc['net_interest'];

            $creditTransaction = CustomerAccountTransaction::create([
                'customer_account_id' => $fixedDeposit->settlement_account_id,
                'transaction_no' => $this->transactionNumberService->generate('CUS'),
                'transaction_type' => 'TRANSFER_IN',
                'amount' => $totalPayout,
                'currency' => 'NGN',
                'reference' => $fixedDeposit->fd_no,
                'narration' => $narration,
                'performed_by' => $performedBy,
                'approved_by' => null,
                'transaction_date' => now(),
                'posted' => true,
            ])->refresh();

            event(new FinancialTransactionCreated($creditTransaction));

            $settlementBalance->ledger_balance += $totalPayout;
            $settlementBalance->available_balance += $totalPayout;
            $settlementBalance->last_transaction_id = $creditTransaction->id;
            $settlementBalance->save();

            $settlementAccount = CustomerAccount::findOrFail($fixedDeposit->settlement_account_id);
            $settlementGlKey = $this->glResolver->resolve($settlementAccount);
            $tenorBucket = $this->tenorBucket($fixedDeposit->tenor_days);
            $fdGlKey = 'FIXED_DEPOSIT_'.$tenorBucket;
            $interestExpenseGlKey = 'INTEREST_EXPENSE_FD_'.$tenorBucket;

            $principalLedger = CashLedger::create([
                'reference_no' => $fixedDeposit->fd_no,
                'branch_id' => null,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_PRINCIPAL',
                'source_type' => FixedDeposit::class,
                'source_id' => $fixedDeposit->id,
                'entry_type' => 'DEBIT',
                'account_type' => $fdGlKey,
                'account_code' => $fdGlKey,
                'debit_account_key' => $fdGlKey,
                'credit_account_key' => $settlementGlKey,
                'debit' => $calc['principal'],
                'credit' => 0,
                'running_balance' => $settlementBalance->ledger_balance,
                'currency' => 'NGN',
                'narration' => $narration,
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => now(),
            ]);

            $this->glPostingService->postFromCashLedger($principalLedger);

            if ($calc['net_interest'] > 0) {
                $interestLedger = CashLedger::create([
                    'reference_no' => $fixedDeposit->fd_no,
                    'branch_id' => null,
                    'vault_id' => null,
                    'teller_id' => null,
                    'user_id' => $performedBy,
                    'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_INTEREST',
                    'source_type' => FixedDeposit::class,
                    'source_id' => $fixedDeposit->id,
                    'entry_type' => 'DEBIT',
                    'account_type' => $interestExpenseGlKey,
                    'account_code' => $interestExpenseGlKey,
                    'debit_account_key' => $interestExpenseGlKey,
                    'credit_account_key' => $settlementGlKey,
                    'debit' => $calc['net_interest'],
                    'credit' => 0,
                    'running_balance' => $settlementBalance->ledger_balance,
                    'currency' => 'NGN',
                    'narration' => $narration,
                    'status' => 'PENDING',
                    'approved_by' => null,
                    'transaction_date' => now(),
                ]);

                $this->glPostingService->postFromCashLedger($interestLedger);
            }

            if ($calc['fee_applied'] > 0) {
                $feeLedger = CashLedger::create([
                    'reference_no' => $fixedDeposit->fd_no,
                    'branch_id' => null,
                    'vault_id' => null,
                    'teller_id' => null,
                    'user_id' => $performedBy,
                    'transaction_type' => 'FIXED_DEPOSIT_LIQUIDATION_FEE',
                    'source_type' => FixedDeposit::class,
                    'source_id' => $fixedDeposit->id,
                    'entry_type' => 'DEBIT',
                    'account_type' => $interestExpenseGlKey,
                    'account_code' => $interestExpenseGlKey,
                    'debit_account_key' => $interestExpenseGlKey,
                    'credit_account_key' => 'COMMISSION_INCOME',
                    'debit' => $calc['fee_applied'],
                    'credit' => 0,
                    'running_balance' => 0,
                    'currency' => 'NGN',
                    'narration' => "Early liquidation penalty fee on {$fixedDeposit->fd_no}",
                    'status' => 'PENDING',
                    'approved_by' => null,
                    'transaction_date' => now(),
                ]);

                $this->glPostingService->postFromCashLedger($feeLedger);
            }

            $fixedDeposit->update([
                'status' => $calc['is_early'] ? 'LIQUIDATED_EARLY' : 'LIQUIDATED_AT_MATURITY',
                'liquidated_by' => $performedBy,
                'liquidated_at' => now(),
                'interest_paid' => $calc['net_interest'],
            ]);

            return [
                'fixed_deposit' => $fixedDeposit->fresh(),
                'settlement_transaction' => $creditTransaction->fresh(),
                'settlement_balance' => $settlementBalance->fresh(),
                'calculation' => $calc,
            ];
        });
    }

    protected function calculateLiquidation(FixedDeposit $fixedDeposit, Carbon $asOf): array
    {
        $principal = (float) $fixedDeposit->principal_amount;
        $startDate = Carbon::parse($fixedDeposit->start_date);
        $maturityDate = Carbon::parse($fixedDeposit->maturity_date);

        $isEarly = $asOf->lt($maturityDate);

        if ($isEarly) {
            $elapsedDays = max(0, $startDate->diffInDays($asOf));
            $rate = (float) $fixedDeposit->pre_liquidation_rate;
            $grossInterest = $principal * ($rate / 100) * ($elapsedDays / 365);

            $fee = min((float) $fixedDeposit->pre_liquidation_penalty_fee, $grossInterest);
            $netInterest = round($grossInterest - $fee, 2);

            return [
                'is_early' => true,
                'elapsed_days' => $elapsedDays,
                'rate_applied' => $rate,
                'principal' => $principal,
                'gross_interest' => round($grossInterest, 2),
                'fee_applied' => round($fee, 2),
                'net_interest' => max(0, $netInterest),
                'total_payout' => $principal + max(0, $netInterest),
            ];
        }

        $elapsedDays = $fixedDeposit->tenor_days;
        $rate = (float) $fixedDeposit->interest_rate;
        $grossInterest = round($principal * ($rate / 100) * ($elapsedDays / 365), 2);

        return [
            'is_early' => false,
            'elapsed_days' => $elapsedDays,
            'rate_applied' => $rate,
            'principal' => $principal,
            'gross_interest' => $grossInterest,
            'fee_applied' => 0,
            'net_interest' => $grossInterest,
            'total_payout' => $principal + $grossInterest,
        ];
    }

    protected function tenorBucket(int $tenorDays): int
    {
        $closest = self::STANDARD_TENOR_BUCKETS[0];
        $minDiff = abs($tenorDays - $closest);

        foreach (self::STANDARD_TENOR_BUCKETS as $bucket) {
            $diff = abs($tenorDays - $bucket);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $bucket;
            }
        }

        return $closest;
    }

    protected function generateFdNo(): string
    {
        do {
            $fdNo = 'FD-'.strtoupper(Str::random(8));
        } while (FixedDeposit::where('fd_no', $fdNo)->exists());

        return $fdNo;
    }
}
MBOS_EOF

echo "Part 3 of 4 applied (MerchantSettlementService, FixedDepositService)."