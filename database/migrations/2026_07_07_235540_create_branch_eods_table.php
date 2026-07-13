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
        Schema::create('branch_eods', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('branch_id');
    $table->date('business_date');

    $table->string('status')->default('PENDING');
    // PENDING, CLOSED, FAILED

    $table->unsignedInteger('total_tellers')->default(0);
    $table->unsignedInteger('closed_tellers')->default(0);
    $table->unsignedInteger('balanced_tellers')->default(0);

    $table->boolean('vault_balanced')->default(false);
    $table->boolean('has_pending_approvals')->default(false);
    $table->boolean('has_unposted_transactions')->default(false);

    $table->unsignedBigInteger('closed_by')->nullable();
    $table->timestamp('closed_at')->nullable();

    $table->text('note')->nullable();
    $table->timestamps();

    $table->unique(['branch_id', 'business_date']);

    $table->foreign('closed_by')->references('id')->on('users');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_eods');
    }
};
