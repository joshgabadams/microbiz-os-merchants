<?php

namespace Tests\Feature;

use App\Models\Branch;
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
}
