<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {

            $table->id();

            $table->string('merchant_code')->unique();

            $table->string('business_name');

            $table->string('contact_name')->nullable();

            $table->string('phone')->nullable();

            $table->string('email')->nullable();

            $table->foreignId('branch_id')->nullable()->constrained();

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
        Schema::dropIfExists('merchants');
    }
};
