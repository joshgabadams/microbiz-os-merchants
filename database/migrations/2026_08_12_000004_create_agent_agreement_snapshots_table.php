<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen copies of Schedule 1 (Agent/Location) and Schedule 5 (Terminal)
 * data at drafting/execution time. source_id is for traceability only --
 * never used for live lookups, since the whole point is that this data
 * must not change if the live Agent/Location/Terminal record changes
 * later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_agreement_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('snapshot_type');
            $table->unsignedBigInteger('source_id');
            $table->json('snapshot_data');
            $table->timestamp('snapshotted_at');
            $table->timestamps();

            $table->index(['agent_agreement_id', 'snapshot_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_agreement_snapshots');
    }
};
