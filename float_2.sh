#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Approval/ApprovalRequestService.php << 'MBOS_EOF'
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
MBOS_EOF

cat > app/Http/Controllers/Api/ApprovalController.php << 'MBOS_EOF'
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
MBOS_EOF

cat > tests/Feature/AgentFloatServiceTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\Branch;
use App\Models\GlJournal;
use App\Models\User;
use App\Models\Vault;
use App\Services\Approval\ApprovalRequestService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentFloatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeActiveAgent(array $overrides = []): Agent
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);
        $registrant = User::factory()->create();

        $agent = Agent::create(array_merge([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $registrant->id,
            'single_transaction_limit' => 1000000,
        ], $overrides));

        $creator = User::factory()->create();
        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
        ]);

        return $agent;
    }

    protected function makeFundedVault(float $fundAmount): Vault
    {
        $branch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
        $user = User::factory()->create();

        $vault = Vault::create([
            'branch_id' => $branch->id,
            'vault_code' => 'VLT-'.uniqid(),
            'active' => true,
        ]);

        app(VaultTransactionService::class)->deposit($vault, $fundAmount, $user->id);

        return $vault;
    }

    public function test_allocate_float_credits_agent_balance_and_debits_vault(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        $result = app(AgentFloatService::class)->allocateFloat($vault, $agent, 100000, $user->id);

        $this->assertEquals(100000, (float) $result['agent_balance']->ledger_float);
        $this->assertEquals(100000, (float) $result['agent_balance']->available_float);

        $vaultBalance = \App\Models\VaultBalance::where('vault_id', $vault->id)->first();
        $this->assertEquals(400000, (float) $vaultBalance->available_balance);
    }

    public function test_allocate_float_creates_agent_balance_row_if_missing(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        $this->assertEquals(0, AgentBalance::where('agent_id', $agent->id)->count());

        app(AgentFloatService::class)->allocateFloat($vault, $agent, 50000, $user->id);

        $this->assertEquals(1, AgentBalance::where('agent_id', $agent->id)->count());
    }

    public function test_allocate_float_posts_two_balanced_gl_journal_entries(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        $result = app(AgentFloatService::class)->allocateFloat($vault, $agent, 100000, $user->id);

        $journalEntries = GlJournal::where('reference', $result['cash_ledger']->reference_no)->get();

        $this->assertCount(2, $journalEntries, 'The agent-side CashLedger entry alone should post exactly 2 balanced GlJournal rows.');
    }

    public function test_allocate_float_fails_for_non_active_agent(): void
    {
        $agent = $this->makeActiveAgent(['status' => AgentStatus::DRAFT->value]);
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not ACTIVE');

        app(AgentFloatService::class)->allocateFloat($vault, $agent, 100000, $user->id);
    }

    public function test_return_float_fails_with_insufficient_agent_balance(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        app(AgentFloatService::class)->allocateFloat($vault, $agent, 50000, $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient agent float balance');

        app(AgentFloatService::class)->returnFloat($vault, $agent, 100000, $user->id);
    }

    public function test_return_float_correctly_reverses_allocation(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $user = User::factory()->create();

        app(AgentFloatService::class)->allocateFloat($vault, $agent, 100000, $user->id);
        $result = app(AgentFloatService::class)->returnFloat($vault, $agent, 60000, $user->id);

        $this->assertEquals(40000, (float) $result['agent_balance']->ledger_float);
        $this->assertEquals(40000, (float) $result['agent_balance']->available_float);
    }

    public function test_full_maker_checker_flow_allocates_float_only_after_approval(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $maker = User::factory()->create();
        $checker = User::factory()->create();

        $approvalService = app(ApprovalRequestService::class);

        $request = $approvalService->createRequest(
            'AGENT_ALLOCATE_FLOAT',
            [
                'vault_id' => $vault->id,
                'agent_id' => $agent->id,
                'amount' => 75000,
                'performed_by' => $maker->id,
            ],
            $maker->id,
            75000
        );

        $this->assertEquals(0, AgentBalance::where('agent_id', $agent->id)->count());

        $approvalService->approve($request, $checker->id);

        $balance = AgentBalance::where('agent_id', $agent->id)->first();
        $this->assertEquals(75000, (float) $balance->ledger_float);
    }

    public function test_maker_cannot_approve_their_own_agent_float_request(): void
    {
        $agent = $this->makeActiveAgent();
        $vault = $this->makeFundedVault(500000);
        $maker = User::factory()->create();

        $approvalService = app(ApprovalRequestService::class);

        $request = $approvalService->createRequest(
            'AGENT_ALLOCATE_FLOAT',
            [
                'vault_id' => $vault->id,
                'agent_id' => $agent->id,
                'amount' => 75000,
                'performed_by' => $maker->id,
            ],
            $maker->id,
            75000
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $approvalService->approve($request, $maker->id);
    }
}
MBOS_EOF

echo "AG-06 Part 2 of 2 applied (approval service/controller extended, tests). Next: php artisan migrate && php artisan test --filter=AgentFloatServiceTest"