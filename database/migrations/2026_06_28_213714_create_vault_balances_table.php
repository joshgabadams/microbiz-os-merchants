<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_balances', function (Blueprint $table) {

            $table->id();

            $table->foreignId('vault_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('currency', 3)->default('NGN');

            $table->decimal('ledger_balance', 24, 2)->default(0);

            $table->decimal('available_balance', 24, 2)->default(0);

            $table->decimal('locked_balance', 24, 2)->default(0);

            $table->unsignedBigInteger('last_transaction_id')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'vault_id',
                'currency'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_balances');
    }
};
