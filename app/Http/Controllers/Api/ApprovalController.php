<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Approval\ApprovalRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApprovalRequestService $approvalRequestService
    ) {
    }

    public function requestAllocateFloat(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'teller_id' => ['required', 'integer', 'exists:tellers,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $authenticatedUserId = $request->user()->id;

        $approval = $this->approvalRequestService->createRequest(
            'ALLOCATE_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'teller_id' => $validated['teller_id'],
                'amount' => $validated['amount'],
                'performed_by' => $authenticatedUserId,
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $authenticatedUserId,
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success(
            $approval,
            'Float allocation request created successfully.',
            201
        );
    }

    public function requestReturnFloat(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'teller_id' => ['required', 'integer', 'exists:tellers,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $authenticatedUserId = $request->user()->id;

        $approval = $this->approvalRequestService->createRequest(
            'RETURN_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'teller_id' => $validated['teller_id'],
                'amount' => $validated['amount'],
                'performed_by' => $authenticatedUserId,
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $authenticatedUserId,
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success(
            $approval,
            'Float return request created successfully.',
            201
        );
    }

    public function requestAgentAllocateFloat(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $authenticatedUserId = $request->user()->id;

        $approval = $this->approvalRequestService->createRequest(
            'AGENT_ALLOCATE_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'agent_id' => $validated['agent_id'],
                'amount' => $validated['amount'],
                'performed_by' => $authenticatedUserId,
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $authenticatedUserId,
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success(
            $approval,
            'Agent float allocation request created successfully.',
            201
        );
    }

    public function requestAgentReturnFloat(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $authenticatedUserId = $request->user()->id;

        $approval = $this->approvalRequestService->createRequest(
            'AGENT_RETURN_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'agent_id' => $validated['agent_id'],
                'amount' => $validated['amount'],
                'performed_by' => $authenticatedUserId,
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $authenticatedUserId,
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success(
            $approval,
            'Agent float return request created successfully.',
            201
        );
    }

    public function pending()
    {
        $requests = ApprovalRequest::query()
            ->where('status', 'PENDING')
            ->latest()
            ->get();

        return $this->success(
            $requests,
            'Pending approval requests retrieved successfully.'
        );
    }

    public function approve(Request $request, int $id)
    {
        $validated = $request->validate([
            'checker_note' => ['nullable', 'string'],
        ]);

        $approvalRequest = ApprovalRequest::findOrFail($id);

        $checkerId = $request->user()->id;

        $approved = $this->approvalRequestService->approve(
            $approvalRequest,
            $checkerId,
            $validated['checker_note'] ?? null
        );

        return $this->success(
            $approved,
            'Approval request approved successfully.'
        );
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'checker_note' => ['nullable', 'string'],
        ]);

        $approvalRequest = ApprovalRequest::findOrFail($id);

        $checkerId = $request->user()->id;

        $rejected = $this->approvalRequestService->reject(
            $approvalRequest,
            $checkerId,
            $validated['checker_note'] ?? null
        );

        return $this->success(
            $rejected,
            'Approval request rejected successfully.'
        );
    }
}
