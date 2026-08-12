<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step record: downloaded_at set first, acknowledged_at set second
 * (the actual completion event -- a download alone never satisfies the
 * onboarding training gate). operator_id null = agent-level onboarding
 * training (gates AgentStatus TRAINING_PENDING -> TERMINAL_PENDING);
 * operator_id set = a specific operator's own training record, tracked
 * separately from the agent-level gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('agent_operators')->cascadeOnDelete();
            $table->foreignId('training_document_id')->constrained()->restrictOnDelete();
            $table->string('training_document_version');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('completion_method')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_training_records');
    }
};
