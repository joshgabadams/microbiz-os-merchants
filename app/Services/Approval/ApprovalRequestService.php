<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalRequestService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService
    ) {
    }

    public function createRequest(
        string $requestType,
        array $payload,
        int $makerId,
        ?float $amount = null,
        string $currency = 'NGN',
        ?string $makerNote = null
    ): ApprovalRequest {
        return ApprovalRequest::create([
            'request_no' => $this->transactionNumberService->generate('APR'),
            'request_type' => $requestType,
            'payload' => $payload,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'PENDING',
            'maker_id' => $makerId,
            'maker_note' => $makerNote,
        ]);
    }

    public function reject(
        ApprovalRequest $request,
        int $checkerId,
        ?string $checkerNote = null
    ): ApprovalRequest {
        if ($request->status !== 'PENDING') {
            throw new Exception('Only pending approval requests can be rejected.');
        }

        $request->update([
            'status' => 'REJECTED',
            'checker_id' => $checkerId,
            'rejected_at' => now(),
            'checker_note' => $checkerNote,
        ]);

        return $request->fresh();
    }

    public function approve(
    ApprovalRequest $request,
    int $checkerId,
    ?string $checkerNote = null
): ApprovalRequest {
    if ($request->status !== 'PENDING') {
        throw new Exception('Only pending approval requests can be approved.');
    }

    if ($request->maker_id === $checkerId) {
        throw new Exception('Maker cannot approve own request.');
    }

    return DB::transaction(function () use (
        $request,
        $checkerId,
        $checkerNote
    ) {
        $payload = $request->payload;

        $result = match ($request->request_type) {
            'ALLOCATE_FLOAT' => app(\App\Services\CashManagement\VaultTellerFloatService::class)
                ->allocateFloat(
                    \App\Models\Vault::findOrFail($payload['vault_id']),
                    \App\Models\Teller::findOrFail($payload['teller_id']),
                    (float) $payload['amount'],
                    (int) $payload['performed_by'],
                    $payload['reference'] ?? null,
                    $payload['narration'] ?? null
                ),

            'RETURN_FLOAT' => app(\App\Services\CashManagement\VaultTellerFloatService::class)
                ->returnFloat(
                    \App\Models\Vault::findOrFail($payload['vault_id']),
                    \App\Models\Teller::findOrFail($payload['teller_id']),
                    (float) $payload['amount'],
                    (int) $payload['performed_by'],
                    $payload['reference'] ?? null,
                    $payload['narration'] ?? null
                ),

            default => throw new Exception('Unsupported approval request type.'),
        };

        $executedTransaction = $result['vault_transaction']
            ?? $result['teller_transaction']
            ?? null;

        $request->update([
            'status' => 'APPROVED',
            'checker_id' => $checkerId,
            'approved_at' => now(),
            'checker_note' => $checkerNote,
            'executed_transaction_type' => $executedTransaction ? get_class($executedTransaction) : null,
            'executed_transaction_id' => $executedTransaction?->id,
        ]);

        return $request->fresh();
    });
}

}