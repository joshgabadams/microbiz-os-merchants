<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBN's tiered-KYC/CDD framework requires BVN as a baseline identity
 * field; the merchants table had registration_number/TIN but no BVN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('bvn', 11)->nullable()->after('tax_identification_number');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('bvn');
        });
    }
};
