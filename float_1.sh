#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_11_000001_create_agent_balances_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG-06: Float. Exact schema per M-PAY Agency Banking Blueprint §8.6 --
 * intentionally not adapted to match VaultBalance/TellerBalance's
 * ledger_balance/available_balance naming, since the Blueprint's own
 * terminology (ledger_float/available_float) is the source of truth
 * here, and the field-name mismatch is easy to keep straight given
 * how few places touch this table directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->unique()->constrained()->restrictOnDelete();
            $table->string('currency', 3)->default('NGN');
            $table->decimal('ledger_float', 24, 2)->default(0);
            $table->decimal('available_float', 24, 2)->default(0);
            $table->decimal('locked_float', 24, 2)->default(0);
            $table->decimal('declared_physical_cash', 24, 2)->default(0);
            $table->decimal('commission_balance', 24, 2)->default(0);
            $table->decimal('pending_commission', 24, 2)->default(0);
            $table->unsignedBigInteger('last_transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_balances');
    }
};
MBOS_EOF

cat > app/Models/AgentBalance.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBalance extends Model
{
    protected $fillable = [
        'agent_id',
        'currency',
        'ledger_float',
        'available_float',
        'locked_float',
        'declared_physical_cash',
        'commission_balance',
        'pending_commission',
        'last_transaction_id',
    ];

    protected $casts = [
        'ledger_float' => 'decimal:2',
        'available_float' => 'decimal:2',
        'locked_float' => 'decimal:2',
        'declared_physical_cash' => 'decimal:2',
        'commission_balance' => 'decimal:2',
        'pending_commission' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
MBOS_EOF

cat > app/Services/CashManagement/AgentFloatService.php << 'MBOS_EOF'
<?php

namespace App\Services\CashManagement;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\CashLedger;
use App\Models\Vault;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Payments\AgentOperationGuard;
use App\Services\Vault\VaultTransactionService;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentFloatService
{
    public function __construct(
        protected VaultTransactionService $vaultTransactionService,
        protected AgentOperationGuard $guard,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService
    ) {
    }

    /**
     * @throws Exception
     */
    public function allocateFloat(
        Vault $vault,
        Agent $agent,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        $this->guard->guardFloatOperation($agent, $amount);

        return DB::transaction(function () use (
            $vault,
            $agent,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            $vaultTransaction = $this->vaultTransactionService->withdraw(
                $vault,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Vault to Agent Float'
            );

            $this->ensureBalanceExists($agent);

            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            $balance->ledger_float += $amount;
            $balance->available_float += $amount;
            $balance->save();

            $transactionDate = now();

            $cashLedger = CashLedger::create([
                'reference_no' => $this->transactionNumberService->generate('AGF'),
                'branch_id' => $agent->branch_id,
                'vault_id' => $vault->id,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_FLOAT_RECEIPT',
                'source_type' => Agent::class,
                'source_id' => $agent->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'AGENCY_FLOAT',
                'credit_account_key' => 'VAULT_CASH',
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Vault to Agent Float',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            return [
                'vault_transaction' => $vaultTransaction,
                'agent_balance' => $balance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
            ];
        });
    }

    /**
     * @throws Exception
     */
    public function returnFloat(
        Vault $vault,
        Agent $agent,
        float $amount,
        int $performedBy,
        ?string $reference = null,
        ?string $narration = null
    ): array {
        if ($amount <= 0) {
            throw new Exception('Float amount must be greater than zero.');
        }

        $this->guard->guardFloatOperation($agent, $amount);

        return DB::transaction(function () use (
            $vault,
            $agent,
            $amount,
            $performedBy,
            $reference,
            $narration
        ) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception('Agent has no float balance to return.');
            }

            if ($balance->available_float < $amount) {
                throw new Exception('Insufficient agent float balance.');
            }

            $balance->ledger_float -= $amount;
            $balance->available_float -= $amount;
            $balance->save();

            $transactionDate = now();

            $cashLedger = CashLedger::create([
                'reference_no' => $this->transactionNumberService->generate('AGF'),
                'branch_id' => $agent->branch_id,
                'vault_id' => $vault->id,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_FLOAT_RETURN',
                'source_type' => Agent::class,
                'source_id' => $agent->id,
                'entry_type' => 'CREDIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'VAULT_CASH',
                'credit_account_key' => 'AGENCY_FLOAT',
                'debit' => 0,
                'credit' => $amount,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Agent return float to vault',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            $vaultTransaction = $this->vaultTransactionService->deposit(
                $vault,
                $amount,
                $performedBy,
                $reference,
                $narration ?? 'Agent return float to vault'
            );

            return [
                'agent_balance' => $balance->fresh(),
                'cash_ledger' => $cashLedger->fresh(),
                'vault_transaction' => $vaultTransaction,
            ];
        });
    }

    protected function ensureBalanceExists(Agent $agent): void
    {
        AgentBalance::firstOrCreate(
            ['agent_id' => $agent->id],
            ['currency' => 'NGN']
        );
    }
}
MBOS_EOF

echo "AG-06 Part 1 of 2 applied (migration, model, service)."