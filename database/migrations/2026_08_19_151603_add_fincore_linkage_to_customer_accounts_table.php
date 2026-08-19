<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which Fineract client/account a local customer_accounts row
 * mirrors, so a merchant registered via account-number lookup (see
 * MerchantRegistrationController) can be traced back to its Fineract
 * source of truth. Nullable: existing/local-only customer accounts have
 * no Fineract counterpart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->string('fincore_client_id')->nullable()->after('customer_id')->index();
            $table->string('fincore_account_no')->nullable()->after('fincore_client_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->dropColumn(['fincore_client_id', 'fincore_account_no']);
        });
    }
};
