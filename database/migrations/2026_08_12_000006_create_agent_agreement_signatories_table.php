<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records each party's signature -- MicroBiz and the Agent -- supporting
 * both scanned wet-signature upload and a future e-signature provider
 * integration under one shape. Not tied to the internal `users` table
 * for the AGENT side, since the Agent's signatory isn't a platform user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_agreement_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('party');
            $table->string('signatory_name');
            $table->string('signatory_title')->nullable();
            $table->string('signature_method');
            $table->string('signature_evidence_path')->nullable();
            $table->string('provider_reference_id')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['agent_agreement_id', 'party']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_agreement_signatories');
    }
};
