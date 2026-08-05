<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A NEW migration, deliberately not an edit to the original
 * create_gl_journals_table migration -- Laravel skips already-run
 * migration files regardless of later content changes, so any database
 * that ran the original (incomplete, id/created_at/updated_at only)
 * version never picks up an edit to that file. This is the real fix for
 * such a database. hasColumn() guards make it a safe no-op anywhere the
 * columns already exist (e.g. a fresh install using the since-corrected
 * original migration).
 *
 * Assumes gl_journals is currently empty wherever this actually applies --
 * true everywhere GL posting has been failing, which is the whole premise
 * of this bug (CHANGELOG.md #1). If that's ever not the case somewhere,
 * the non-nullable columns below (gl_account_id, entry_type, amount,
 * reference, source_type, source_id) would need a backfill first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_journals', function (Blueprint $table) {

            if (! Schema::hasColumn('gl_journals', 'gl_account_id')) {
                $table->foreignId('gl_account_id')->after('id')->constrained();
            }

            if (! Schema::hasColumn('gl_journals', 'entry_type')) {
                $table->string('entry_type')->after('gl_account_id');
            }

            if (! Schema::hasColumn('gl_journals', 'amount')) {
                $table->decimal('amount', 24, 2)->after('entry_type');
            }

            if (! Schema::hasColumn('gl_journals', 'reference')) {
                $table->string('reference')->after('amount');
            }

            if (! Schema::hasColumn('gl_journals', 'source_type')) {
                $table->string('source_type')->after('reference');
            }

            if (! Schema::hasColumn('gl_journals', 'source_id')) {
                $table->unsignedBigInteger('source_id')->after('source_type');
            }

            if (! Schema::hasColumn('gl_journals', 'currency')) {
                $table->string('currency', 3)->default('NGN')->after('source_id');
            }

            if (! Schema::hasColumn('gl_journals', 'narration')) {
                $table->text('narration')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('gl_journals', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('narration');
            }

        });
    }

    public function down(): void
    {
        // Deliberately a no-op: this migration only ever adds columns that
        // should already exist per GlJournal.php. There's no universally
        // safe "down" without risking dropping columns a fresh install's
        // own original migration created directly, independent of this one.
    }
};
