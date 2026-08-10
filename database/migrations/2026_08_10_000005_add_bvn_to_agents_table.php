<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBN Agent Banking Guidelines (6 Oct 2025) require every agent's
 * terminal to be traceable back to the agent's BVN or TIN. TIN already
 * exists as tax_identification_number; BVN was missing entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('bvn', 11)->nullable()->after('tax_identification_number');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('bvn');
        });
    }
};
