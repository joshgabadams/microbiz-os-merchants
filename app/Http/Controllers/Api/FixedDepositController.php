<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedDeposit\BookFixedDepositRequest;
use App\Http\Requests\FixedDeposit\LiquidateFixedDepositRequest;
use App\Models\CustomerAccount;
use App\Models\FixedDeposit;
use App\Services\Deposits\FixedDepositService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Exception;

class FixedDepositController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FixedDepositService $fixedDepositService
    ) {
    }

    public function index()
    {
        return $this->success(
            FixedDeposit::with(['sourceAccount', 'settlementAccount'])->latest()->get(),
            'Fixed deposits retrieved successfully.'
        );
    }

    public function show(FixedDeposit $fixedDeposit)
    {
        return $this->success(
            $fixedDeposit->load(['sourceAccount', 'settlementAccount']),
            'Fixed deposit retrieved successfully.'
        );
    }

    public function book(BookFixedDepositRequest $request)
    {
        try {
            if ($request->filled('start_date') && Carbon::parse($request->start_date)->lt(Carbon::today())) {
                if (! $request->user()->hasPermission('fixed_deposits.backdate')) {
                    return $this->error('Missing required permission: fixed_deposits.backdate.', 403);
                }
            }

            $sourceAccount = CustomerAccount::findOrFail($request->customer_account_id);
            $settlementAccount = CustomerAccount::findOrFail($request->settlement_account_id);

            $fixedDeposit = $this->fixedDepositService->book(
                $sourceAccount,
                $settlementAccount,
                (float) $request->principal_amount,
                (float) $request->interest_rate,
                (float) $request->pre_liquidation_rate,
                (float) ($request->pre_liquidation_penalty_fee ?? 0),
                (int) $request->tenor_days,
                (int) $request->branch_id,
                $request->user()->id,
                $request->narration,
                $request->filled('start_date') ? Carbon::parse($request->start_date) : null,
                $request->backdate_reason
            );

            return $this->success($fixedDeposit, 'Fixed deposit booked successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function previewLiquidation(FixedDeposit $fixedDeposit)
    {
        try {
            $preview = $this->fixedDepositService->previewLiquidation($fixedDeposit);

            return $this->success($preview, 'Liquidation preview calculated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function liquidate(LiquidateFixedDepositRequest $request, FixedDeposit $fixedDeposit)
    {
        try {
            $result = $this->fixedDepositService->liquidate(
                $fixedDeposit,
                (int) $request->branch_id,
                $request->user()->id,
                $request->narration
            );

            return $this->success($result, 'Fixed deposit liquidated successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
