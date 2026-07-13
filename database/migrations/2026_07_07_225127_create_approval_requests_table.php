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
        Schema::create('approval_requests', function (Blueprint $table) {
    $table->id();

    $table->string('request_no')->unique();

    $table->string('request_type');
    $table->json('payload');

    $table->decimal('amount', 24, 2)->nullable();
    $table->string('currency', 3)->default('NGN');

    $table->string('status')->default('PENDING');
    // PENDING, APPROVED, REJECTED, CANCELLED

    $table->unsignedBigInteger('maker_id');
    $table->unsignedBigInteger('checker_id')->nullable();

    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();

    $table->text('maker_note')->nullable();
    $table->text('checker_note')->nullable();

    $table->string('executed_transaction_type')->nullable();
$table->unsignedBigInteger('executed_transaction_id')->nullable();

$table->index(
    ['executed_transaction_type', 'executed_transaction_id'],
    'approval_exec_txn_idx'
);

    $table->timestamps();

    $table->foreign('maker_id')->references('id')->on('users');
    $table->foreign('checker_id')->references('id')->on('users');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
