<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AddAgentDocumentRequest;
use App\Http\Requests\Agent\AddAgentOwnerRequest;
use App\Http\Requests\Agent\AgentReasonRequest;
use App\Http\Requests\Agent\OnboardAgentRequest;
use App\Models\Agent;
use App\Services\Payments\AgentActivationService;
use App\Services\Payments\AgentApprovalService;
use App\Services\Payments\AgentKycService;
use App\Services\Payments\AgentRegistrationService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AgentRegistrationService $registrationService,
        protected AgentApprovalService $approvalService,
        protected AgentActivationService $activationService,
        protected AgentKycService $kycService
    ) {
    }

    public function index()
    {
        return $this->success(
            Agent::with(['branch', 'supervisor'])->get(),
            'Agents retrieved successfully.'
        );
    }

    public function show(Agent $agent)
    {
        return $this->success(
            $agent->load(['branch', 'supervisor']),
            'Agent retrieved successfully.'
        );
    }

    public function store(OnboardAgentRequest $request)
    {
        try {
            $agent = $this->registrationService->register(
                $request->validated(),
                $request->user()->id
            );

            return $this->success($agent, 'Agent registered successfully.', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, Agent $agent)
    {
        $agent->update($request->only([
            'trading_name', 'registration_number', 'tax_identification_number',
            'phone', 'email', 'supervisor_id', 'principal_reference',
            'daily_transaction_limit', 'daily_cash_out_limit', 'single_transaction_limit',
            'next_review_date',
        ]));

        return $this->success($agent->fresh(), 'Agent updated successfully.');
    }

    public function submit(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->submit($agent, $request->user()->id);

            return $this->success($result, 'Agent submitted for approval.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approve(Agent $agent, Request $request)
    {
        try {
            $result = $this->approvalService->approve($agent, $request->user()->id);

            return $this->success($result, 'Agent approved.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reject(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->approvalService->reject($agent, $request->user()->id, $request->reason);

            return $this->success($result, 'Agent rejected.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function activate(Agent $agent)
    {
        try {
            $result = $this->activationService->activate($agent);

            return $this->success($result, 'Agent activated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function restrict(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->restrict($agent, $request->reason);

            return $this->success($result, 'Agent restricted.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function suspend(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->suspend($agent, $request->reason);

            return $this->success($result, 'Agent suspended.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reactivate(Agent $agent)
    {
        try {
            $result = $this->activationService->reactivate($agent);

            return $this->success($result, 'Agent reactivated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function terminate(Agent $agent, AgentReasonRequest $request)
    {
        try {
            $result = $this->activationService->terminate($agent, $request->reason);

            return $this->success($result, 'Agent terminated.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ---------- AG-02: KYC & Approval ----------

    public function listOwners(Agent $agent)
    {
        return $this->success($agent->owners, 'Agent owners retrieved successfully.');
    }

    public function addOwner(Agent $agent, AddAgentOwnerRequest $request)
    {
        $owner = $agent->owners()->create($request->validated());

        return $this->success($owner, 'Agent owner added successfully.', 201);
    }

    public function listDocuments(Agent $agent)
    {
        return $this->success($agent->documents, 'Agent documents retrieved successfully.');
    }

    public function addDocument(Agent $agent, AddAgentDocumentRequest $request)
    {
        $document = $agent->documents()->create($request->validated());

        return $this->success($document, 'Agent document added successfully.', 201);
    }

    public function completeKyc(Agent $agent, Request $request)
    {
        try {
            $result = $this->kycService->completeKyc($agent, $request->user()->id);

            return $this->success($result, 'Agent KYC completed; moved to location verification.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
