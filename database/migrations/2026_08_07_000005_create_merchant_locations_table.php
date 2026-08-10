<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant Management Services Blueprint 8.4 (schema) + Module 3's
 * described fields (trading_name, contact_person, operating_hours,
 * risk_classification aren't in the literal 8.4 SQL block, but are
 * explicitly named in Module 3's field list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location_code')->unique();
            $table->string('name');
            $table->string('trading_name')->nullable();
            $table->text('address');
            $table->string('state')->nullable();
            $table->string('local_government')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('operating_hours')->nullable();
            $table->string('risk_classification')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_locations');
    }
};
