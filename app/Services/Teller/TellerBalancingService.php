<?php

namespace App\Services\Teller;

use App\Models\Teller;
use App\Models\TellerCashBalance;
use App\Models\TellerTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class TellerBalancingService
{
    public function balance(
        Teller $teller,
        string $businessDate,
        float $physicalCash,
        int $balancedBy,
        ?string $note = null
    ): TellerCashBalance {
        if (!$teller->active) {
            throw new Exception('Teller is inactive.');
        }

        return DB::transaction(function () use (
            $teller,
            $businessDate,
            $physicalCash,
            $balancedBy,
            $note
        ) {
            $openingCash = $this->sumTransactions($teller, $businessDate, 'OPENING_CASH');
            $floatReceived = $this->sumTransactions($teller, $businessDate, 'RECEIVE_FLOAT');
            $customerDeposits = $this->sumTransactions($teller, $businessDate, 'CUSTOMER_DEPOSIT');
            $customerWithdrawals = $this->sumTransactions($teller, $businessDate, 'CUSTOMER_WITHDRAWAL');
            $floatReturned = $this->sumTransactions($teller, $businessDate, 'RETURN_FLOAT');

            $expectedCash =
                $openingCash
                + $floatReceived
                + $customerDeposits
                - $customerWithdrawals
                - $floatReturned;

            $variance = $physicalCash - $expectedCash;

            $status = match (true) {
                $variance == 0.0 => 'BALANCED',
                $variance > 0 => 'OVER',
                default => 'SHORT',
            };

            return TellerCashBalance::updateOrCreate(
                [
                    'teller_id' => $teller->id,
                    'business_date' => $businessDate,
                ],
                [
                    'opening_cash' => $openingCash,
                    'float_received' => $floatReceived,
                    'customer_deposits' => $customerDeposits,
                    'customer_withdrawals' => $customerWithdrawals,
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

    protected function sumTransactions(
        Teller $teller,
        string $businessDate,
        string $transactionType
    ): float {
        return (float) TellerTransaction::where('teller_id', $teller->id)
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