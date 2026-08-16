<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes another real gap ahead of production: agent_transactions.
 * fee_amount and commission_amount have existed since AG-07 but were
 * never populated. This adds the configuration side -- a flat,
 * per-agent-per-service commission amount, mirroring the existing
 * fee_amount field exactly, rather than inventing a percentage/split
 * rule that isn't confirmed anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_services', function (Blueprint $table) {
            $table->decimal('commission_amount', 24, 2)->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('agent_services', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
        });
    }
};
