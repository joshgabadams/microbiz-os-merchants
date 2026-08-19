<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTP challenges for the merchant self-service login/registration flow.
 * code_hash uses Hash::make() (bcrypt, same as passwords) -- brute force
 * is mitigated by `attempts` + short expiry, not hash speed. Once verified,
 * verification_token_hash holds a SHA-256 hash of a random opaque token
 * (fast-lookup hash, same pattern Sanctum uses for its own tokens) that
 * the session-exchange endpoint (built later) will look up by exact match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_otps', function (Blueprint $table) {
            $table->id();

            $table->string('fincore_client_id')->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('verification_token_hash')->nullable()->index();
            $table->timestamp('verification_token_expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_otps');
    }
};
