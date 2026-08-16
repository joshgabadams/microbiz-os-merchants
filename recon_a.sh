#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_13_000001_create_agent_reconciliations_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 13: Agent Reconciliation. Closes the "float mismatch"
 * exception category for physical cash specifically, mirroring
 * ReconciliationService's exact till-session pattern: compare a
 * recorded value (declared_physical_cash) against an authoritative
 * computed value, flag MATCHED/MISMATCHED, record the result.
 *
 * No session/period concept exists for agents yet (unlike tills), so
 * this is a point-in-time check against real transaction history
 * rather than bounded to a session -- reconcileAgent() can be called
 * repeatedly, each call reflecting the current state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('system_expected_physical_cash', 24, 2);
            $table->decimal('declared_physical_cash', 24, 2);
            $table->decimal('variance', 24, 2);

            $table->string('status')->default('PENDING'); // PENDING, MATCHED, MISMATCHED, APPROVED

            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_reconciliations');
    }
};
MBOS_EOF

cat > app/Models/AgentReconciliation.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentReconciliation extends Model
{
    protected $fillable = [
        'agent_id',
        'branch_id',

        'system_expected_physical_cash',
        'declared_physical_cash',
        'variance',

        'status', // PENDING, MATCHED, MISMATCHED, APPROVED

        'reconciled_by',
        'approved_by',

        'notes',
        'reconciled_at',
    ];

    protected $casts = [
        'system_expected_physical_cash' => 'decimal:2',
        'declared_physical_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reconciledBy()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentReconciliationService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentReconciliation;
use App\Models\AgentTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Module 13: Agent Reconciliation. Closes the "float mismatch"
 * exception category for physical cash specifically -- the most
 * concrete, directly buildable piece of Module 13's broader
 * reconciliation list, since it stays within a single real system
 * (agent_transactions/agent_balances) rather than guessing at
 * external FINCORE360/processor posting semantics.
 *
 * Mirrors ReconciliationService's exact till-session pattern: compare
 * a recorded value against an authoritative computed value, flag
 * MATCHED/MISMATCHED, record the result. No session/period concept
 * exists for agents yet, so this is a point-in-time check rather than
 * bounded to a session -- reconcileAgent() can be called repeatedly.
 *
 * Reversed transactions are correctly excluded automatically: the
 * real AgentTransactionReversalService flips a reversed transaction's
 * status to REVERSED (never creates a new AgentTransaction row), so
 * filtering by status = COMPLETED already accounts for reversals
 * without any extra logic here.
 */
class AgentReconciliationService
{
    /**
     * @throws Exception
     */
    public function reconcileAgent(
        Agent $agent,
        ?int $reconciledBy = null,
        ?string $notes = null
    ): AgentReconciliation {
        return DB::transaction(function () use ($agent, $reconciledBy, $notes) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new Exception(
                    "Agent {$agent->agent_code} has no float balance to reconcile."
                );
            }

            /*
             * System-expected physical cash is derived from real
             * transaction history rather than aggregating anything
             * external -- CASH_IN increases physical cash (agent
             * receives it from the customer), CASH_OUT decreases it
             * (agent pays it out).
             */
            $totalCashIn = (float) AgentTransaction::where('agent_id', $agent->id)
                ->where('transaction_type', 'CASH_IN')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            $totalCashOut = (float) AgentTransaction::where('agent_id', $agent->id)
                ->where('transaction_type', 'CASH_OUT')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            $systemExpectedPhysicalCash = round($totalCashIn - $totalCashOut, 2);
            $declaredPhysicalCash = round((float) $balance->declared_physical_cash, 2);
            $variance = round($declaredPhysicalCash - $systemExpectedPhysicalCash, 2);

            $status = abs($variance) < 0.005 ? 'MATCHED' : 'MISMATCHED';

            return AgentReconciliation::create([
                'agent_id' => $agent->id,
                'branch_id' => $agent->branch_id,

                'system_expected_physical_cash' => $systemExpectedPhysicalCash,
                'declared_physical_cash' => $declaredPhysicalCash,
                'variance' => $variance,

                'status' => $status,

                'reconciled_by' => $reconciledBy,
                'approved_by' => null,

                'notes' => $notes,
                'reconciled_at' => now(),
            ]);
        });
    }
}
MBOS_EOF

echo "Agent reconciliation Part A applied (migration, model, service)."
