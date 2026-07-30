<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_transactions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('merchant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('transaction_no')->unique();

            $table->enum('transaction_type', [

                'QR_COLLECTION',

                'POS_COLLECTION',

                'REVERSAL',

                'ADJUSTMENT',

            ]);

            $table->decimal('amount', 24, 2);

            $table->string('currency', 3)
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

            $table->foreignId('reversal_of_transaction_id')
                ->nullable();

            $table->boolean('is_reversed')
                ->default(false);

            $table->timestamp('reversed_at')
                ->nullable();

            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_transactions');
    }
};
