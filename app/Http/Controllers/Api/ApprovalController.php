<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Approval\ApprovalRequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

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
            'performed_by' => ['required', 'integer', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_id' => ['required', 'integer', 'exists:users,id'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $approval = $this->approvalRequestService->createRequest(
            'ALLOCATE_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'teller_id' => $validated['teller_id'],
                'amount' => $validated['amount'],
                'performed_by' => $validated['performed_by'],
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $validated['maker_id'],
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success($approval, 'Float allocation request created successfully.', 201);
    }

    public function requestReturnFloat(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => ['required', 'integer', 'exists:vaults,id'],
            'teller_id' => ['required', 'integer', 'exists:tellers,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'performed_by' => ['required', 'integer', 'exists:users,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'maker_id' => ['required', 'integer', 'exists:users,id'],
            'maker_note' => ['nullable', 'string'],
        ]);

        $approval = $this->approvalRequestService->createRequest(
            'RETURN_FLOAT',
            [
                'vault_id' => $validated['vault_id'],
                'teller_id' => $validated['teller_id'],
                'amount' => $validated['amount'],
                'performed_by' => $validated['performed_by'],
                'reference' => $validated['reference'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ],
            $validated['maker_id'],
            $validated['amount'],
            'NGN',
            $validated['maker_note'] ?? null
        );

        return $this->success($approval, 'Float return request created successfully.', 201);
    }

    public function pending()
    {
        $requests = ApprovalRequest::where('status', 'PENDING')
            ->latest()
            ->get();

        return $this->success($requests, 'Pending approval requests retrieved successfully.');
    }

    public function approve(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'checker_id' => ['required', 'integer', 'exists:users,id'],
                'checker_note' => ['nullable', 'string'],
            ]);

            $approvalRequest = ApprovalRequest::findOrFail($id);

            $approved = $this->approvalRequestService->approve(
                $approvalRequest,
                $validated['checker_id'],
                $validated['checker_note'] ?? null
            );

            return $this->success($approved, 'Approval request approved successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'checker_id' => ['required', 'integer', 'exists:users,id'],
                'checker_note' => ['nullable', 'string'],
            ]);

            $approvalRequest = ApprovalRequest::findOrFail($id);

            $rejected = $this->approvalRequestService->reject(
                $approvalRequest,
                $validated['checker_id'],
                $validated['checker_note'] ?? null
            );

            return $this->success($rejected, 'Approval request rejected successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}