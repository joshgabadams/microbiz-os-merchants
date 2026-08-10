<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_agreements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->string('agreement_number')->unique();
            $table->unsignedInteger('version');

            $table->string('status')->default('DRAFT');

            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('renewal_due_date')->nullable();

            $table->json('permitted_services')->nullable();
            $table->json('commercial_terms')->nullable();

            $table->string('document_path')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'version']);
            $table->index(['agent_id', 'status']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_agreements');
    }
};
