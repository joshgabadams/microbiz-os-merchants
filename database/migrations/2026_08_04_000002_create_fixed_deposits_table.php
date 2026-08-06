<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposits', function (Blueprint $table) {

            $table->id();

            $table->string('fd_no')->unique();

            $table->foreignId('customer_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->foreignId('settlement_account_id')
                ->constrained('customer_accounts')
                ->restrictOnDelete();

            $table->decimal('principal_amount', 24, 2);
            $table->string('currency', 3)->default('NGN');

            $table->decimal('interest_rate', 8, 4);

            $table->decimal('pre_liquidation_rate', 8, 4);

            $table->decimal('pre_liquidation_penalty_fee', 24, 2)->default(0);

            $table->unsignedInteger('tenor_days');
            $table->date('start_date');
            $table->date('maturity_date');

            $table->enum('status', [
                'ACTIVE',
                'LIQUIDATED_EARLY',
                'LIQUIDATED_AT_MATURITY',
            ])->default('ACTIVE');

            $table->foreignId('booked_by')->constrained('users');

            $table->foreignId('liquidated_by')->nullable()->constrained('users');
            $table->timestamp('liquidated_at')->nullable();
            $table->decimal('interest_paid', 24, 2)->nullable();

            $table->text('narration')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
