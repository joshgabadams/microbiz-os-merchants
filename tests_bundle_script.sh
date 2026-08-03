#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os
mkdir -p tests/Feature

cat > phpunit.xml << 'MBOS_EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <!--
            Tests run against a real MySQL database, not SQLite. Several
            migrations use raw MySQL-specific SQL (ALTER TABLE ... MODIFY
            ... ENUM(...), SET FOREIGN_KEY_CHECKS) that SQLite cannot
            execute at all, and the original enum() lists in the base
            migrations don't match the values the actual code uses
            (e.g. 'FLOAT_RECEIVED' vs the real 'RECEIVE_FLOAT') -- so
            SQLite's own CHECK constraint would reject real data even if
            the MySQL-only ALTER statements were skipped. Rather than
            retrofit every migration to be cross-database-safe, tests use
            a dedicated MySQL database instead.

            Create it once with: mysql -u root -e "CREATE DATABASE IF NOT EXISTS microbiz_os_testing;"
        -->
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="3306"/>
        <env name="DB_DATABASE" value="microbiz_os_testing"/>
        <env name="DB_USERNAME" value="root"/>
        <env name="DB_PASSWORD" value=""/>
        <env name="DB_URL" value=""/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
MBOS_EOF

cat > tests/Feature/CustomerCashGlPostingTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\GlJournal;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use App\Services\Customer\CustomerCashService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the exact bug found and fixed this session: CustomerCashService
 * used to fake the "posted" state by setting status=APPROVED directly and
 * firing FinancialTransactionPosted by hand, without ever calling
 * GlPostingService -- meaning no GlJournal rows were ever created for
 * customer deposits/withdrawals. This locks in the fix.
 */
class CustomerCashGlPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeTellerWithBalance(float $openingBalance = 0): Teller
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-001',
            'office_id' => 1,
        ]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-TEST-'.uniqid(),
            'display_name' => 'Test Teller',
            'status' => 'OPEN',
            'active' => true,
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => $openingBalance,
            'available_balance' => $openingBalance,
        ]);

        return $teller;
    }

    protected function makeActiveCustomerAccount(): CustomerAccount
    {
        $customer = Customer::create([
            'customer_no' => 'CUS-TEST-'.uniqid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        return CustomerAccount::create([
            'customer_id' => $customer->id,
            'account_no' => 'ACC-TEST-'.uniqid(),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_deposit_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(0);
        $account = $this->makeActiveCustomerAccount();

        $result = app(CustomerCashService::class)->deposit(
            $teller,
            $account,
            1000,
            $user->id
        );

        $referenceNo = $result['cash_ledger']->reference_no;

        $journalEntries = GlJournal::where('reference', $referenceNo)->get();

        $this->assertCount(
            2,
            $journalEntries,
            'Expected exactly one DEBIT and one CREDIT journal entry for a deposit.'
        );

        $debit = $journalEntries->firstWhere('entry_type', 'DEBIT');
        $credit = $journalEntries->firstWhere('entry_type', 'CREDIT');

        $this->assertNotNull($debit, 'Missing DEBIT journal entry.');
        $this->assertNotNull($credit, 'Missing CREDIT journal entry.');
        $this->assertEquals(1000, (float) $debit->amount);
        $this->assertEquals(1000, (float) $credit->amount);

        $this->assertEquals('APPROVED', $result['cash_ledger']->fresh()->status);
    }

    public function test_withdraw_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(5000);
        $account = $this->makeActiveCustomerAccount();

        $result = app(CustomerCashService::class)->withdraw(
            $teller,
            $account,
            500,
            $user->id
        );

        $referenceNo = $result['cash_ledger']->reference_no;

        $journalEntries = GlJournal::where('reference', $referenceNo)->get();

        $this->assertCount(2, $journalEntries);

        $debit = $journalEntries->firstWhere('entry_type', 'DEBIT');
        $credit = $journalEntries->firstWhere('entry_type', 'CREDIT');

        $this->assertEquals(500, (float) $debit->amount);
        $this->assertEquals(500, (float) $credit->amount);
        $this->assertEquals('APPROVED', $result['cash_ledger']->fresh()->status);
    }

    public function test_withdraw_rejects_when_teller_cash_is_insufficient(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(100);
        $account = $this->makeActiveCustomerAccount();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient teller cash balance.');

        app(CustomerCashService::class)->withdraw(
            $teller,
            $account,
            5000,
            $user->id
        );
    }
}
MBOS_EOF

cat > tests/Feature/PermissionMiddlewareTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks in the exact allow/deny behavior manually verified via curl this
 * session: a user with the required permission succeeds, a user without
 * it gets a 403 with the expected message.
 */
class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function makeOpenTeller(): Teller
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-001',
            'office_id' => 1,
        ]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-TEST-'.uniqid(),
            'display_name' => 'Test Teller',
            'status' => 'OPEN',
            'active' => true,
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
        ]);

        return $teller;
    }

    public function test_user_without_tellers_manage_permission_is_blocked(): void
    {
        $teller = $this->makeOpenTeller();

        // A plain user with no roles/permissions at all.
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Missing required permission: tellers.manage.',
        ]);
    }

    public function test_user_with_tellers_manage_permission_succeeds(): void
    {
        $teller = $this->makeOpenTeller();

        $user = User::factory()->create();
        $tellerOfficerRole = Role::where('name', 'teller-officer')->firstOrFail();
        $user->roles()->attach($tellerOfficerRole->id);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_unauthenticated_request_is_rejected_before_permission_check(): void
    {
        $teller = $this->makeOpenTeller();

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(401);
    }
}
MBOS_EOF

