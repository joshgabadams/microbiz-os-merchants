<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {

            $table->id();

            $table->string('wallet_no')->unique();

            $table->string('owner_name');

            $table->string('phone')->unique();

            // At least one of bvn/nin required at Tier 1, both required at
            // Tier 2/3 -- enforced in WalletOnboardingService, not at the DB
            // level, consistent with how business rules are enforced
            // elsewhere in this codebase.
            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();

            $table->unsignedTinyInteger('kyc_tier')->default(1);

            $table->enum('status', [
                'ACTIVE',
                'SUSPENDED',
                'DEACTIVATED',
            ])->default('ACTIVE');

            $table->foreignId('onboarded_by')->nullable()->constrained('users');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
