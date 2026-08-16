<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('till_sessions', function (Blueprint $table) {
            $table->foreignId('branch_business_day_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('branch_business_days')
                ->restrictOnDelete();

            $table->index(
                ['branch_business_day_id', 'status'],
                'till_sessions_business_day_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('till_sessions', function (Blueprint $table) {
            $table->dropIndex(
                'till_sessions_business_day_status_index'
            );

            $table->dropForeign([
                'branch_business_day_id',
            ]);

            $table->dropColumn(
                'branch_business_day_id'
            );
        });
    }
};
