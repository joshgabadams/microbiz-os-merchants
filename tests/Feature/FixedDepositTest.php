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
use Tests\TestCase;

class FixedDepositTest extends TestCase
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

    public function test_booking_debits_source_account_and_creates_active_fd(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source,
            $source,
            100000,
            10.0,
            2.0,
            500,
            365,
            $branch->id,
            $user->id
        );

        $this->assertEquals('ACTIVE', $fd->status);
        $this->assertEquals(100000, (float) $fd->principal_amount);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(100000, (float) $sourceBalance->available_balance);
    }

    public function test_booking_rejects_insufficient_source_balance(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(50000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 500, 365, $branch->id, $user->id
        );
    }

    public function test_liquidation_at_full_maturity_pays_correct_simple_interest(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-MATURE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_AT_MATURITY', $result['fixed_deposit']->status);
        $this->assertEquals(10000, (float) $result['calculation']['net_interest']);
        $this->assertEquals(110000, (float) $result['calculation']['total_payout']);
        $this->assertEquals(0, (float) $result['calculation']['fee_applied']);
    }

    public function test_early_liquidation_applies_reduced_rate_and_penalty_fee(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-EARLY',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 50,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $this->assertEquals('LIQUIDATED_EARLY', $result['fixed_deposit']->status);
        $this->assertEquals(164.38, (float) $result['calculation']['gross_interest']);
        $this->assertEquals(50, (float) $result['calculation']['fee_applied']);
        $this->assertEquals(114.38, (float) $result['calculation']['net_interest']);
    }

    public function test_early_liquidation_fee_never_makes_interest_negative(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-FEE-EXCEEDS',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 500,
            'tenor_days' => 365,
            'start_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(335),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $result = app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $this->assertEquals(0, (float) $result['calculation']['net_interest']);
        $this->assertEquals(100000, (float) $result['calculation']['total_payout']);
    }

    public function test_already_liquidated_fd_cannot_be_liquidated_again(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-DOUBLE',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $source->id,
            'principal_amount' => 50000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 90,
            'start_date' => now()->subDays(90),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        $service = app(FixedDepositService::class);
        $service->liquidate($fd, $branch->id, $user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already');

        $service->liquidate($fd->fresh(), $branch->id, $user->id);
    }

    public function test_settlement_account_receives_principal_plus_interest(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);
        $settlement = $this->makeFundedAccount(0);

        $fd = FixedDeposit::create([
            'fd_no' => 'FD-TEST-SETTLEMENT',
            'customer_account_id' => $source->id,
            'settlement_account_id' => $settlement->id,
            'principal_amount' => 100000,
            'currency' => 'NGN',
            'interest_rate' => 10.0,
            'pre_liquidation_rate' => 2.0,
            'pre_liquidation_penalty_fee' => 0,
            'tenor_days' => 365,
            'start_date' => now()->subDays(365),
            'maturity_date' => now()->subDay(),
            'status' => 'ACTIVE',
            'booked_by' => $user->id,
        ]);

        app(FixedDepositService::class)->liquidate($fd, $branch->id, $user->id);

        $settlementBalance = CustomerAccountBalance::where('customer_account_id', $settlement->id)->first();

        $this->assertEquals(110000, (float) $settlementBalance->available_balance);

        $sourceBalance = CustomerAccountBalance::where('customer_account_id', $source->id)->first();
        $this->assertEquals(200000, (float) $sourceBalance->available_balance);
    }
}
