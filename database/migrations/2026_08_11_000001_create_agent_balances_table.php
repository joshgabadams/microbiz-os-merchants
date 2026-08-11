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
