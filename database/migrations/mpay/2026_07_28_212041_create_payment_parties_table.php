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
        Schema::connection($this->connection)->create('payment_parties', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->enum('party_type', ['CUSTOMER', 'AGENT', 'MERCHANT']);
            $table->unsignedBigInteger('os_customer_id')->nullable();
            $table->string('fincore_customer_id')->nullable();
            $table->string('risk_level')->default('NORMAL');
            $table->timestamps();

            $table->index(['party_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('payment_parties');
    }
};
