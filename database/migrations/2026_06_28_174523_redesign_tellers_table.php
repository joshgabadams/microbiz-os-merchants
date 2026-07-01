<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tellers', function (Blueprint $table) {

            // Remove old column
            $table->dropColumn('fineract_gl_id');

            // Relationships
            $table->foreignId('vault_id')
                  ->nullable()
                  ->after('branch_id');

            $table->foreignId('gl_account_id')
                  ->nullable()
                  ->after('vault_id');

            // Business identifiers
            $table->string('teller_code')
                  ->unique()
                  ->after('user_id');

            $table->string('staff_code')
                  ->nullable();

            $table->string('display_name');

            // Cash controls
            $table->decimal('opening_cash_limit',18,2)
                  ->default(0);

            $table->decimal('minimum_cash',18,2)
                  ->default(0);

            $table->decimal('maximum_cash',18,2)
                  ->default(0);

            // Operational state
            $table->enum('status',[
                'OPEN',
                'CLOSED',
                'SUSPENDED'
            ])->default('CLOSED');

        });
    }

    public function down(): void
    {
        //
    }
};
