<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('customer_account_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('customer_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_account_id');
        });
    }
};
