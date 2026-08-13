<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchBusinessDay;
use App\Models\User;
use App\Services\Branch\BranchBusinessDayService;
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
}
