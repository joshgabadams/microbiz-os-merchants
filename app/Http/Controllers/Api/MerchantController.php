<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\CollectPosPaymentRequest;
use App\Http\Requests\Merchant\CollectQrPaymentRequest;
use App\Http\Requests\Merchant\OnboardMerchantRequest;
use App\Http\Requests\Merchant\SettleMerchantRequest;
use App\Models\Merchant;
use App\Services\Payments\MerchantOnboardingService;
use App\Services\Payments\MerchantPaymentService;
use App\Services\Payments\MerchantSettlementService;
use App\Traits\ApiResponse;
use Exception;

class MerchantController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MerchantOnboardingService $onboardingService,
        protected MerchantPaymentService $paymentService,
        protected MerchantSettlementService $settlementService
    ) {
    }

    public function index()
    {
        return $this->success(
            Merchant::with('balance')->get(),
            'Merchants retrieved successfully.'
        );
    }

    public function show(Merchant $merchant)
    {
        return $this->success(
            $merchant->load('balance'),
            'Merchant retrieved successfully.'
        );
    }

    public function onboard(OnboardMerchantRequest $request)
    {
        try {
            $merchant = $this->onboardingService->onboard(
                $request->validated(),
                $request->user()->id
            );

            return $this->success(
                $merchant,
                'Merchant onboarded successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function collectQr(CollectQrPaymentRequest $request)
    {
        try {
            $merchant = Merchant::findOrFail($request->merchant_id);

            $result = $this->paymentService->collectQrPayment(
                $merchant,
                (float) $request->amount,
                (int) $request->branch_id,
                $request->user()->id,
                $request->reference,
                $request->narration
            );

            return $this->success(
                $result,
                'QR payment collected successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function collectPos(CollectPosPaymentRequest $request)
    {
        try {
            $merchant = Merchant::findOrFail($request->merchant_id);

            $result = $this->paymentService->collectPosPayment(
                $merchant,
                (float) $request->amount,
                (int) $request->branch_id,
                $request->user()->id,
                $request->reference,
                $request->narration
            );

            return $this->success(
                $result,
                'POS payment collected successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function settle(SettleMerchantRequest $request)
    {
        try {
            $merchant = Merchant::findOrFail($request->merchant_id);

            $result = $this->settlementService->settle(
                $merchant,
                (float) $request->amount,
                (int) $request->branch_id,
                $request->user()->id,
                $request->reference,
                $request->narration
            );

            return $this->success(
                $result,
                'Merchant settled successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
