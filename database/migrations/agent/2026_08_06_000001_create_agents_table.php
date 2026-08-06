<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the M-PAY Agency Banking Blueprint §8.1 schema exactly.
 * Sprint AG-01 scope: the agents table only -- locations, agreements,
 * operators, and terminals are separate later sprints (AG-03/AG-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_code')->unique();
            $table->string('agent_type');
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('registration_number')->nullable()->index();
            $table->string('tax_identification_number')->nullable();
            $table->string('phone')->index();
            $table->string('email')->nullable()->index();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('DRAFT')->index();
            $table->string('kyc_status')->default('PENDING');
            $table->string('risk_rating')->default('MEDIUM');
            $table->boolean('exclusive_relationship')->default(true);
            $table->string('principal_reference')->nullable();
            $table->decimal('daily_transaction_limit', 24, 2)->nullable();
            $table->decimal('daily_cash_out_limit', 24, 2)->nullable();
            $table->decimal('single_transaction_limit', 24, 2)->nullable();
            $table->date('next_review_date')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
