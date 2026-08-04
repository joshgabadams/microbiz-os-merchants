<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\User;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class FixedDepositBackdatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function makeFundedAccount(float $balance): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $account = CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'status' => 'ACTIVE',
        ]);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_backdating_within_window_with_reason_succeeds(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Correcting a delayed paper-form entry.'
        );

        $this->assertEquals(Carbon::today()->subDays(10)->toDateString(), $fd->start_date->toDateString());
        $this->assertEquals('Correcting a delayed paper-form entry.', $fd->backdate_reason);
        $this->assertEquals(Carbon::today()->subDays(10)->addDays(365)->toDateString(), $fd->maturity_date->toDateString());
    }

    public function test_backdating_without_reason_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('backdate reason is required');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            null
        );
    }

    public function test_backdating_beyond_max_window_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('more than 30 days');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(31),
            'Trying to backdate too far.'
        );
    }

    public function test_future_start_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be in the future');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::tomorrow(),
            'Should never get this far.'
        );
    }

    public function test_non_backdated_booking_does_not_require_a_reason(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $this->assertNull($fd->backdate_reason);
        $this->assertEquals(Carbon::today()->toDateString(), $fd->start_date->toDateString());
    }

    public function test_backdated_fd_cannot_be_liquidated_before_minimum_holding_period(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Backdated for testing the holding period.'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be liquidated for another');

        app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);
    }

    public function test_backdated_fd_can_be_liquidated_after_minimum_holding_period(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id,
            null,
            Carbon::today()->subDays(10),
            'Backdated for testing the holding period.'
        );

        FixedDeposit::where('id', $fd->id)->update([
            'created_at' => now()->subHours(25),
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd->fresh(), $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
        $this->assertGreaterThan(0, (float) $result['calculation']['net_interest']);
    }

    public function test_non_backdated_fd_has_no_holding_period_restriction(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $result = app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
    }
}
