#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Payments/AgentCashInService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\AgentBalance;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Customer\CustomerAccountService;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentCashInService
{
    public function __construct(
        protected AgentOperationGuard $guard,
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function cashIn(
        AgentOperator $operator,
        AgentTerminal $terminal,
        CustomerAccount $customerAccount,
        float $amount,
        string $idempotencyKey,
        float $latitude,
        float $longitude,
        int $performedBy,
        ?string $customerReference = null,
        ?string $narration = null
    ): AgentTransaction {
        if ($amount <= 0) {
            throw new Exception('Cash-in amount must be greater than zero.');
        }

        $existing = AgentTransaction::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $agent = $operator->agent;
        $location = $operator->location;

        if ($terminal->agent_id !== $agent->id) {
            throw new Exception('This terminal does not belong to the specified agent.');
        }

        if ($terminal->agent_location_id !== $location->id) {
            throw new Exception("This terminal is not assigned to the operator's location.");
        }

        $this->guard->guardCashInOperation($agent, $location, $operator, $terminal, $amount);

        $geoFencePassed = $this->isWithinGeoFence(
            $latitude,
            $longitude,
            (float) $terminal->registered_latitude,
            (float) $terminal->registered_longitude,
            $terminal->geo_fence_radius_metres
        );

        if (! $geoFencePassed) {
            throw new Exception("Transaction rejected: device is outside the terminal's approved geo-fence.");
        }

        if ($customerAccount->status !== 'ACTIVE') {
            throw new Exception('Customer account is not active.');
        }

        return DB::transaction(function () use (
            $agent,
            $location,
            $operator,
            $terminal,
            $customerAccount,
            $amount,
            $idempotencyKey,
            $latitude,
            $longitude,
            $geoFencePassed,
            $performedBy,
            $customerReference,
            $narration
        ) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance || (float) $balance->available_float < $amount) {
                throw new Exception("Agent {$agent->agent_code} has insufficient float for this transaction.");
            }

            $transactionDate = now();
            $transactionNo = $this->transactionNumberService->generate('AGT');

            $agentTransaction = AgentTransaction::create([
                'transaction_no' => $transactionNo,
                'idempotency_key' => $idempotencyKey,
                'agent_id' => $agent->id,
                'agent_location_id' => $location->id,
                'agent_terminal_id' => $terminal->id,
                'agent_operator_id' => $operator->id,
                'transaction_type' => 'CASH_IN',
                'status' => 'INITIATED',
                'amount' => $amount,
                'currency' => $balance->currency,
                'customer_account_id' => $customerAccount->id,
                'customer_reference' => $customerReference,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_fence_passed' => $geoFencePassed,
                'transaction_date' => $transactionDate,
                'performed_by' => $performedBy,
            ]);

            $balance->ledger_float -= $amount;
            $balance->available_float -= $amount;
            $balance->save();

            $this->customerAccountService->deposit(
                $customerAccount,
                $amount,
                $performedBy,
                $customerReference,
                $narration ?? 'Agent cash-in'
            );

            $cashLedger = CashLedger::create([
                'reference_no' => $transactionNo,
                'branch_id' => $agent->branch_id,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_CASH_IN',
                'source_type' => AgentTransaction::class,
                'source_id' => $agentTransaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'AGENCY_FLOAT',
                'credit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Agent cash-in',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            $agentTransaction->update([
                'status' => 'COMPLETED',
                'posted_at' => now(),
            ]);

            $terminal->update([
                'last_transaction_at' => now(),
                'last_latitude' => $latitude,
                'last_longitude' => $longitude,
            ]);

            return $agentTransaction->fresh();
        });
    }

    protected function isWithinGeoFence(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $radiusMetres
    ): bool {
        $earthRadiusMetres = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceMetres = $earthRadiusMetres * $c;

        return $distanceMetres <= $radiusMetres;
    }
}
MBOS_EOF

cat > tests/Feature/AgentCashInServiceTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
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
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentCashInServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeReadyAgentContext(float $floatAmount = 200000, array $agentOverrides = []): array
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

        $vaultBranch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
        $vault = \App\Models\Vault::create([
            'branch_id' => $vaultBranch->id,
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        $vaultFunder = User::factory()->create();
        app(VaultTransactionService::class)->deposit($vault, $floatAmount + 100000, $vaultFunder->id);
        app(AgentFloatService::class)->allocateFloat($vault, $agent, $floatAmount, $vaultFunder->id);

        return [
            'agent' => $agent,
            'location' => $location,
            'operator' => $operator,
            'terminal' => $terminal,
        ];
    }

    protected function makeActiveCustomerAccount(): CustomerAccount
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
            'ledger_balance' => 0,
            'available_balance' => 0,
        ]);

        return $account;
    }

    public function test_valid_cash_in_succeeds(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $result = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals('COMPLETED', $result->status);
        $this->assertEquals('CASH_IN', $result->transaction_type);
        $this->assertTrue($result->geo_fence_passed);
    }

    public function test_cash_in_rejected_for_inactive_agent(): void
    {
        $context = $this->makeReadyAgentContext(200000, ['status' => AgentStatus::SUSPENDED->value]);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot transact');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_cash_in_rejected_for_insufficient_float(): void
    {
        $context = $this->makeReadyAgentContext(10000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('insufficient float');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );
    }

    public function test_duplicate_idempotency_key_returns_original_response(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $first = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $second = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            $idempotencyKey,
            6.5244000,
            3.3792000,
            $user->id
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AgentTransaction::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_customer_account_credited_correctly(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $balance = CustomerAccountBalance::where('customer_account_id', $customerAccount->id)->first();
        $this->assertEquals(50000, (float) $balance->available_balance);
    }

    public function test_agent_float_updated_correctly(): void
    {
        $context = $this->makeReadyAgentContext(200000);
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $balance = AgentBalance::where('agent_id', $context['agent']->id)->first();
        $this->assertEquals(150000, (float) $balance->available_float, 'Cash-in debits agent float per Blueprint §13.2, not credits it.');
    }

    public function test_gl_posting_balances(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $result = app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            6.5244000,
            3.3792000,
            $user->id
        );

        $journalEntries = GlJournal::where('reference', $result->transaction_no)->get();
        $this->assertCount(2, $journalEntries, 'Cash-in should post exactly 2 balanced GlJournal rows.');
    }

    public function test_transaction_outside_geo_fence_is_rejected(): void
    {
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('geo-fence');

        app(AgentCashInService::class)->cashIn(
            $context['operator'],
            $context['terminal'],
            $customerAccount,
            50000,
            (string) Str::uuid(),
            7.3775000,
            3.9470000,
            $user->id
        );
    }
}
MBOS_EOF

echo "AG-07 Part 2 of 2 applied (service, tests). Next: php artisan migrate --path=database/migrations/2026_08_12_000001_create_agent_transactions_table.php && php artisan test --filter=AgentCashInServiceTest"