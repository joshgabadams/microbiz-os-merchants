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
