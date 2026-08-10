<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE merchant_transactions
            MODIFY transaction_type ENUM(
                'QR_COLLECTION',
                'POS_COLLECTION',
                'REVERSAL',
                'ADJUSTMENT',
                'SETTLEMENT'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE merchant_transactions
            MODIFY transaction_type ENUM(
                'QR_COLLECTION',
                'POS_COLLECTION',
                'REVERSAL',
                'ADJUSTMENT'
            ) NOT NULL
        ");
    }
};
