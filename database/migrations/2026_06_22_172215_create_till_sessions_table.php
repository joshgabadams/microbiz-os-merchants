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
        Schema::create('till_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teller_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_float', 18, 2);
            $table->decimal('expected_cash', 18, 2)->default(0);
            $table->decimal('physical_cash', 18, 2)->nullable();
            $table->decimal('variance', 18, 2)->default(0);
            $table->enum('status', ['OPEN', 'CLOSED']);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('till_sessions');
    }
};
