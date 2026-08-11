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
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
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
