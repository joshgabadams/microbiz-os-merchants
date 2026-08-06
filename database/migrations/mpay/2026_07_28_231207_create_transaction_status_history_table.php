<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mpay';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('transaction_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('payment_transactions');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason_code')->nullable();
            $table->string('changed_by_type')->default('SYSTEM');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('transaction_status_history');
    }
};
