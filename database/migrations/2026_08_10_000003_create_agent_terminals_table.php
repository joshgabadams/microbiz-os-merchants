<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency Banking Blueprint 8.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_location_id')->constrained()->restrictOnDelete();
            $table->string('terminal_id')->unique();
            $table->string('serial_number')->unique();
            $table->string('device_model')->nullable();
            $table->string('provider')->nullable();
            $table->string('application_version')->nullable();
            $table->string('status')->default('PENDING_ACTIVATION');
            $table->decimal('registered_latitude', 10, 7);
            $table->decimal('registered_longitude', 10, 7);
            $table->unsignedInteger('geo_fence_radius_metres');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->boolean('geo_fence_compliant')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_terminals');
    }
};
