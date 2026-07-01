<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tellers', function (Blueprint $table) {

    $table->id();

    $table->foreignId('branch_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('user_id')
          ->nullable();

    $table->unsignedBigInteger('fineract_gl_id');

    $table->decimal('daily_limit',18,2)
          ->default(0);

    $table->boolean('active')
          ->default(true);

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tellers');
    }
};
