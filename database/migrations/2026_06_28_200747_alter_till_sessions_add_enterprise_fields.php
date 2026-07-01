<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('till_sessions', function (Blueprint $table) {

            $table->foreignId('branch_id')
                  ->nullable()
                  ->after('teller_id');

            $table->foreignId('vault_id')
                  ->nullable()
                  ->after('branch_id');

            $table->foreignId('opened_by')
                  ->nullable()
                  ->after('vault_id');

            $table->foreignId('closed_by')
                  ->nullable()
                  ->after('opened_by');

            $table->string('session_reference')
                  ->nullable()
                  ->unique()
                  ->after('id');

            $table->decimal('current_balance',18,2)
                  ->default(0)
                  ->after('opening_float');

        });
    }

    public function down(): void
    {
        Schema::table('till_sessions', function (Blueprint $table) {

            $table->dropColumn([
                'branch_id',
                'vault_id',
                'opened_by',
                'closed_by',
                'session_reference',
                'current_balance'
            ]);

        });
    }
};