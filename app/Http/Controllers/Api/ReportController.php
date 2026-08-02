<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TellerTransaction;
use App\Models\VaultTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Call-over reports: every transaction (narration, amount, reference,
 * who performed it) at a given teller or vault over a date range, for
 * end-of-day/periodic reconciliation against physical vouchers.
 */
class ReportController extends Controller
{
    use ApiResponse;

    public function tellerTransactions(Request $request)
    {
        $validated = $request->validate([
            'teller_id' => ['required', 'integer', 'exists:tellers,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $transactions = TellerTransaction::with(['performer', 'approver'])
            ->where('teller_id', $validated['teller_id'])
            ->whereBetween('transaction_date', [
                $validated['from_date'].' 00:00:00',
                $validated['to_date'].' 23:59:59',
            ])
            ->orderBy('transaction_date')
            ->get();

        return $this->success(
            [
                'teller_id' => $validated['teller_id'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'count' => $transactions->count(),
                'total_debits' => $transactions->whereIn('transaction_type', [
                    'CUSTOMER_WITHDRAWAL', 'RETURN_FLOAT',
                ])->sum('amount'),
                'total_credits' => $transactions->whereIn('transaction_type', [
                    'CUSTOMER_DEPOSIT', 'RECEIVE_FLOAT',
                ])->sum('amount'),
                'transactions' => $transactions,
            ],
            'Teller call-over report generated successfully.'
        );
    }

    public function vaultTransactions(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $transactions = VaultTransaction::with(['performer', 'approver'])
            ->where('vault_id', $validated['vault_id'])
            ->whereBetween('transaction_date', [
                $validated['from_date'].' 00:00:00',
                $validated['to_date'].' 23:59:59',
            ])
            ->orderBy('transaction_date')
            ->get();

        return $this->success(
            [
                'vault_id' => $validated['vault_id'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'count' => $transactions->count(),
                'total_debits' => $transactions->whereIn('transaction_type', [
                    'WITHDRAWAL', 'FLOAT_ALLOCATION', 'TRANSFER_OUT', 'ATM_LOAD', 'SHORTAGE',
                ])->sum('amount'),
                'total_credits' => $transactions->whereIn('transaction_type', [
                    'DEPOSIT', 'FLOAT_RETURN', 'TRANSFER_IN', 'ATM_UNLOAD', 'SURPLUS',
                ])->sum('amount'),
                'transactions' => $transactions,
            ],
            'Vault call-over report generated successfully.'
        );
    }
}