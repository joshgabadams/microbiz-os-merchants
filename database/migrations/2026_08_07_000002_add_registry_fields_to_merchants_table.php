<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant Management Services Blueprint 8.1, plus the risk-assessment
 * fields the actual onboarding wizard collects (18.3 step 6) that aren't
 * in the literal SQL block there.
 *
 * Deliberately NOT duplicated: customer_account_id already serves as
 * 8.1's settlement_account_id; approved_by/approved_at/rejection_reason/
 * submitted_at/activated_at already exist from the earlier lifecycle
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {

            $table->string('legal_name')->nullable();
            $table->string('trading_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('registration_number')->nullable()->index();
            $table->string('tax_identification_number')->nullable();
            $table->string('merchant_category_code', 4)->nullable();
            $table->string('industry')->nullable();
            $table->string('website')->nullable();

            $table->string('state')->nullable();
            $table->string('local_government')->nullable();
            $table->text('registered_address')->nullable();
            $table->text('operating_address')->nullable();

            $table->string('risk_rating')->default('MEDIUM');
            $table->string('customer_type')->nullable();
            $table->string('geographic_reach')->nullable();
            $table->string('business_model')->nullable();
            $table->decimal('expected_monthly_volume', 24, 2)->nullable();
            $table->decimal('expected_monthly_value', 24, 2)->nullable();

            $table->string('settlement_frequency')->default('T_PLUS_1');
            $table->unsignedInteger('settlement_delay_days')->default(1);
            $table->decimal('daily_limit', 24, 2)->nullable();
            $table->decimal('monthly_limit', 24, 2)->nullable();

            $table->string('kyc_status')->default('PENDING');
            $table->timestamp('kyc_completed_at')->nullable();
            $table->timestamp('next_review_at')->nullable();

            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('source_channel')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'trading_name', 'business_type', 'registration_number',
                'tax_identification_number', 'merchant_category_code', 'industry', 'website',
                'state', 'local_government', 'registered_address', 'operating_address',
                'risk_rating', 'customer_type', 'geographic_reach', 'business_model',
                'expected_monthly_volume', 'expected_monthly_value',
                'settlement_frequency', 'settlement_delay_days', 'daily_limit', 'monthly_limit',
                'kyc_status', 'kyc_completed_at', 'next_review_at',
                'suspension_reason', 'suspended_at', 'source_channel',
            ]);
        });
    }
};
