<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashManagement\ReverseCustomerTransactionRequest;
use App\Models\CustomerAccountTransaction;
use App\Services\CashManagement\TransactionReversalService;
use App\Traits\ApiResponse;
use Exception;

class TransactionReversalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionReversalService $reversalService
    ) {
    }

    public function reverse(ReverseCustomerTransactionRequest $request, CustomerAccountTransaction $customerAccountTransaction)
    {
        try {
            $tellerTransaction = \App\Models\TellerTransaction::where(
                'customer_account_transaction_id',
                $customerAccountTransaction->id
            )->firstOrFail();

            $result = match ($customerAccountTransaction->transaction_type) {
                'CASH_DEPOSIT' => $this->reversalService->reverseCustomerDeposit(
                    $customerAccountTransaction,
                    $tellerTransaction,
                    $request->user()->id,
                    $request->reference,
                    $request->narration
                ),
                'CASH_WITHDRAWAL' => $this->reversalService->reverseCustomerWithdrawal(
                    $customerAccountTransaction,
                    $tellerTransaction,
                    $request->user()->id,
                    $request->reference,
                    $request->narration
                ),
                default => throw new Exception(
                    "Transaction type {$customerAccountTransaction->transaction_type} cannot be reversed via this endpoint."
                ),
            };

            return $this->success($result, 'Transaction reversed successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
