<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per MERCHANT_API_CONTRACT.md: verify operates on the specific challenge
 * a send() call returned, not "whatever the latest OTP row for this
 * client is" -- removes any ambiguity about which challenge is active if
 * more than one send happens close together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_otps', function (Blueprint $table) {
            $table->string('challenge_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_otps', function (Blueprint $table) {
            $table->dropColumn('challenge_id');
        });
    }
};
