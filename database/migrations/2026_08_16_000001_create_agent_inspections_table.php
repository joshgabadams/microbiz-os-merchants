<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG-12: Agent supervision / inspections -- the other half of the
 * supervision area alongside agent_complaints. A durable site-visit
 * record: who inspected which agent/location, what was found, the
 * compliance grading, any corrective action required, and whether
 * that corrective action has actually been followed up on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_inspections', function (Blueprint $table) {
            $table->id();

            $table->string('inspection_no')->unique();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->cascadeOnDelete();

            $table->foreignId('agent_location_id')
                ->nullable()
                ->constrained('agent_locations')
                ->nullOnDelete();

            $table->foreignId('inspector_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('inspection_type');
            // ROUTINE, COMPLIANCE, FOLLOW_UP, INCIDENT_TRIGGERED,
            // PRE_ACTIVATION

            $table->date('inspection_date');

            $table->string('status')->default('SCHEDULED');
            // SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED

            $table->text('findings')->nullable();

            $table->string('compliance_outcome')->nullable();
            // COMPLIANT, MINOR_NON_COMPLIANCE, MAJOR_NON_COMPLIANCE,
            // CRITICAL_NON_COMPLIANCE

            $table->text('corrective_action')->nullable();
            $table->date('corrective_action_deadline')->nullable();

            $table->string('follow_up_status')->default('NOT_REQUIRED');
            // NOT_REQUIRED, PENDING, IN_PROGRESS, COMPLETED
            $table->text('follow_up_notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['status', 'inspection_date']);
            $table->index(['follow_up_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_inspections');
    }
};
