<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avoids Schema::table(...)->change() on the enum-backed `status` column.
 * SQLite emulates enum() as a CHECK constraint, and Doctrine's SQLite
 * schema manager cannot reliably rebuild a table around that constraint
 * (fails with "create table __temp__merchants ()" regardless of whether
 * doctrine/dbal is installed). Renaming, adding a plain column, copying
 * data across, then dropping the old one uses only native ADD/RENAME/DROP
 * COLUMN operations, which SQLite (3.35+) and every other supported driver
 * handle directly without a full table rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->renameColumn('status', 'status_old');
        });

        Schema::table('merchants', function (Blueprint $table) {

            $table->string('status')->default('DRAFT');

            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->timestamp('activated_at')->nullable();

        });

        DB::statement('UPDATE merchants SET status = status_old');

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('status_old');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->renameColumn('status', 'status_new');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE');
        });

        DB::statement('UPDATE merchants SET status = status_new');

        Schema::table('merchants', function (Blueprint $table) {

            $table->dropConstrainedForeignId('approved_by');

            $table->dropColumn([
                'status_new',
                'submitted_at',
                'approved_at',
                'rejection_reason',
                'activated_at',
            ]);

        });
    }
};
