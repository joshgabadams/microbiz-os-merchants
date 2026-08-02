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