cat > tests/Feature/BalanceProtectionTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GlAccount;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultBalance;
use App\Services\Teller\TellerTransactionService;
use App\Services\Vault\VaultTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the negative-balance protection audited (by reading every debit
 * path) in step 2 of the production checklist. If any of these guards are
 * ever accidentally removed during a future refactor, these tests fail
 * immediately instead of silently allowing an overdraft.
 */
class BalanceProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Real seeder, not a hand-rolled single GL account -- withdraw()
        // needs both CASH_CONTROL and VAULT_CASH to resolve correctly.
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

    protected function vaultCashGlAccountId(): int
    {
        return GlAccount::where('usage', 'VAULT_CASH')->firstOrFail()->id;
    }

    public function test_vault_withdraw_rejects_when_balance_is_insufficient(): void
    {
        $branch = $this->makeBranch();
        $user = User::factory()->create();

        $vault = Vault::create([
            'branch_id' => $branch->id,
            'gl_account_id' => $this->vaultCashGlAccountId(),
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        VaultBalance::create([
            'vault_id' => $vault->id,
            'currency' => 'NGN',
            'ledger_balance' => 100,
            'available_balance' => 100,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient vault balance.');

        app(VaultTransactionService::class)->withdraw($vault, 5000, $user->id);
    }

    public function test_teller_return_float_rejects_when_balance_is_insufficient(): void
    {
        $branch = $this->makeBranch();
        $user = User::factory()->create();

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-'.uniqid(),
            'display_name' => 'Test Teller',
            'status' => 'OPEN',
            'active' => true,
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => 200,
            'available_balance' => 200,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient teller balance.');

        app(TellerTransactionService::class)->returnFloat($teller, 5000, $user->id);
    }

    public function test_vault_withdraw_succeeds_when_balance_is_sufficient(): void
    {
        $branch = $this->makeBranch();
        $user = User::factory()->create();

        $vault = Vault::create([
            'branch_id' => $branch->id,
            'gl_account_id' => $this->vaultCashGlAccountId(),
            'code' => 'VLT-'.uniqid(),
            'name' => 'Test Vault',
            'active' => true,
        ]);

        VaultBalance::create([
            'vault_id' => $vault->id,
            'currency' => 'NGN',
            'ledger_balance' => 10000,
            'available_balance' => 10000,
        ]);

        $transaction = app(VaultTransactionService::class)->withdraw($vault, 3000, $user->id);

        $this->assertEquals(3000, (float) $transaction->amount);

        $balance = VaultBalance::where('vault_id', $vault->id)->first();
        $this->assertEquals(7000, (float) $balance->available_balance);
    }
}
MBOS_EOF

cat > tests/Feature/WalletTierLimitTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Payments\WalletOnboardingService;
use App\Services\Payments\WalletService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the Tier 1 balance cap enforcement manually verified via
 * Tinker this session: a top-up that would push a wallet's balance past
 * its tier's cap must be rejected, not silently capped or allowed through.
 */
class WalletTierLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // topUp() posts to the GL (SUSPENSE_ASSET / WALLET_LIABILITY),
        // so real GL accounts must exist for it to resolve correctly.
        $this->seed(GlAccountSeeder::class);
    }

    public function test_topup_rejects_when_it_would_exceed_tier_1_balance_cap(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        $wallet = app(WalletOnboardingService::class)->onboard([
            'owner_name' => 'Test Wallet Holder',
            'phone' => '0801'.rand(1000000, 9999999),
            'bvn' => '12345678901',
        ], $user->id);

        $this->assertEquals(1, $wallet->kyc_tier);

        $tier1Cap = config('wallet.tiers.1.balance_cap');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("This would exceed the Tier 1 balance cap of {$tier1Cap}.");

        app(WalletService::class)->topUp($wallet, $tier1Cap + 1, $branch->id, $user->id);
    }

    public function test_topup_succeeds_when_within_tier_1_balance_cap(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-'.uniqid(),
            'office_id' => 1,
        ]);

        $wallet = app(WalletOnboardingService::class)->onboard([
            'owner_name' => 'Test Wallet Holder',
            'phone' => '0801'.rand(1000000, 9999999),
            'nin' => '10987654321',
        ], $user->id);

        $result = app(WalletService::class)->topUp($wallet, 20000, $branch->id, $user->id);

        $this->assertEquals(20000, (float) $result['balance']->available_balance);
    }

    public function test_onboarding_rejects_tier_1_wallet_with_no_bvn_or_nin(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tier 1 wallets require at least a BVN or NIN.');

        app(WalletOnboardingService::class)->onboard([
            'owner_name' => 'No Identity Wallet',
            'phone' => '0801'.rand(1000000, 9999999),
        ], $user->id);
    }

    public function test_onboarding_rejects_tier_2_wallet_missing_either_bvn_or_nin(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tier 2 and 3 wallets require both a BVN and a NIN.');

        app(WalletOnboardingService::class)->onboard([
            'owner_name' => 'Partial Identity Wallet',
            'phone' => '0801'.rand(1000000, 9999999),
            'bvn' => '12345678901',
            'kyc_tier' => 2,
        ], $user->id);
    }
}
MBOS_EOF

echo "Test suite added. Now run: mysql -u root -e \"CREATE DATABASE IF NOT EXISTS microbiz_os_testing;\" && php artisan test"