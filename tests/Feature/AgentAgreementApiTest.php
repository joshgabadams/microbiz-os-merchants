<?php

namespace Tests\Feature;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentAgreementTemplate;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PaymentsRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentAgreementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentsRbacSeeder::class);
    }

    protected function attachRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->attach($role->id, [
            'assigned_at' => now(),
        ]);

        $user->unsetRelation('roles');
    }

    protected function makeAgentAgreementPending(int $createdBy): Agent
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        return Agent::create([
            'agent_code' => 'AGT-'.uniqid(),
            'agent_type' => 'INDIVIDUAL',
            'legal_name' => 'Agreement Test Agent',
            'phone' => '08000000000',
            'branch_id' => $branch->id,
            'status' => AgentStatus::AGREEMENT_PENDING->value,
            'kyc_status' => 'COMPLETED',
            'created_by' => $createdBy,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);
    }

    protected function makeApprovedTemplate(
        ?User $creator = null
    ): AgentAgreementTemplate {
        $creator ??= User::factory()->create();
        $approver = User::factory()->create();

        return AgentAgreementTemplate::create([
            'name' => 'MicroBiz Standard Agent Banking Agreement',
            'version' => '1.0',
            'status' => 'APPROVED',

            'legal_clauses' =>
                'Standard MicroBiz Agent Banking Agreement clauses.',

            'default_operator_training_obligations' =>
                'All operators must complete required training.',

            'default_escalation_contacts' => [
                [
                    'department' => 'Agency Banking Operations',
                    'email' => 'agency-ops@example.com',
                ],
            ],

            'governing_law' => 'Federal Republic of Nigeria',

            'created_by' => $creator->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    protected function agreementPayload(
        AgentAgreementTemplate $template
    ): array {
        return [
            'agreement_template_id' => $template->id,

            'effective_date' => now()->toDateString(),

            'expiry_date' => now()
                ->addYear()
                ->toDateString(),

            'renewal_due_date' => now()
                ->addMonths(11)
                ->toDateString(),

            'initial_term_months' => 12,

            'agent_termination_notice_days' => 30,

            'microbiz_termination_notice_days' => 30,

            'dispute_resolution_method' => 'ARBITRATION',

            'arbitration_seat' => 'Lagos, Nigeria',

            'governing_law' => 'Federal Republic of Nigeria',

            'special_conditions' =>
                'Agent must comply with all approved operational controls.',

            /*
             * Schedule 2
             *
             * The request validator expects an array of service
             * definitions, not an array of strings.
             */
            'permitted_services' => [
                [
                    'service' => 'CASH_IN',
                    'enabled' => true,
                    'limit' => 500000,
                    'notes' => 'Cash-in service enabled.',
                ],
                [
                    'service' => 'CASH_OUT',
                    'enabled' => true,
                    'limit' => 500000,
                    'notes' => 'Cash-out service enabled.',
                ],
            ],

            /*
             * Schedule 3
             *
             * Commercial terms are also service-specific records.
             */
            'commercial_terms' => [
                [
                    'service' => 'CASH_IN',

                    'customer_fee' =>
                        'As approved by MicroBiz tariff.',

                    'agent_commission' =>
                        'As approved by MicroBiz commission schedule.',

                    'settlement_timing' =>
                        'Real-time',
                ],
                [
                    'service' => 'CASH_OUT',

                    'customer_fee' =>
                        'As approved by MicroBiz tariff.',

                    'agent_commission' =>
                        'As approved by MicroBiz commission schedule.',

                    'settlement_timing' =>
                        'Real-time',
                ],
            ],
        ];
    }

    public function test_user_without_agreement_permission_is_blocked(): void
    {
        $registrant = User::factory()->create();
        $user = User::factory()->create();

        $this->attachRole(
            $user,
            'agent-kyc-officer'
        );

        Sanctum::actingAs($user);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        /*
         * Although this user should be rejected by permission
         * middleware before request processing, keep the request
         * payload valid so this test remains focused exclusively
         * on authorization.
         */
        $template = $this->makeApprovedTemplate();

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload($template)
        );

        $response->assertForbidden();
    }

    public function test_agreement_officer_can_create_agreement(): void
    {
        $registrant = User::factory()->create();
        $agreementOfficer = User::factory()->create();

        $this->attachRole(
            $agreementOfficer,
            'agent-agreement-officer'
        );

        Sanctum::actingAs($agreementOfficer);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        $template = $this->makeApprovedTemplate(
            $agreementOfficer
        );

        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload($template)
        );

        $response->assertCreated();

        $this->assertDatabaseHas('agent_agreements', [
            'agent_id' => $agent->id,
            'agreement_template_id' => $template->id,
            'version' => 1,
            'status' => 'DRAFT',
            'created_by' => $agreementOfficer->id,
        ]);
    }

    public function test_same_officer_cannot_create_and_execute_agreement(): void
    {
        $registrant = User::factory()->create();
        $officer = User::factory()->create();

        $this->attachRole(
            $officer,
            'agent-agreement-officer'
        );

        $this->attachRole(
            $officer,
            'agent-agreement-executor'
        );

        Sanctum::actingAs($officer);

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        $template = $this->makeApprovedTemplate(
            $officer
        );

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload($template)
        );

        $createResponse->assertCreated();

        $agreementId = $createResponse->json('data.id');

        $this->assertNotNull($agreementId);

        /*
         * A freshly created agreement is still DRAFT.
         *
         * Execution must therefore be rejected. This test also
         * ensures that the creator cannot bypass the agreement
         * lifecycle merely because they possess the executor role.
         */
        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements/{$agreementId}/execute"
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::AGREEMENT_PENDING->value,
            $agent->fresh()->status
        );

        $this->assertDatabaseHas('agent_agreements', [
            'id' => $agreementId,
            'agent_id' => $agent->id,
            'status' => 'DRAFT',
            'created_by' => $officer->id,
        ]);
    }

    public function test_independent_executor_cannot_execute_incomplete_agreement(): void
    {
        $registrant = User::factory()->create();
        $agreementOfficer = User::factory()->create();
        $executor = User::factory()->create();

        $this->attachRole(
            $agreementOfficer,
            'agent-agreement-officer'
        );

        $this->attachRole(
            $executor,
            'agent-agreement-executor'
        );

        $agent = $this->makeAgentAgreementPending(
            $registrant->id
        );

        Sanctum::actingAs($agreementOfficer);

        $template = $this->makeApprovedTemplate(
            $agreementOfficer
        );

        $createResponse = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements",
            $this->agreementPayload($template)
        );

        $createResponse->assertCreated();

        $agreementId = $createResponse->json('data.id');

        $this->assertNotNull($agreementId);

        /*
         * Switch identity to the independent executor.
         */
        Sanctum::actingAs($executor);

        /*
         * Independence alone is insufficient.
         *
         * The agreement has not yet passed:
         *
         * DRAFT
         * -> INTERNAL REVIEW
         * -> RISK APPROVAL
         * -> COMPLIANCE APPROVAL
         * -> LEGAL APPROVAL
         * -> BUSINESS OWNER APPROVAL
         * -> SIGNATURE
         * -> BOTH SIGNATURES RECORDED
         * -> EXECUTION
         *
         * Therefore execution must fail at this stage.
         */
        $response = $this->postJson(
            "/api/v1/agents/{$agent->id}/agreements/{$agreementId}/execute"
        );

        $response->assertUnprocessable();

        $this->assertEquals(
            AgentStatus::AGREEMENT_PENDING->value,
            $agent->fresh()->status
        );

        $this->assertDatabaseHas('agent_agreements', [
            'id' => $agreementId,
            'agent_id' => $agent->id,
            'status' => 'DRAFT',
        ]);
    }
}