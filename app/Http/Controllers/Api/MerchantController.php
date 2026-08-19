<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\AddMerchantDocumentRequest;
use App\Http\Requests\Merchant\AddMerchantOwnerRequest;
use App\Http\Requests\Merchant\CollectPosPaymentRequest;
use App\Http\Requests\Merchant\CollectQrPaymentRequest;
use App\Http\Requests\Merchant\RejectMerchantRequest;
use App\Http\Requests\Merchant\SettleMerchantRequest;
use App\Http\Requests\Merchant\AddMerchantLocationRequest;
use App\Http\Requests\Merchant\AssignMerchantTerminalRequest;
use App\Http\Requests\Merchant\UpdateMerchantRequest;
use App\Models\Merchant;
use App\Models\MerchantTerminal;
use App\Services\Payments\MerchantActivationService;
use App\Services\Payments\MerchantApprovalService;
use App\Services\Payments\MerchantKycService;
use App\Services\Payments\MerchantLocationService;
use App\Services\Payments\MerchantOnboardingService;
use App\Services\Payments\MerchantPaymentService;
use App\Services\Payments\MerchantSettlementService;
use App\Services\Payments\MerchantTerminalService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MerchantOnboardingService $onboardingService,
        protected MerchantPaymentService $paymentService,
        protected MerchantSettlementService $settlementService,
        protected MerchantApprovalService $approvalService,
        protected MerchantActivationService $activationService,
        protected MerchantKycService $kycService,
        protected MerchantLocationService $locationService,
        protected MerchantTerminalService $terminalService
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
            $merchant->load([
                'balance',
                'customerAccount',
                'owners',
                'documents',
                'locations',
                'terminals',
            ]),
            'Merchant retrieved successfully.'
        );
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant)
    {
        $merchant->update($request->validated());

        return $this->success($merchant->fresh(), 'Merchant updated successfully.');
    }

    public function submit(Merchant $merchant, Request $request)
    {
        try {
            $result = $this->approvalService->submit(
                $merchant,
                $request->user()->id
            );

            return $this->success($result, 'Merchant submitted for review.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approve(Merchant $merchant, Request $request)
    {
        try {
            $result = $this->approvalService->approve(
                $merchant,
                $request->user()->id
            );

            return $this->success($result, 'Merchant approved.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reject(Merchant $merchant, RejectMerchantRequest $request)
    {
        try {
            $result = $this->approvalService->reject(
                $merchant,
                $request->user()->id,
                $request->reason
            );

            return $this->success($result, 'Merchant rejected.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activate(Merchant $merchant)
    {
        try {
            $result = $this->activationService->activate($merchant);

            return $this->success($result, 'Merchant activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspend(Merchant $merchant, RejectMerchantRequest $request)
    {
        try {
            $result = $this->onboardingService->suspend($merchant, $request->reason);

            return $this->success($result, 'Merchant suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reactivate(Merchant $merchant)
    {
        try {
            $result = $this->onboardingService->reactivate($merchant);

            return $this->success($result, 'Merchant reactivated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function deactivate(Merchant $merchant, RejectMerchantRequest $request)
    {
        try {
            $result = $this->onboardingService->deactivate($merchant, $request->reason);

            return $this->success($result, 'Merchant deactivated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function listOwners(Merchant $merchant)
    {
        return $this->success(
            $this->kycService->listOwners($merchant),
            'Beneficial owners retrieved successfully.'
        );
    }

    public function addOwner(Merchant $merchant, AddMerchantOwnerRequest $request)
    {
        $owner = $this->kycService->addOwner($merchant, $request->validated());

        return $this->success($owner, 'Beneficial owner added successfully.', 201);
    }

    public function listDocuments(Merchant $merchant)
    {
        return $this->success(
            $this->kycService->listDocuments($merchant),
            'Documents retrieved successfully.'
        );
    }

    public function addDocument(Merchant $merchant, AddMerchantDocumentRequest $request)
    {
        $document = $this->kycService->addDocument($merchant, $request->validated());

        return $this->success($document, 'Document added successfully.', 201);
    }

    public function listLocations(Merchant $merchant)
    {
        return $this->success(
            $this->locationService->list($merchant),
            'Locations retrieved successfully.'
        );
    }

    public function addLocation(Merchant $merchant, AddMerchantLocationRequest $request)
    {
        $location = $this->locationService->add($merchant, $request->validated());

        return $this->success($location, 'Location added successfully.', 201);
    }

    public function listTerminals(Merchant $merchant)
    {
        return $this->success(
            $this->terminalService->list($merchant),
            'Terminals retrieved successfully.'
        );
    }

    public function assignTerminal(AssignMerchantTerminalRequest $request)
    {
        $merchant = Merchant::findOrFail($request->merchant_id);

        $terminal = $this->terminalService->assign(
            $merchant,
            $request->validated(),
            $request->user()->id
        );

        return $this->success($terminal, 'Terminal assigned successfully.', 201);
    }

    public function activateTerminal(MerchantTerminal $terminal)
    {
        try {
            $result = $this->terminalService->activate($terminal);

            return $this->success($result, 'Terminal activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspendTerminal(MerchantTerminal $terminal)
    {
        try {
            $result = $this->terminalService->suspend($terminal);

            return $this->success($result, 'Terminal suspended.');
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
                $request->idempotency_key,
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
                $request->idempotency_key,
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
                $request->idempotency_key,
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
