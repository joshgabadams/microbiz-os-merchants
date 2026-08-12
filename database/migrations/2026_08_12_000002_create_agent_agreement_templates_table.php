<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable legal document a per-agent agreement is drafted from --
 * standard clauses, default Schedule 4 (operator/training obligations)
 * and Schedule 6 (escalation contacts) content that's the same across
 * agents, so it's captured once here and copied in at drafting time
 * rather than re-entered per agreement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_agreement_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version');
            $table->string('status')->default('DRAFT');
            $table->longText('legal_clauses')->nullable();
            $table->text('default_operator_training_obligations')->nullable();
            $table->json('default_escalation_contacts')->nullable();
            $table->string('governing_law')->default('Federal Republic of Nigeria');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_agreement_templates');
    }
};
