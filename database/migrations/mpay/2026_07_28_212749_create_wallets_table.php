<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mpay';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->foreignId('party_id')->constrained('payment_parties');
            $table->string('fincore_account_id')->nullable();
            $table->string('wallet_number')->unique();
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('available_balance_cache')->default(0);
            $table->unsignedBigInteger('daily_limit_minor')->nullable();
            $table->unsignedBigInteger('single_transaction_limit_minor')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('wallets');
    }
};
