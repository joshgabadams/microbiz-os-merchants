<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_transaction_id')->nullable()->after('posted');
            $table->boolean('is_reversed')->default(false)->after('reversal_of_transaction_id');
            $table->timestamp('reversed_at')->nullable()->after('is_reversed');
            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');

            $table->foreign('reversal_of_transaction_id')
                ->references('id')
                ->on('vault_transactions');

            $table->foreign('reversed_by')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('vault_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_transaction_id']);
            $table->dropForeign(['reversed_by']);

            $table->dropColumn([
                'reversal_of_transaction_id',
                'is_reversed',
                'reversed_at',
                'reversed_by',
            ]);
        });
    }
};