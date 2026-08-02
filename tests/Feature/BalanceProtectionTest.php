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
