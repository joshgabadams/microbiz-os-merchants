<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_ledgers', function (Blueprint $table) {

            if (!Schema::hasColumn('cash_ledgers', 'entry_type')) {
                $table->enum('entry_type', [
                    'DEBIT',
                    'CREDIT'
                ])->after('source_id');
            }

            if (!Schema::hasColumn('cash_ledgers', 'account_type')) {
                $table->string('account_type')
                    ->after('entry_type');
            }

            if (!Schema::hasColumn('cash_ledgers', 'account_code')) {
                $table->string('account_code')
                    ->after('account_type');
            }

        });
    }

    public function down(): void
    {
        Schema::table('cash_ledgers', function (Blueprint $table) {

            if (Schema::hasColumn('cash_ledgers', 'entry_type')) {
                $table->dropColumn('entry_type');
            }

            if (Schema::hasColumn('cash_ledgers', 'account_type')) {
                $table->dropColumn('account_type');
            }

            if (Schema::hasColumn('cash_ledgers', 'account_code')) {
                $table->dropColumn('account_code');
            }

        });
    }
};