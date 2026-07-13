<?php

namespace App\Services\Vault;

use App\Models\Vault;
use App\Models\VaultCashBalance;
use App\Models\VaultTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class VaultBalancingService
{
    public function balance(
        Vault $vault,
        string $businessDate,
        float $physicalCash,
        int $balancedBy,
        ?string $note = null
    ): VaultCashBalance {
        if (!$vault->active) {
            throw new Exception('Vault is inactive.');
        }

        return DB::transaction(function () use (
            $vault,
            $businessDate,
            $physicalCash,
            $balancedBy,
            $note
        ) {
            $vaultDeposits = $this->sumVaultTransactions($vault, $businessDate, 'DEPOSIT');
            $vaultWithdrawals = $this->sumVaultTransactions($vault, $businessDate, 'WITHDRAWAL');

            $floatAllocated = VaultTransaction::where('vault_id', $vault->id)
                ->where('transaction_type', 'WITHDRAWAL')
                ->whereDate('transaction_date', $businessDate)
                ->where('posted', true)
                ->where('narration', 'like', '%float%')
                ->where(function ($query) {
                    $query->where('is_reversed', false)
                        ->orWhereNull('is_reversed');
                })
                ->sum('amount');

            $floatReturned = VaultTransaction::where('vault_id', $vault->id)
                ->where('transaction_type', 'DEPOSIT')
                ->whereDate('transaction_date', $businessDate)
                ->where('posted', true)
                ->where('narration', 'like', '%return%')
                ->where(function ($query) {
                    $query->where('is_reversed', false)
                        ->orWhereNull('is_reversed');
                })
                ->sum('amount');

            $openingCash =
                $physicalCash
                - $vaultDeposits
                - $floatReturned
                + $vaultWithdrawals
                + $floatAllocated;

            $expectedCash =
                $openingCash
                + $vaultDeposits
                + $floatReturned
                - $vaultWithdrawals
                - $floatAllocated;

            $variance = $physicalCash - $expectedCash;

            $status = match (true) {
                $variance == 0.0 => 'BALANCED',
                $variance > 0 => 'OVER',
                default => 'SHORT',
            };

            return VaultCashBalance::updateOrCreate(
                [
                    'vault_id' => $vault->id,
                    'business_date' => $businessDate,
                ],
                [
                    'opening_cash' => $openingCash,
                    'vault_deposits' => $vaultDeposits,
                    'vault_withdrawals' => $vaultWithdrawals,
                    'float_allocated' => $floatAllocated,
                    'float_returned' => $floatReturned,
                    'expected_cash' => $expectedCash,
                    'physical_cash' => $physicalCash,
                    'variance' => $variance,
                    'status' => $status,
                    'balanced_by' => $balancedBy,
                    'balanced_at' => now(),
                    'note' => $note,
                ]
            );
        });
    }

    protected function sumVaultTransactions(
        Vault $vault,
        string $businessDate,
        string $transactionType
    ): float {
        return (float) VaultTransaction::where('vault_id', $vault->id)
            ->where('transaction_type', $transactionType)
            ->whereDate('transaction_date', $businessDate)
            ->where('posted', true)
            ->where(function ($query) {
                $query->where('is_reversed', false)
                    ->orWhereNull('is_reversed');
            })
            ->sum('amount');
    }
}