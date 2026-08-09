<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable();
            $table->decimal('ownership_percentage', 5, 2)->default(0);
            $table->boolean('is_director')->default(false);
            $table->boolean('is_pep')->default(false);
            $table->boolean('sanctions_match')->default(false);
            $table->string('screening_status')->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_beneficial_owners');
    }
};
