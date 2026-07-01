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
        Schema::create('vault_ledgers', function (Blueprint $table) {

    $table->id();

    $table->foreignId('vault_id')->constrained();

    $table->foreignId('vault_transaction_id')
          ->constrained();

    $table->string('transaction_no');

    $table->dateTime('transaction_date');
$table->enum('entry_type', [
    'DEBIT',
    'CREDIT'
]);

    $table->decimal('amount',18,2);

    $table->decimal('balance_before',18,2);

    $table->decimal('balance_after',18,2);

    $table->string('currency',3);

    $table->string('reference')->nullable();

    $table->text('narration')->nullable();

    $table->foreignId('created_by');

    $table->timestamps();

    $table->index('vault_id');
    $table->index('transaction_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_ledgers');
    }
};
