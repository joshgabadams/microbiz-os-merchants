<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('merchant_terminals', 'm_terminals');
    }

    public function down(): void
    {
        Schema::rename('m_terminals', 'merchant_terminals');
    }
};
