<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency Banking Blueprint 8.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->string('status')->default('PENDING');
            $table->timestamp('training_completed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['agent_id', 'agent_location_id', 'user_id'],
                'agent_operator_unique_assignment'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_operators');
    }
};
