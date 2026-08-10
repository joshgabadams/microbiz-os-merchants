<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teller_transactions', function (Blueprint $table) {
            $table->foreignId('customer_account_transaction_id')
                ->nullable()
                ->after('teller_id')
                ->constrained('customer_account_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teller_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_account_transaction_id');
        });
    }
};
