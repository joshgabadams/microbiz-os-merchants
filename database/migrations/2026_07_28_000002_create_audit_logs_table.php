<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor: who did it (a human user or "system" for unattended jobs)
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable(); // denormalized, e.g. "jane@microbiz.africa" or "system"

            // Subject: what was acted upon (polymorphic \u2014 TellerTransaction, VaultTransaction,
            // CustomerAccountTransaction, ApprovalRequest, etc.)
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('action');             // e.g. "teller_transaction.created", "approval.approved"
            $table->string('module')->nullable();  // e.g. "teller", "vault", "customer-cash", "approval"

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('actor_id');
            $table->index(['module', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
