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
        Schema::create('gl_accounts', function (Blueprint $table) {

    $table->id();

    $table->bigInteger('fineract_gl_id')->unique();

    $table->string('name');

    $table->string('gl_code');

    $table->string('type');

    $table->string('usage')->nullable();

    $table->boolean('manual_entries_allowed')
          ->default(false);

    $table->boolean('disabled')
          ->default(false);

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
