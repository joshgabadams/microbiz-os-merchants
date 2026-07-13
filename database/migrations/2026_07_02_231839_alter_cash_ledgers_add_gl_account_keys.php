<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_ledgers', function (Blueprint $table) {

            $table->string('debit_account_key')
                ->after('account_code');

            $table->string('credit_account_key')
                ->after('debit_account_key');

        });
    }

    public function down(): void
    {
        Schema::table('cash_ledgers', function (Blueprint $table) {

            $table->dropColumn([
                'debit_account_key',
                'credit_account_key',
            ]);

        });
    }
};
