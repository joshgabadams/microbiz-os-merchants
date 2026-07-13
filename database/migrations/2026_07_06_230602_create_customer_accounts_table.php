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
      Schema::create('customer_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->string('account_no')->unique();
    $table->string('account_type')->default('SAVINGS');
    $table->string('currency', 3)->default('NGN');
    $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts');
    $table->string('status')->default('ACTIVE');
    $table->timestamps();
}); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_accounts');
    }
};
