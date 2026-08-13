<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Reconciliation;
use App\Models\Teller;
use App\Models\TillSession;
use App\Models\User;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\TillSession\TillSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillSessionBusinessDayTest extends TestCase
{
    use RefreshDatabase;

    protected function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Till Session Business Day Branch',
            'code' => 'TSBD-'.uniqid(),
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
            'display_name' => 'Till Session Business Day Teller',
            'daily_limit' => 1000000,
            'opening_cash_limit' => 500000,
            'minimum_cash' => 0,
            'maximum_cash' => 1000000,
            'active' => true,
            'status' => 'CLOSED',
        ], $overrides));
    }

    protected function openBusinessDay(
        Branch $branch,
        User $user
    ) {
        return app(BranchBusinessDayService::class)->open(
            $branch->id,
            now()->toDateString(),
            $user->id,
            'Opened for till session business day test.'
        );
    }

    public function test_till_session_cannot_open_without_open_business_day(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch
        );

        $user = User::factory()->create();

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            "Branch {$branch->id} does not have an open business day."
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);
    }

    public function test_till_session_can_open_when_branch_business_day_is_open(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch
        );

        $user = User::factory()->create();

        $businessDay = $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        $this->assertSame(
            'OPEN',
            $session->status
        );

        $this->assertSame(
            $teller->id,
            $session->teller_id
        );

        $this->assertSame(
            $branch->id,
            $session->branch_id
        );

        $this->assertSame(
            $businessDay->id,
            $session->branch_business_day_id
        );

        $this->assertSame(
            $user->id,
            $session->opened_by
        );

        $this->assertNotNull(
            $session->opened_at
        );
    }

    public function test_till_session_is_bound_to_exact_open_business_day(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch
        );

        $user = User::factory()->create();

        $businessDay = $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 25000,
        ]);

        $this->assertDatabaseHas(
            'till_sessions',
            [
                'id' => $session->id,
                'teller_id' => $teller->id,
                'branch_id' => $branch->id,
                'branch_business_day_id' => $businessDay->id,
                'status' => 'OPEN',
            ]
        );

        $this->assertTrue(
            $session->businessDay->is(
                $businessDay
            )
        );
    }

    public function test_inactive_teller_cannot_open_till_session(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch,
            [
                'active' => false,
            ]
        );

        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Cannot open a till session for an inactive teller.'
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);
    }

    public function test_teller_cannot_have_two_open_till_sessions(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch
        );

        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'This teller already has an open session.'
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 5000,
        ]);
    }

    public function test_negative_opening_float_is_rejected(): void
    {
        $branch = $this->createBranch();

        $teller = $this->createTeller(
            $branch
        );

        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Opening float cannot be negative.'
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => -1,
        ]);
    }

    public function test_open_till_session_for_another_branch_does_not_satisfy_business_day_requirement(): void
    {
        $branchOne = $this->createBranch();

        $branchTwo = $this->createBranch();

        $user = User::factory()->create();

        $this->openBusinessDay(
            $branchOne,
            $user
        );

        $teller = $this->createTeller(
            $branchTwo
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            "Branch {$branchTwo->id} does not have an open business day."
        );

        app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);
    }

    public function test_till_session_cannot_close_without_reconciliation(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Till session must be reconciled before it can be closed.'
        );

        app(TillSessionService::class)->close(
            $session->id,
            $user->id
        );
    }

    public function test_matched_reconciliation_allows_till_session_to_close(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'system_balance' => 10000,
            'physical_cash' => 10000,
            'variance' => 0,
            'status' => 'MATCHED',
            'reconciled_by' => $user->id,
            'approved_by' => null,
            'notes' => 'Matched reconciliation.',
            'reconciled_at' => now(),
        ]);

        $closed = app(TillSessionService::class)->close(
            $session->id,
            $user->id
        );

        $this->assertSame(
            'CLOSED',
            $closed->status
        );

        $this->assertSame(
            $user->id,
            $closed->closed_by
        );

        $this->assertNotNull(
            $closed->closed_at
        );

        $this->assertDatabaseHas(
            'till_sessions',
            [
                'id' => $session->id,
                'status' => 'CLOSED',
                'closed_by' => $user->id,
            ]
        );
    }

    public function test_mismatched_reconciliation_cannot_close_till_session(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'system_balance' => 10000,
            'physical_cash' => 9500,
            'variance' => -500,
            'status' => 'MISMATCHED',
            'reconciled_by' => $user->id,
            'approved_by' => null,
            'notes' => 'Cash shortage.',
            'reconciled_at' => now(),
        ]);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Till session cannot be closed while reconciliation is mismatched.'
        );

        app(TillSessionService::class)->close(
            $session->id,
            $user->id
        );
    }

    public function test_closed_till_session_cannot_be_closed_again(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'system_balance' => 10000,
            'physical_cash' => 10000,
            'variance' => 0,
            'status' => 'MATCHED',
            'reconciled_by' => $user->id,
            'approved_by' => null,
            'notes' => null,
            'reconciled_at' => now(),
        ]);

        $service = app(TillSessionService::class);

        $service->close(
            $session->id,
            $user->id
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Only an open till session can be closed.'
        );

        $service->close(
            $session->id,
            $user->id
        );
    }

    public function test_till_session_cannot_close_after_its_business_day_is_closed(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $businessDay = $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'system_balance' => 10000,
            'physical_cash' => 10000,
            'variance' => 0,
            'status' => 'MATCHED',
            'reconciled_by' => $user->id,
            'approved_by' => null,
            'notes' => null,
            'reconciled_at' => now(),
        ]);

        $businessDay->update([
            'status' => 'CLOSED',
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            "Branch {$branch->id} does not have an open business day."
        );

        app(TillSessionService::class)->close(
            $session->id,
            $user->id
        );
    }

    public function test_till_session_cannot_close_against_a_different_open_business_day(): void
    {
        $branch = $this->createBranch();
        $teller = $this->createTeller($branch);
        $user = User::factory()->create();

        $originalBusinessDay = $this->openBusinessDay(
            $branch,
            $user
        );

        $session = app(TillSessionService::class)->open([
            'teller_id' => $teller->id,
            'opened_by' => $user->id,
            'opening_float' => 10000,
        ]);

        Reconciliation::create([
            'till_session_id' => $session->id,
            'teller_id' => $teller->id,
            'branch_id' => $branch->id,
            'system_balance' => 10000,
            'physical_cash' => 10000,
            'variance' => 0,
            'status' => 'MATCHED',
            'reconciled_by' => $user->id,
            'approved_by' => null,
            'notes' => null,
            'reconciled_at' => now(),
        ]);

        $originalBusinessDay->update([
            'status' => 'CLOSED',
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);

        app(BranchBusinessDayService::class)->open(
            $branch->id,
            now()->addDay()->toDateString(),
            $user->id,
            'Next business day.'
        );

        $this->expectException(\Exception::class);

        $this->expectExceptionMessage(
            'Till session does not belong to the current open business day.'
        );

        app(TillSessionService::class)->close(
            $session->id,
            $user->id
        );
    }
}
