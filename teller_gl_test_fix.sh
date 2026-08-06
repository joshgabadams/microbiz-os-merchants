#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > tests/Feature/TellerOpenCloseGlPostingTest.php << 'MBOS_EOF'
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use App\Services\Teller\TellerTransactionService;
use Database\Seeders\GlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerOpenCloseGlPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GlAccountSeeder::class);
    }

    protected function makeTellerWithBalance(float $balance): Teller
    {
        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB-'.uniqid(), 'office_id' => 1]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-'.uniqid(),
            'display_name' => 'Test Teller',
            'active' => true,
            'status' => 'CLOSED',
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
            'ledger_balance' => $balance,
            'available_balance' => $balance,
        ]);

        return $teller;
    }

    public function test_opening_teller_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(5000);

        $transaction = app(TellerTransactionService::class)->openTeller($teller, $user->id);

        $openingControlAccount = GlAccount::where('usage', 'OPENING_CASH_CONTROL')->firstOrFail();
        $tellerCashAccount = GlAccount::where('usage', 'TELLER_CASH')->firstOrFail();

        $entries = GlJournal::where('reference', $transaction->transaction_no)->get();

        $this->assertCount(2, $entries, 'Opening a teller should create exactly 2 balanced GlJournal rows.');

        $debit = $entries->firstWhere('entry_type', 'DEBIT');
        $credit = $entries->firstWhere('entry_type', 'CREDIT');

        $this->assertEquals($tellerCashAccount->id, $debit->gl_account_id);
        $this->assertEquals($openingControlAccount->id, $credit->gl_account_id);
        $this->assertEquals(5000, (float) $debit->amount);
        $this->assertEquals(5000, (float) $credit->amount);
    }

    public function test_closing_teller_creates_two_balanced_gl_journal_entries(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(5000);

        app(TellerTransactionService::class)->openTeller($teller, $user->id);
        $transaction = app(TellerTransactionService::class)->closeTeller($teller->fresh(), $user->id);

        $closingControlAccount = GlAccount::where('usage', 'CLOSING_CASH_CONTROL')->firstOrFail();
        $tellerCashAccount = GlAccount::where('usage', 'TELLER_CASH')->firstOrFail();

        $entries = GlJournal::where('reference', $transaction->transaction_no)->get();

        $this->assertCount(2, $entries, 'Closing a teller should create exactly 2 balanced GlJournal rows.');

        $debit = $entries->firstWhere('entry_type', 'DEBIT');
        $credit = $entries->firstWhere('entry_type', 'CREDIT');

        $this->assertEquals($closingControlAccount->id, $debit->gl_account_id);
        $this->assertEquals($tellerCashAccount->id, $credit->gl_account_id);
    }

    public function test_opening_teller_increases_total_gl_journal_count(): void
    {
        $user = User::factory()->create();
        $teller = $this->makeTellerWithBalance(3300);

        $before = GlJournal::count();

        app(TellerTransactionService::class)->openTeller($teller, $user->id);

        $after = GlJournal::count();

        $this->assertEquals($before + 2, $after);
    }
}
MBOS_EOF

echo "Test fixture fix applied. Next: php artisan test --filter=TellerOpenCloseGlPostingTest"