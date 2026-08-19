<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves both "Transactions" and "Transaction History" from today's plan --
 * merchant_transactions has no separate current-vs-history split (status
 * just progresses on the same row), so one filterable, paginated list
 * covers both: unfiltered for a live view, status/date-ranged for history.
 */
class MerchantTransactionController extends Controller
{
    public function list(Merchant $merchant, Request $request): JsonResponse
    {
        $query = $merchant->transactions()
            ->with(['performer', 'approver'])
            ->latest('transaction_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->string('transaction_type')->toString());
        }

        if ($request->filled('from')) {
            $query->whereDate('transaction_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('transaction_date', '<=', $request->date('to'));
        }

        return response()->json(
            $query->paginate(
                min(
                    max($request->integer('per_page', 25), 1),
                    100
                )
            )
        );
    }
}
