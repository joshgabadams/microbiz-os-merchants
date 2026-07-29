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
        Schema::connection($this->connection)->create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('idempotency_key');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('payment_transactions');
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('api_idempotency_keys');
    }
};
