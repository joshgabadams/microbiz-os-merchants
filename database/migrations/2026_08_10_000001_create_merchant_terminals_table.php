<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant Management Services Blueprint 8.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('terminal_id')->unique();
            $table->string('serial_number')->unique();
            $table->string('terminal_type');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->default('PENDING_ACTIVATION');
            $table->string('application_version')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_terminals');
    }
};
