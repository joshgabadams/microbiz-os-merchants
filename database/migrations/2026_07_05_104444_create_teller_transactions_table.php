<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_transactions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('teller_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('transaction_no')->unique();

            $table->enum('transaction_type', [

                'FLOAT_RECEIVED',

                'FLOAT_RETURNED',

                'CUSTOMER_DEPOSIT',

                'CUSTOMER_WITHDRAWAL',

                'CASH_TRANSFER',

                'REVERSAL',

                'ADJUSTMENT'

            ]);

            $table->decimal('amount',24,2);

            $table->string('currency',3)
                ->default('NGN');

            $table->string('reference')
                ->nullable();

            $table->text('narration')
                ->nullable();

            $table->foreignId('performed_by')
                ->constrained('users');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('transaction_date');

            $table->boolean('posted')
                ->default(false);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_transactions');
    }
};