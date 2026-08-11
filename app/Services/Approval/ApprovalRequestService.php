<?php

namespace App\Services\Approval;

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\Teller;
use App\Models\Vault;
use App\Services\CashManagement\AgentFloatService;
use App\Services\CashManagement\VaultTellerFloatService;
use App\Services\Common\TransactionNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApprovalRequestService
{
    public function __construct(
        protected TransactionNumberService $transactionNumberService,
        protected VaultTellerFloatService $vaultTellerFloatService,
        protected AgentFloatService $agentFloatService
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
        ApprovalRequest $approvalRequest,
        int $checkerId,
        ?string $checkerNote = null
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $checkerId,
            $checkerNote
        ) {
            $approvalRequest = ApprovalRequest::query()
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            $this->ensureRequestIsPending($approvalRequest);
            $this->ensureMakerAndCheckerAreDifferent(
                $approvalRequest,
                $checkerId
            );

            $approvalRequest->update([
                'status' => 'REJECTED',
                'checker_id' => $checkerId,
                'rejected_at' => now(),
                'approved_at' => null,
                'checker_note' => $checkerNote,
            ]);

            return $approvalRequest->fresh();
        });
    }

    public function approve(
        ApprovalRequest $approvalRequest,
        int $checkerId,
        ?string $checkerNote = null
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $checkerId,
            $checkerNote
        ) {
            $approvalRequest = ApprovalRequest::query()
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            $this->ensureRequestIsPending($approvalRequest);
            $this->ensureMakerAndCheckerAreDifferent(
                $approvalRequest,
                $checkerId
            );

            $payload = $approvalRequest->payload;

            if (!is_array($payload)) {
                throw new RuntimeException(
                    'The approval request payload is invalid.'
                );
            }

            $result = match ($approvalRequest->request_type) {
                'ALLOCATE_FLOAT' => $this->executeAllocateFloat($payload),
                'RETURN_FLOAT' => $this->executeReturnFloat($payload),
                'AGENT_ALLOCATE_FLOAT' => $this->executeAgentAllocateFloat($payload),
                'AGENT_RETURN_FLOAT' => $this->executeAgentReturnFloat($payload),

                default => throw new RuntimeException(
                    "Unsupported approval request type: {$approvalRequest->request_type}."
                ),
            };

            $executedTransaction = $result['vault_transaction']
                ?? $result['teller_transaction']
                ?? null;

            $approvalRequest->update([
                'status' => 'APPROVED',
                'checker_id' => $checkerId,
                'approved_at' => now(),
                'rejected_at' => null,
                'checker_note' => $checkerNote,
                'executed_transaction_type' => $executedTransaction
                    ? get_class($executedTransaction)
                    : null,
                'executed_transaction_id' => $executedTransaction?->id,
            ]);

            return $approvalRequest->fresh();
        });
    }

    protected function executeAllocateFloat(array $payload): array
    {
        $this->validateFloatPayload($payload);

        return $this->vaultTellerFloatService->allocateFloat(
            Vault::findOrFail($payload['vault_id']),
            Teller::findOrFail($payload['teller_id']),
            (float) $payload['amount'],
            (int) $payload['performed_by'],
            $payload['reference'] ?? null,
            $payload['narration'] ?? null
        );
    }

    protected function executeReturnFloat(array $payload): array
    {
        $this->validateFloatPayload($payload);

        return $this->vaultTellerFloatService->returnFloat(
            Vault::findOrFail($payload['vault_id']),
            Teller::findOrFail($payload['teller_id']),
            (float) $payload['amount'],
            (int) $payload['performed_by'],
            $payload['reference'] ?? null,
            $payload['narration'] ?? null
        );
    }

    protected function executeAgentAllocateFloat(array $payload): array
    {
        $this->validateAgentFloatPayload($payload);

        return $this->agentFloatService->allocateFloat(
            Vault::findOrFail($payload['vault_id']),
            Agent::findOrFail($payload['agent_id']),
            (float) $payload['amount'],
            (int) $payload['performed_by'],
            $payload['reference'] ?? null,
            $payload['narration'] ?? null
        );
    }

    protected function executeAgentReturnFloat(array $payload): array
    {
        $this->validateAgentFloatPayload($payload);

        return $this->agentFloatService->returnFloat(
            Vault::findOrFail($payload['vault_id']),
            Agent::findOrFail($payload['agent_id']),
            (float) $payload['amount'],
            (int) $payload['performed_by'],
            $payload['reference'] ?? null,
            $payload['narration'] ?? null
        );
    }

    protected function ensureRequestIsPending(
        ApprovalRequest $approvalRequest
    ): void {
        if ($approvalRequest->status !== 'PENDING') {
            throw ValidationException::withMessages([
                'status' => [
                    'Only pending approval requests can be processed.',
                ],
            ]);
        }
    }

    protected function ensureMakerAndCheckerAreDifferent(
        ApprovalRequest $approvalRequest,
        int $checkerId
    ): void {
        if ((int) $approvalRequest->maker_id === $checkerId) {
            throw ValidationException::withMessages([
                'checker' => [
                    'The maker cannot approve or reject their own request.',
                ],
            ]);
        }
    }

    protected function validateFloatPayload(array $payload): void
    {
        $requiredFields = [
            'vault_id',
            'teller_id',
            'amount',
            'performed_by',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(
                    "The approval payload is missing the {$field} field."
                );
            }
        }

        if ((float) $payload['amount'] <= 0) {
            throw new RuntimeException(
                'The approval payload contains an invalid amount.'
            );
        }
    }

    protected function validateAgentFloatPayload(array $payload): void
    {
        $requiredFields = [
            'vault_id',
            'agent_id',
            'amount',
            'performed_by',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(
                    "The approval payload is missing the {$field} field."
                );
            }
        }

        if ((float) $payload['amount'] <= 0) {
            throw new RuntimeException(
                'The approval payload contains an invalid amount.'
            );
        }
    }
}
