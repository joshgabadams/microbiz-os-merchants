<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mpay';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('terminal_code')->unique();
            $table->string('serial_number')->nullable();
            $table->string('processor')->default('INTERSWITCH');
            $table->string('status')->default('ACTIVE');
            // References the main database's users.id — not DB-constrained,
            // since 'mpay' is a physically separate database (same pattern
            // as payment_parties.os_customer_id).
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('pos_terminals');
    }
};
