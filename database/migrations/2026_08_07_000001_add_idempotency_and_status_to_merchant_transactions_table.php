<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant Management Services Blueprint 5.3/5.6: idempotency is mandatory
 * for payment processing, and business-lifecycle status must be tracked
 * separately from `posted` (which only ever describes GL posting, not the
 * transaction's own lifecycle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_transactions', function (Blueprint $table) {

            if (! Schema::hasColumn('merchant_transactions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('transaction_no');
            }

            if (! Schema::hasColumn('merchant_transactions', 'status')) {
                $table->string('status')->default('INITIATED')->after('transaction_type');
            }

        });
    }

    public function down(): void
    {
        Schema::table('merchant_transactions', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'status']);
        });
    }
};
