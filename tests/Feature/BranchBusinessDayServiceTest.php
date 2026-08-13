<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchBusinessDay;
use App\Models\Reconciliation;
use App\Models\Teller;
use App\Models\User;
use App\Services\Branch\BranchBusinessDayService;
use App\Services\TillSession\TillSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchBusinessDayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BranchBusinessDayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            BranchBusinessDayService::class
        );
    }

    protected function createBranch(): Branch
    {
        return Branch::create([
            'name' => 'Business Day Test Branch',
            'code' => 'BD-' . strtoupper(
                substr(uniqid(), -8)
            ),
            'office_id' => 1,
        ]);
    }

    protected function createUser(): User
    {
        return User::factory()->create();
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
        'teller_code' => 'TLR-' . uniqid(),
        'staff_code' => 'STF-' . uniqid(),
        'display_name' => 'Business Day Close Guard Teller',
        'daily_limit' => 1000000,
        'opening_cash_limit' => 500000,
        'minimum_cash' => 0,
        'maximum_cash' => 1000000,
        'active' => true,
        'status' => 'CLOSED',
    ], $overrides));
}

    public function test_business_day_can_be_opened(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $day = $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id,
            'Opening branch business day.'
        );

        $this->assertSame(
            'OPEN',
            $day->status
        );

        $this->assertSame(
            $branch->id,
            $day->branch_id
        );

        $this->assertSame(
            '2026-08-13',
            $day->business_date->toDateString()
        );

        $this->assertSame(
            $user->id,
            $day->opened_by
        );

        $this->assertNotNull(
            $day->opened_at
        );

        $this->assertNull(
            $day->closed_at
        );
    }

    public function test_branch_cannot_have_two_open_business_days(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Branch {$branch->id} already has an open business day."
        );

        $this->service->open(
            $branch->id,
            '2026-08-14',
            $user->id
        );
    }

    public function test_closed_business_date_cannot_be_reopened(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $this->service->close(
            $branch->id,
            $user->id
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Business date 2026-08-13 has already been used for branch {$branch->id}."
        );

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );
    }

    public function test_next_business_date_can_be_opened_after_previous_day_closes(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $firstDay = $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $this->service->close(
            $branch->id,
            $user->id
        );

        $secondDay = $this->service->open(
            $branch->id,
            '2026-08-14',
            $user->id
        );

        $this->assertSame(
            'CLOSED',
            $firstDay->fresh()->status
        );

        $this->assertSame(
            'OPEN',
            $secondDay->status
        );

        $this->assertSame(
            '2026-08-14',
            $secondDay->business_date->toDateString()
        );
    }

    public function test_open_business_day_can_be_closed(): void
    {
        $branch = $this->createBranch();
        $openedBy = $this->createUser();
        $closedBy = $this->createUser();

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $openedBy->id
        );

        $day = $this->service->close(
            $branch->id,
            $closedBy->id,
            'Business day completed.'
        );

        $this->assertSame(
            'CLOSED',
            $day->status
        );

        $this->assertSame(
            $closedBy->id,
            $day->closed_by
        );

        $this->assertNotNull(
            $day->closed_at
        );

        $this->assertSame(
            'Business day completed.',
            $day->notes
        );
    }

    public function test_branch_without_open_business_day_cannot_be_closed(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Branch {$branch->id} does not have an open business day."
        );

        $this->service->close(
            $branch->id,
            $user->id
        );
    }

    public function test_current_open_day_returns_open_business_day(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $created = $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $current = $this->service->currentOpenDay(
            $branch->id
        );

        $this->assertNotNull($current);

        $this->assertSame(
            $created->id,
            $current->id
        );
    }

    public function test_current_open_day_returns_null_when_branch_is_closed(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $this->service->close(
            $branch->id,
            $user->id
        );

        $this->assertNull(
            $this->service->currentOpenDay(
                $branch->id
            )
        );
    }

    public function test_require_open_day_fails_when_no_business_day_is_open(): void
    {
        $branch = $this->createBranch();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Branch {$branch->id} does not have an open business day."
        );

        $this->service->requireOpenDay(
            $branch->id
        );
    }

    public function test_is_open_reflects_business_day_state(): void
    {
        $branch = $this->createBranch();
        $user = $this->createUser();

        $this->assertFalse(
            $this->service->isOpen(
                $branch->id
            )
        );

        $this->service->open(
            $branch->id,
            '2026-08-13',
            $user->id
        );

        $this->assertTrue(
            $this->service->isOpen(
                $branch->id
            )
        );

        $this->service->close(
            $branch->id,
            $user->id
        );

        $this->assertFalse(
            $this->service->isOpen(
                $branch->id
            )
        );
    }

    public function test_different_branches_can_have_open_business_days_simultaneously(): void
    {
        $branchOne = $this->createBranch();
        $branchTwo = $this->createBranch();
        $user = $this->createUser();

        $dayOne = $this->service->open(
            $branchOne->id,
            '2026-08-13',
            $user->id
        );

        $dayTwo = $this->service->open(
            $branchTwo->id,
            '2026-08-13',
            $user->id
        );

        $this->assertSame(
            'OPEN',
            $dayOne->status
        );

        $this->assertSame(
            'OPEN',
            $dayTwo->status
        );

        $this->assertSame(
            2,
            BranchBusinessDay::where(
                'status',
                'OPEN'
            )->count()
        );
    }

    public function test_business_day_cannot_close_while_till_session_is_open(): void
{
    $branch = $this->createBranch();
    $user = $this->createUser();
    $teller = $this->createTeller($branch);

    $businessDay = $this->service->open(
        $branch->id,
        '2026-08-13',
        $user->id
    );

    $session = app(TillSessionService::class)->open([
        'teller_id' => $teller->id,
        'opened_by' => $user->id,
        'opening_float' => 10000,
    ]);

    $this->assertSame(
        $businessDay->id,
        $session->branch_business_day_id
    );

    try {
        $this->service->close(
            $branch->id,
            $user->id
        );

        $this->fail(
            'Expected business-day close to be blocked by an open till session.'
        );
    } catch (\Exception $exception) {
        $this->assertSame(
            "Branch {$branch->id} cannot close its business day while till sessions remain open.",
            $exception->getMessage()
        );
    }

    /*
     * The failed close must leave both sides of the lifecycle open.
     */
    $this->assertSame(
        'OPEN',
        $businessDay->fresh()->status
    );

    $this->assertSame(
        'OPEN',
        $session->fresh()->status
    );

    $this->assertNull(
        $businessDay->fresh()->closed_at
    );
}

    public function test_business_day_can_close_after_till_session_is_closed(): void
{
    $branch = $this->createBranch();
    $user = $this->createUser();
    $teller = $this->createTeller($branch);

    $businessDay = $this->service->open(
        $branch->id,
        '2026-08-13',
        $user->id
    );

    $session = app(TillSessionService::class)->open([
        'teller_id' => $teller->id,
        'opened_by' => $user->id,
        'opening_float' => 10000,
    ]);

    /*
     * Normal production path:
     *
     * OPEN business day
     * -> OPEN till
     * -> MATCHED reconciliation
     * -> CLOSED till
     * -> CLOSED business day
     */
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
        'notes' => 'Matched before business-day close.',
        'reconciled_at' => now(),
    ]);

    $closedSession = app(TillSessionService::class)->close(
        $session->id,
        $user->id
    );

    $this->assertSame(
        'CLOSED',
        $closedSession->status
    );

    $closedBusinessDay = $this->service->close(
        $branch->id,
        $user->id,
        'All till sessions closed.'
    );

    $this->assertSame(
        'CLOSED',
        $closedBusinessDay->status
    );

    $this->assertSame(
        $businessDay->id,
        $closedBusinessDay->id
    );

    $this->assertSame(
        $user->id,
        $closedBusinessDay->closed_by
    );

    $this->assertNotNull(
        $closedBusinessDay->closed_at
    );

    $this->assertSame(
        'All till sessions closed.',
        $closedBusinessDay->notes
    );

    $this->assertNull(
        $this->service->currentOpenDay(
            $branch->id
        )
    );
}
}
