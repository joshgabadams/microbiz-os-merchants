<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable, versioned training guide -- the same document applies
 * across agents (and, later, individual operators), so it's captured
 * once here rather than re-entered per agent. Versioning matters
 * because Schedule 4 anticipates refresher training when the guide
 * changes; a historical acknowledgement must record which version was
 * actually acknowledged, not just "the current one."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version');
            $table->string('status')->default('DRAFT');
            $table->string('file_path');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_documents');
    }
};
