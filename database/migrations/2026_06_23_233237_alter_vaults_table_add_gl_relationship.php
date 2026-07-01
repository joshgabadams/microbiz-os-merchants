<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaults', function (Blueprint $table) {

            $table->foreignId('gl_account_id')
                  ->nullable()
                  ->after('branch_id');

        });
    }

    public function down(): void
    {
        Schema::table('vaults', function (Blueprint $table) {

            $table->dropColumn('gl_account_id');

        });
    }
};
