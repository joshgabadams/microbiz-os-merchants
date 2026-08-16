<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal Risk/Compliance/Legal/Business Owner sign-off, matching the
 * document's own "INTERNAL APPROVAL (BEFORE EXECUTION)" block. All four
 * must be APPROVED before execution can proceed -- a real, enforced
 * gate, not just a recorded convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_agreement_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_agreement_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('approval_type');

            $table->string('status')
                ->default('PENDING');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            // Explicit short index name avoids MySQL's 64-character
            // identifier limit for automatically generated index names.
            $table->unique(
                ['agent_agreement_id', 'approval_type'],
                'agent_agreement_approval_type_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_agreement_approvals');
    }
};
