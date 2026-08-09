<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tessa_alerts', function (Blueprint $table) {

            $table->id();

            $table->string('alert_type');
            // TELLER_VARIANCE, HIGH_REVERSAL, UNUSUAL_APPROVAL (first pass);
            // VAULT_LIQUIDITY, EXCESSIVE_FLOAT, CASH_FORECAST,
            // MERCHANT_TRANSACTION reserved for later phases.

            $table->string('severity');
            // LOW, MEDIUM, HIGH

            // Polymorphic subject: the teller, the approval request, etc.
            // that this alert is actually about.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('title');

            // Per TESSA's own design principle: "must provide reasons for
            // every alert" -- this is the human-readable explanation, not
            // just a severity flag.
            $table->text('description');

            // The specific numbers behind the alert (variance amount,
            // reversal count, approval latency in seconds, thresholds
            // used) -- kept structured for the eventual dashboard, not
            // just embedded in the description text.
            $table->json('metadata')->nullable();

            $table->string('status')->default('OPEN');
            // OPEN, ACKNOWLEDGED, RESOLVED

            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamp('detected_at');

            $table->timestamps();

            $table->index(['alert_type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tessa_alerts');
    }
};
