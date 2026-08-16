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
