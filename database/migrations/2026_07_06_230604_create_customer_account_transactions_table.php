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
        Schema::create('customer_account_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
    $table->string('transaction_no')->unique();
    $table->enum('transaction_type', [
        'CASH_DEPOSIT',
        'CASH_WITHDRAWAL',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'REVERSAL',
        'ADJUSTMENT',
    ]);
    $table->decimal('amount', 24, 2);
    $table->string('currency', 3)->default('NGN');
    $table->string('reference')->nullable();
    $table->text('narration')->nullable();
    $table->unsignedBigInteger('performed_by');
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamp('transaction_date');
    $table->boolean('posted')->default(false);
    $table->timestamps();

    $table->foreign('performed_by')->references('id')->on('users');
    $table->foreign('approved_by')->references('id')->on('users');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_account_transactions');
    }
};
