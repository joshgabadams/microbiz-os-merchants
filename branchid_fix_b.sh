#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > tests/Feature/FixedDepositTest.php << 'MBOS_EOF'
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
MBOS_EOF

cat > tests/Feature/GlChartMirrorTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Models\FixedDeposit;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\User;
use App\Services\Accounting\CustomerAccountGlResolver;
use App\Services\Deposits\FixedDepositService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlChartMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    public function test_seeder_creates_all_57_accounts_with_no_duplicate_fineract_gl_id(): void
    {
        $this->assertEquals(57, GlAccount::count());
        $this->assertEquals(57, GlAccount::distinct('fineract_gl_id')->count('fineract_gl_id'));
    }

    protected function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);
    }

    protected function makeAccount(string $accountType, ?string $productCode): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        return CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'account_type' => $accountType,
            'product_code' => $productCode,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_resolver_defaults_savings_with_no_product_code_to_regular(): void
    {
        $account = $this->makeAccount('SAVINGS', null);

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_SAVINGS_REGULAR', $key);
    }

    public function test_resolver_resolves_kids_savings_product_correctly(): void
    {
        $account = $this->makeAccount('SAVINGS', 'KIDS');

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_SAVINGS_KIDS', $key);
    }

    public function test_resolver_defaults_current_with_no_product_code_to_individual(): void
    {
        $account = $this->makeAccount('CURRENT', null);

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_CURRENT_INDIVIDUAL', $key);
    }

    public function test_resolver_resolves_corporate_current_product_correctly(): void
    {
        $account = $this->makeAccount('CURRENT', 'CORPORATE');

        $key = app(CustomerAccountGlResolver::class)->resolve($account);

        $this->assertEquals('CUSTOMER_CURRENT_CORPORATE', $key);
    }

    protected function makeFundedAccount(float $balance, string $accountType = 'SAVINGS', ?string $productCode = null): CustomerAccount
    {
        $account = $this->makeAccount($accountType, $productCode);

        CustomerAccountBalance::create([
            'customer_account_id' => $account->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $account;
    }

    public function test_fd_booking_at_standard_365_tenor_posts_to_that_exact_gl_account(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 365, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_365')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a journal entry against FIXED_DEPOSIT_365 for a 365-day booking.');
    }

    public function test_fd_booking_at_non_standard_45_day_tenor_buckets_to_nearest_standard_60(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 45, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_60')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a 45-day booking to bucket to the nearest standard tenor, FIXED_DEPOSIT_60.');
    }

    public function test_fd_booking_at_non_standard_400_day_tenor_buckets_to_nearest_standard_365(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000);

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 400, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'FIXED_DEPOSIT_365')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected a 400-day booking to bucket to the nearest standard tenor, FIXED_DEPOSIT_365.');
    }

    public function test_fd_booking_from_current_account_posts_to_current_gl_not_savings(): void
    {
        $user = User::factory()->create();
        $branch = $this->makeBranch();
        $source = $this->makeFundedAccount(200000, 'CURRENT', 'CORPORATE');

        $fd = app(FixedDepositService::class)->book(
            $source, $source, 100000, 10.0, 2.0, 0, 90, $branch->id, $user->id
        );

        $expectedAccount = GlAccount::where('usage', 'CUSTOMER_CURRENT_CORPORATE')->firstOrFail();

        $journalEntry = GlJournal::where('reference', $fd->fd_no)
            ->where('gl_account_id', $expectedAccount->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Expected booking from a CORPORATE current account to post against CUSTOMER_CURRENT_CORPORATE.');
    }
}
MBOS_EOF

echo "branch_id fix Part B applied (tests)."