<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG-12: Agent supervision / complaints.
 *
 * Provides a durable complaint case record with tracking, ownership,
 * SLA timing, resolution and escalation fields suitable for agency
 * banking operations and regulatory complaint reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_complaints', function (Blueprint $table) {
            $table->id();

            $table->string('complaint_no')->unique();

            $table->foreignId('agent_id')
                ->nullable()
                ->constrained('agents')
                ->nullOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('agent_location_id')
                ->nullable()
                ->constrained('agent_locations')
                ->nullOnDelete();

            $table->foreignId('agent_transaction_id')
                ->nullable()
                ->constrained('agent_transactions')
                ->nullOnDelete();

            $table->string('complainant_name');
            $table->string('complainant_phone')->nullable();
            $table->string('complainant_email')->nullable();

            $table->string('channel')->default('BRANCH');
            // BRANCH, PHONE, EMAIL, WEB, AGENT, OTHER

            $table->string('category');
            // CASH_IN, CASH_OUT, TRANSFER, FEES, AGENT_CONDUCT,
            // SERVICE_FAILURE, FRAUD_SUSPECTED, OTHER

            $table->string('subject');
            $table->text('description');

            $table->decimal('disputed_amount', 24, 2)->nullable();

            $table->string('priority')->default('NORMAL');
            // LOW, NORMAL, HIGH, CRITICAL

            $table->string('status')->default('OPEN');
            // OPEN, ACKNOWLEDGED, IN_PROGRESS, RESOLVED, CLOSED, ESCALATED

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->unsignedInteger('escalation_level')->default(0);
            $table->text('escalation_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['agent_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_complaints');
    }
};
