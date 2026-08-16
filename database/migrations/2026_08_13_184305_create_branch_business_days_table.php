<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_business_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->date('business_date');

            // OPEN, CLOSED
            $table->string('status')->default('OPEN');

            $table->foreignId('opened_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('opened_at');

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['branch_id', 'business_date'],
                'branch_business_days_branch_date_unique'
            );

            $table->index(
                ['branch_id', 'status'],
                'branch_business_days_branch_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_business_days');
    }
};
