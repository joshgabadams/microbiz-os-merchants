<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_balances', function (Blueprint $table) {

            $table->id();

            $table->foreignId('teller_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('currency', 3)
                ->default('NGN');

            $table->decimal('ledger_balance', 24, 2)
                ->default(0);

            $table->decimal('available_balance', 24, 2)
                ->default(0);

            $table->decimal('locked_balance', 24, 2)
                ->default(0);

            $table->foreignId('last_transaction_id')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'teller_id',
                'currency'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_balances');
    }
};