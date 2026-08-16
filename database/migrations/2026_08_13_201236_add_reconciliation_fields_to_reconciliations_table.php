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
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->foreignId('till_session_id')
                ->after('id')
                ->constrained('till_sessions')
                ->restrictOnDelete();

            $table->foreignId('teller_id')
                ->after('till_session_id')
                ->constrained('tellers')
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->after('teller_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->decimal('system_balance', 18, 2)
                ->after('branch_id');

            $table->decimal('physical_cash', 18, 2)
                ->after('system_balance');

            $table->decimal('variance', 18, 2)
                ->after('physical_cash');

            $table->string('status', 20)
                ->default('PENDING')
                ->after('variance');

            $table->foreignId('reconciled_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->after('reconciled_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')
                ->nullable()
                ->after('approved_by');

            $table->timestamp('reconciled_at')
                ->nullable()
                ->after('notes');

            $table->unique(
                'till_session_id',
                'reconciliations_till_session_unique'
            );

            $table->index(
                ['branch_id', 'status'],
                'reconciliations_branch_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropIndex(
                'reconciliations_branch_status_index'
            );

            $table->dropUnique(
                'reconciliations_till_session_unique'
            );

            $table->dropForeign(['approved_by']);
            $table->dropForeign(['reconciled_by']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['teller_id']);
            $table->dropForeign(['till_session_id']);

            $table->dropColumn([
                'till_session_id',
                'teller_id',
                'branch_id',
                'system_balance',
                'physical_cash',
                'variance',
                'status',
                'reconciled_by',
                'approved_by',
                'notes',
                'reconciled_at',
            ]);
        });
    }
};
