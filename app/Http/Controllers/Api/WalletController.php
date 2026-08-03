<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\OnboardWalletRequest;
use App\Http\Requests\Wallet\TopUpWalletRequest;
use App\Http\Requests\Wallet\TransferWalletRequest;
use App\Models\Wallet;
use App\Services\Payments\WalletOnboardingService;
use App\Services\Payments\WalletService;
use App\Traits\ApiResponse;
use Exception;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletOnboardingService $onboardingService,
        protected WalletService $walletService
    ) {
    }

    public function index()
    {
        return $this->success(
            Wallet::with('balance')->get(),
            'Wallets retrieved successfully.'
        );
    }

    public function show(Wallet $wallet)
    {
        return $this->success(
            $wallet->load('balance'),
            'Wallet retrieved successfully.'
        );
    }

    public function onboard(OnboardWalletRequest $request)
    {
        try {
            $wallet = $this->onboardingService->onboard(
                $request->validated(),
                $request->user()->id
            );

            return $this->success(
                $wallet,
                'Wallet onboarded successfully.',
                201
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function topUp(TopUpWalletRequest $request)
    {
        try {
            $wallet = Wallet::findOrFail($request->wallet_id);

            $result = $this->walletService->topUp(
                $wallet,
                (float) $request->amount,
                (int) $request->branch_id,
                $request->user()->id,
                $request->reference,
                $request->narration
            );

            return $this->success(
                $result,
                'Wallet topped up successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function transfer(TransferWalletRequest $request)
    {
        try {
            $fromWallet = Wallet::findOrFail($request->from_wallet_id);
            $toWallet = Wallet::findOrFail($request->to_wallet_id);

            $result = $this->walletService->transfer(
                $fromWallet,
                $toWallet,
                (float) $request->amount,
                $request->user()->id,
                $request->reference,
                $request->narration
            );

            return $this->success(
                $result,
                'Transfer completed successfully.'
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
