<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentReconciliation;
use App\Models\AgentTerminal;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\User;
use App\Models\Vault;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\CashManagement\AgentFloatService;
use App\Services\Payments\AgentCashInService;
use App\Services\Payments\AgentReconciliationService;
use App\Services\Payments\AgentServiceConfigurationService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    protected function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function makeReadyAgentContext(float $floatAmount = 300000): array
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);
        $registrant = User::factory()->create();

        app(BranchBusinessDayService::class)->open($branch->id, now()->toDateString(), $registrant->id);

        $agent = Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::ACTIVE->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $registrant->id,
            'single_transaction_limit' => 1000000,
        ]);

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
            'last_heartbeat_at' => now(),
            'registered_latitude' => 6.5244000,
            'registered_longitude' => 3.3792000,
            'geo_fence_radius_metres' => 100,
            'activated_at' => now(),
        ]);

        $vaultBranch = Branch::create(['name' => 'Vault Branch', 'code' => 'VB-'.uniqid(), 'office_id' => 1]);
        $vault = Vault::create(['branch_id' => $vaultBranch->id, 'code' => 'VLT-'.uniqid(), 'name' => 'Test Vault', 'active' => true]);
        $vaultFunder = User::factory()->create();
        app(VaultTransactionService::class)->deposit($vault, $floatAmount + 100000, $vaultFunder->id);
        app(AgentFloatService::class)->allocateFloat($vault, $agent, $floatAmount, $vaultFunder->id);

        $serviceEnabler = User::factory()->create();
        app(AgentServiceConfigurationService::class)->enableService($agent, 'CASH_IN', $serviceEnabler->id);

        return ['agent' => $agent, 'operator' => $operator, 'terminal' => $terminal];
    }

    protected function makeActiveCustomerAccount(): CustomerAccount
    {
        $customer = Customer::create(['customer_no' => 'CUS-'.uniqid(), 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $account = CustomerAccount::create(['customer_id' => $customer->id, 'account_no' => 'ACC-'.uniqid(), 'status' => 'ACTIVE']);
        CustomerAccountBalance::create(['customer_account_id' => $account->id, 'currency' => 'NGN', 'ledger_balance' => 0, 'available_balance' => 0]);

        return $account;
    }

    public function test_dashboard_reports_correct_agent_status_counts(): void
    {
        $this->authenticatedUser();

        $context = $this->makeReadyAgentContext();

        $branch = Branch::create(['name' => 'B2', 'code' => 'B2-'.uniqid(), 'office_id' => 1]);
        $creator = User::factory()->create();
        Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Suspended Agent',
            'phone' => '08011111111',
            'branch_id' => $branch->id,
            'status' => AgentStatus::SUSPENDED->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $creator->id,
        ]);

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.agents.total', 2);
        $response->assertJsonPath('data.agents.active', 1);
        $response->assertJsonPath('data.agents.suspended', 1);
    }

    public function test_dashboard_reports_correct_terminal_counts(): void
    {
        $this->authenticatedUser();
        $this->makeReadyAgentContext();

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.terminals.active', 1);
        $response->assertJsonPath('data.terminals.non_compliant', 1);
    }

    public function test_dashboard_reports_correct_daily_cash_in_value(): void
    {
        $this->authenticatedUser();
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $performer = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'], $context['terminal'], $customerAccount, 50000,
            (string) Str::uuid(), 6.5244000, 3.3792000, $performer->id
        );

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.daily_transaction_values.cash_in', 50000);
        $response->assertJsonPath('data.daily_transaction_values.total', 50000);
    }

    public function test_dashboard_reports_reconciliation_exceptions_from_latest_reconciliation_only(): void
    {
        $this->authenticatedUser();
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $performer = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'], $context['terminal'], $customerAccount, 50000,
            (string) Str::uuid(), 6.5244000, 3.3792000, $performer->id
        );

        AgentBalance::where('agent_id', $context['agent']->id)->update(['declared_physical_cash' => 10000]);
        app(AgentReconciliationService::class)->reconcileAgent($context['agent']->fresh());

        AgentBalance::where('agent_id', $context['agent']->id)->update(['declared_physical_cash' => 50000]);
        app(AgentReconciliationService::class)->reconcileAgent($context['agent']->fresh());

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.reconciliation.exceptions', 0);
    }

    public function test_dashboard_reports_float_shortage_from_latest_mismatched_reconciliation(): void
    {
        $this->authenticatedUser();
        $context = $this->makeReadyAgentContext();
        $customerAccount = $this->makeActiveCustomerAccount();
        $performer = User::factory()->create();

        app(AgentCashInService::class)->cashIn(
            $context['operator'], $context['terminal'], $customerAccount, 50000,
            (string) Str::uuid(), 6.5244000, 3.3792000, $performer->id
        );

        AgentBalance::where('agent_id', $context['agent']->id)->update(['declared_physical_cash' => 10000]);
        app(AgentReconciliationService::class)->reconcileAgent($context['agent']->fresh());

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.reconciliation.exceptions', 1);
        $response->assertJsonPath('data.reconciliation.float_shortages', 1);
    }

    public function test_dashboard_honestly_flags_unbuilt_widgets_as_not_yet_tracked(): void
    {
        $this->authenticatedUser();

        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.complaints.not_yet_tracked', true);
        $response->assertJsonPath('data.tessa_alerts.not_yet_tracked', true);
        $response->assertJsonPath('data.high_risk_agents.not_yet_tracked', true);
    }

    public function test_unauthenticated_user_cannot_view_dashboard(): void
    {
        $response = $this->getJson('/api/v1/reports/agency-dashboard');

        $response->assertUnauthorized();
    }
}
