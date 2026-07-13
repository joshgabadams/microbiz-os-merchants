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
        Schema::create('teller_cash_balances', function (Blueprint $table) {
    $table->id();

    $table->foreignId('teller_id')->constrained()->cascadeOnDelete();
    $table->date('business_date');

    $table->decimal('opening_cash', 24, 2)->default(0);
    $table->decimal('float_received', 24, 2)->default(0);
    $table->decimal('customer_deposits', 24, 2)->default(0);
    $table->decimal('customer_withdrawals', 24, 2)->default(0);
    $table->decimal('float_returned', 24, 2)->default(0);

    $table->decimal('expected_cash', 24, 2)->default(0);
    $table->decimal('physical_cash', 24, 2)->default(0);
    $table->decimal('variance', 24, 2)->default(0);

    $table->string('status')->default('PENDING');
    // PENDING, BALANCED, OVER, SHORT

    $table->unsignedBigInteger('balanced_by')->nullable();
    $table->timestamp('balanced_at')->nullable();

    $table->text('note')->nullable();

    $table->timestamps();

    $table->unique(['teller_id', 'business_date']);

    $table->foreign('balanced_by')->references('id')->on('users');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teller_cash_balances');
    }
};
