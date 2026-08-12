<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes a real gap ahead of production: Module 7 states explicitly
 * "An agent must not automatically receive all service permissions
 * merely because the agent has been activated." Every active agent
 * could do cash-in/cash-out/transfer unconditionally until now.
 *
 * Scoped to the fields Module 7 lists that are actually consumed today
 * (status, limit override, fee, authentication requirement, effective/
 * expiry dates) -- approval threshold and channel availability are not
 * yet used anywhere and can be added later without a breaking change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->string('status')->default('ENABLED');
            $table->decimal('limit_override', 24, 2)->nullable();
            $table->decimal('fee_amount', 24, 2)->nullable();
            $table->boolean('authentication_required')->default(true);
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_services');
    }
};
