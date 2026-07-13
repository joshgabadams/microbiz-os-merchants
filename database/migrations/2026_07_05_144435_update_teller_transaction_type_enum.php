<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE teller_transactions 
            MODIFY transaction_type ENUM(
                'RECEIVE_FLOAT',
                'RETURN_FLOAT',
                'CUSTOMER_DEPOSIT',
                'CUSTOMER_WITHDRAWAL',
                'CASH_TRANSFER',
                'REVERSAL',
                'ADJUSTMENT'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE teller_transactions 
            MODIFY transaction_type ENUM(
                'FLOAT_RECEIVED',
                'FLOAT_RETURNED',
                'CUSTOMER_DEPOSIT',
                'CUSTOMER_WITHDRAWAL',
                'CASH_TRANSFER',
                'REVERSAL',
                'ADJUSTMENT'
            ) NOT NULL
        ");
    }
};

