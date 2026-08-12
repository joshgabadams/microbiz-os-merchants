<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_operator_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type')->index();
            $table->string('status')->default('INITIATED')->index();
            $table->decimal('amount', 24, 2);
            $table->decimal('fee_amount', 24, 2)->default(0);
            $table->decimal('commission_amount', 24, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('customer_account_id')
                ->nullable()
                ->constrained('customer_accounts');
            $table->string('customer_reference')->nullable();
            $table->string('processor_reference')->nullable()->index();
            $table->string('channel_reference')->nullable()->index();
            $table->string('rrn')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('geo_fence_passed');
            $table->timestamp('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->json('risk_metadata')->nullable();
            $table->json('channel_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_transactions');
    }
};
