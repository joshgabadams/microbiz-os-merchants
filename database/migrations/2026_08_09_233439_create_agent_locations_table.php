<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->restrictOnDelete();

            $table->string('location_code')->unique();

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('local_government');
            $table->string('state');

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->unsignedInteger('approved_radius_metres')->default(10);

            $table->string('verification_status')->default('PENDING');
            $table->string('status')->default('PENDING');

            $table->json('verification_evidence')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['agent_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_locations');
    }
};
