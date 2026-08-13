<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Reconciliation;
use App\Models\Teller;
use App\Models\TillSession;
use App\Models\User;
use App\Services\Accounting\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Reconciliation Test Branch',
            'code' => 'REC-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function createTeller(
        Branch $branch,
        array $overrides = []
    ): Teller {
        return Teller::create(array_merge([
            'branch_id' => $branch->id,
            'vault_id' => null,
            'gl_account_id' => null,
            'user_id' => User::factory()->create()->id,
            'teller_code' => 'TLR-'.uniqid(),
            'staff_code' => 'STF-'.uniqid(),
            'display_name' => 'Reconciliation Test Teller',
            'daily_limit' => 1000000,
            'opening_cash_limit' => 500000,
            'minimum_cash' => 0,
            'maximum_cash' => 1000000,
            'active' => true,
            'status' => 'CLOSED',
        ], $overrides));
    }

    protected function createOpenSession(
        float $expectedCash = 10000
    ): TillSession {
        $branch = $this->createBranch();

        $teller = $this->createTeller($branch);

        $user = User::factory()->create();

        return TillSession::create([
            'session_reference' => 'TS-'.uniqid(),
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'branch_business_day_id' => null,
            'vault_id' => $teller->vault_id,
            'opened_by' => $user->id,
            'closed_by' => null,
            'opening_float' => $expectedCash,
            'current_balance' => $expectedCash,
            'expected_cash' => $expectedCash,
            'physical_cash' => null,
            'variance' => 0,
            'status' => 'OPEN',
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }

    public function test_open_till_session_can_be_reconciled_when_cash_matches(): void
    {
        $session = $this->createOpenSession(10000);

        $user = User::factory()->create();

        $reconciliation = app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                10000,
                $user->id,
                'End of shift reconciliation.'
            );

        $this->assertSame(
            'MATCHED',
            $reconciliation->status
        );

        $this->assertSame(
            $session->id,
            $reconciliation->till_session_id
        );

        $this->assertSame(
            $session->teller_id,
            $reconciliation->teller_id
        );

        $this->assertSame(
            $session->branch_id,
            $reconciliation->branch_id
        );

        $this->assertEquals(
            10000,
            (float) $reconciliation->system_balance
        );

        $this->assertEquals(
            10000,
            (float) $reconciliation->physical_cash
        );

        $this->assertEquals(
            0,
            (float) $reconciliation->variance
        );

        $this->assertSame(
            $user->id,
            $reconciliation->reconciled_by
        );

        $this->assertNull(
            $reconciliation->approved_by
        );

        $this->assertSame(
            'End of shift reconciliation.',
            $reconciliation->notes
        );

        $this->assertNotNull(
            $reconciliation->reconciled_at
        );

        $this->assertDatabaseHas(
            'reconciliations',
            [
                'id' => $reconciliation->id,
                'till_session_id' => $session->id,
                'status' => 'MATCHED',
            ]
        );
    }

    public function test_reconciliation_records_shortage_as_mismatch(): void
    {
        $session = $this->createOpenSession(10000);

        $reconciliation = app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                9500
            );

        $this->assertSame(
            'MISMATCHED',
            $reconciliation->status
        );

        $this->assertEquals(
            10000,
            (float) $reconciliation->system_balance
        );

        $this->assertEquals(
            9500,
            (float) $reconciliation->physical_cash
        );

        $this->assertEquals(
            -500,
            (float) $reconciliation->variance
        );
    }

    public function test_reconciliation_records_overage_as_mismatch(): void
    {
        $session = $this->createOpenSession(10000);

        $reconciliation = app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                10500
            );

        $this->assertSame(
            'MISMATCHED',
            $reconciliation->status
        );

        $this->assertEquals(
            500,
            (float) $reconciliation->variance
        );
    }

    public function test_reconciliation_updates_session_physical_cash_and_variance(): void
    {
        $session = $this->createOpenSession(15000);

        app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                14750
            );

        $session->refresh();

        $this->assertEquals(
            14750,
            (float) $session->physical_cash
        );

        $this->assertEquals(
            -250,
            (float) $session->variance
        );

        $this->assertEquals(
            15000,
            (float) $session->expected_cash
        );
    }

    public function test_reconciliation_does_not_close_till_session(): void
    {
        $session = $this->createOpenSession(10000);

        app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                10000
            );

        $session->refresh();

        $this->assertSame(
            'OPEN',
            $session->status
        );

        $this->assertNull(
            $session->closed_at
        );

        $this->assertNull(
            $session->closed_by
        );
    }

    public function test_negative_physical_cash_is_rejected(): void
    {
        $session = $this->createOpenSession(10000);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Physical cash cannot be negative.'
        );

        app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                -1
            );
    }

    public function test_closed_till_session_cannot_be_reconciled(): void
    {
        $session = $this->createOpenSession(10000);

        $session->update([
            'status' => 'CLOSED',
            'closed_at' => now(),
        ]);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Only an open till session can be reconciled.'
        );

        app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                10000
            );
    }

    public function test_till_session_cannot_be_reconciled_twice(): void
    {
        $session = $this->createOpenSession(10000);

        $service = app(ReconciliationService::class);

        $service->reconcileSession(
            $session,
            10000
        );

        $this->assertSame(
            1,
            Reconciliation::where(
                'till_session_id',
                $session->id
            )->count()
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'This till session has already been reconciled.'
        );

        $service->reconcileSession(
            $session,
            10000
        );
    }

    public function test_reconciliation_uses_expected_cash_not_current_balance(): void
    {
        $session = $this->createOpenSession(10000);

        $session->update([
            'current_balance' => 7000,
            'expected_cash' => 10000,
        ]);

        $reconciliation = app(ReconciliationService::class)
            ->reconcileSession(
                $session,
                10000
            );

        $this->assertEquals(
            10000,
            (float) $reconciliation->system_balance
        );

        $this->assertEquals(
            0,
            (float) $reconciliation->variance
        );

        $this->assertSame(
            'MATCHED',
            $reconciliation->status
        );
    }
}