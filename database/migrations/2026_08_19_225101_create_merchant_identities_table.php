<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The merchant's own login identity for the self-service portal --
 * deliberately NOT the staff `users` table (self-registering applicants
 * shouldn't share a table with staff/RBAC) and NOT the business `merchants`
 * table (that's the KYC/business record, created by staff via /reg/merchant;
 * this is "who can log in", created by the applicant themselves).
 *
 * Always tied to a real Fincore360 client -- either found via account
 * number lookup, or created fresh here for applicants with no existing
 * account (see MerchantIdentityController). password/otp/session fields
 * are added in a later migration once that phase is built -- this one is
 * scoped to identity + the Fincore360 link only, per explicit sequencing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_identities', function (Blueprint $table) {
            $table->id();

            $table->string('fincore_client_id')->unique();
            $table->string('fincore_account_no')->nullable()->index();
            $table->string('legal_form')->nullable();
            $table->string('display_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('status')->default('PENDING');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_identities');
    }
};
