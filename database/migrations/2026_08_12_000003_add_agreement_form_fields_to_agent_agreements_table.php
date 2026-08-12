<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Schedule 7 (commercial/legal variables) fields and the template
 * reference to the existing agent_agreements table. permitted_services/
 * commercial_terms columns already exist (Schedule 2/3) -- validated at
 * the FormRequest layer, no schema change needed for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_agreements', function (Blueprint $table) {
            $table->foreignId('agreement_template_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('agent_agreement_templates')
                ->restrictOnDelete();

            $table->unsignedInteger('initial_term_months')->nullable()->after('renewal_due_date');
            $table->unsignedInteger('agent_termination_notice_days')->nullable()->after('initial_term_months');
            $table->unsignedInteger('microbiz_termination_notice_days')->nullable()->after('agent_termination_notice_days');
            $table->string('dispute_resolution_method')->nullable()->after('microbiz_termination_notice_days');
            $table->string('arbitration_seat')->nullable()->after('dispute_resolution_method');
            $table->string('governing_law')->nullable()->after('arbitration_seat');
            $table->foreignId('relationship_manager_id')
                ->nullable()
                ->after('governing_law')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('special_conditions')->nullable()->after('relationship_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agreement_template_id');
            $table->dropConstrainedForeignId('relationship_manager_id');
            $table->dropColumn([
                'initial_term_months',
                'agent_termination_notice_days',
                'microbiz_termination_notice_days',
                'dispute_resolution_method',
                'arbitration_seat',
                'governing_law',
                'special_conditions',
            ]);
        });
    }
};
