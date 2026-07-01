<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_ledgers', function (Blueprint $table) {

            $table->id();

            $table->string('reference_no')->unique();

            $table->foreignId('branch_id')->constrained();

            $table->foreignId('vault_id')->nullable()->constrained();

            $table->foreignId('teller_id')->nullable()->constrained();

            $table->foreignId('user_id')->nullable()->constrained();

            $table->string('transaction_type');

            $table->string('source_type');

            $table->unsignedBigInteger('source_id')->nullable();

            $table->decimal('debit',18,2)->default(0);

            $table->decimal('credit',18,2)->default(0);

            $table->decimal('running_balance',18,2)->default(0);

            $table->string('currency')->default('NGN');

            $table->text('narration')->nullable();

            $table->enum('status',[
                'PENDING',
                'APPROVED',
                'REVERSED'
            ])->default('PENDING');

            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamp('transaction_date');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_ledgers');
    }
};
