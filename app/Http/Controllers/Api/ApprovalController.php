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

    /**
     * Create a maker-checker request for vault-to-teller float allocation.
     */
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

                // The authenticated maker will perform the transaction
                // after the request is approved.
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

    /**
     * Create a maker-checker request for teller-to-vault float return.
     */
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

                // Derived from the authenticated maker.
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

    /**
     * Retrieve all pending approval requests.
     */
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

    /**
     * Approve a pending maker-checker request.
     */
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

    /**
     * Reject a pending maker-checker request.
     */
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