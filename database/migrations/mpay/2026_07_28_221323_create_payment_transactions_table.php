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
        Schema::connection($this->connection)->create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->uuid('transaction_uuid')->unique();
            $table->string('idempotency_key');
            $table->string('external_reference')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('transaction_type');
            $table->string('channel');
            $table->foreignId('initiator_party_id')->constrained('payment_parties');
            $table->foreignId('source_wallet_id')->nullable()->constrained('wallets');
            $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status');
            $table->string('fincore_posting_status')->nullable();
            $table->string('fincore_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('payment_transactions');
    }
};
