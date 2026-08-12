<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\GlJournal;
use App\Models\User;
use App\Services\Payments\AgentTransferService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers AG-09's internal transfer: a real customer-to-customer money
 * movement facilitated by an agent, where the agent's own float is
 * never touched. Confirms the "no GL posting" design decision
 * (matching WalletService::transfer()'s precedent) is actually true,
 * not just claimed -- both accounts sit on the same balance sheet, so
 * the net GL effect of a transfer between them is genuinely zero.
 */
class AgentTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(array $agentOverrides = []): array
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
        ], $agentOverrides));

        $agreementCreator = User::factory()->create();
        $agent->agreements()->create([
            'agreement_number' => 'AGR-'.uniqid(),
            'version' => 1,
            'status' => 'ACTIVE',
            'created_by' => $agreementCreator->id,
        ]);

        $locationCreator = User::factory()->create();
        $location = AgentLocation::create([
            'agent_id' => $agent->id,
            'location_code' => 'LOC-'.uniqid(),
            'address_line_1' => '1 Test Street',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'latitude' => 6.5244000,
            'longitude' => 3.3792000,
            'approved_radius_metres' => 100,
            'verification_status' => 'VERIFIED',
            'status' => 'ACTIVE',
            'created_by' => $locationCreator->id,
        ]);

        $operatorUser = User::factory()->create();
        $operator = AgentOperator::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'user_id' => $operatorUser->id,
            'role' => 'TILL_OPERATOR',
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        $terminal = AgentTerminal::create([
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'terminal_id' => 'TERM-'.uniqid(),
            'serial_number' => 'SN-'.uniqid(),
            'status' => 'ACTIVE',
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
        ]);

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
    }

    protected function makeCustomerAccount(float $openingBalance = 0): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $account;
    }

    public function test_valid_transfer_succeeds(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();

        $result = app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals('COMPLETED', $result->status);
        $this->assertEquals('TRANSFER', $result->transaction_type);
    }

    public function test_self_transfer_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext();
        $account = $this->makeCustomerAccount(100000);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('itself');

        app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $account,
            $account,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_transfer_rejected_for_insufficient_source_balance(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(10000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient');

        app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_transfer_rejected_for_inactive_agent(): void
    {
        $context = $this->makeReadyAgentContext();
        $context['agent']->update(['status' => AgentStatus::SUSPENDED->value]);
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();
        $freshOperator = \App\Models\AgentOperator::find($context['operator']->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not ACTIVE');

        app(AgentTransferService::class)->transfer(
            $freshOperator,
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_both_accounts_updated_correctly(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(20000);
        $user = User::factory()->create();

        app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $fromBalance = CustomerAccountBalance::where('customer_account_id', $from->id)->first();
        $toBalance = CustomerAccountBalance::where('customer_account_id', $to->id)->first();

        $this->assertEquals(70000, (float) $fromBalance->available_balance);
        $this->assertEquals(50000, (float) $toBalance->available_balance);
    }

    public function test_no_gl_journal_entries_are_created_for_internal_transfer(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();

        $result = app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $journalEntries = GlJournal::where('reference', $result->transaction_no)->get();
        $this->assertCount(0, $journalEntries, 'Same-institution transfers should not post to the GL -- net balance-sheet effect is zero, matching WalletService precedent.');
    }

    public function test_duplicate_idempotency_key_returns_original_response(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $first = app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $second = app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AgentTransaction::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_transaction_outside_geo_fence_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext();
        $from = $this->makeCustomerAccount(100000);
        $to = $this->makeCustomerAccount(0);
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('geo-fence');

        app(AgentTransferService::class)->transfer(
            $context['operator'],
            $context['terminal'],
            $from,
            $to,
            30000,
            (string) Str::uuid(),
            7.3775000,
            3.9470000,
            $user->id
        );
    }
}
