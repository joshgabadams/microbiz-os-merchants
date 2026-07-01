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
        Schema::create('vaults', function (Blueprint $table) {

    $table->id();

    $table->foreignId('branch_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->string('name');

    $table->unsignedBigInteger('fineract_gl_id');

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
        Schema::dropIfExists('vaults');
    }
};
