<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same pattern already used by merchant_transactions/MerchantPaymentService:
 * a retry with the same key must return the original result, not create a
 * second Fincore360 client. Without this, a timed-out request retried by
 * the frontend (or a double-submit) would create two real Fineract clients
 * for the same applicant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_identities', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('fincore_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_identities', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
