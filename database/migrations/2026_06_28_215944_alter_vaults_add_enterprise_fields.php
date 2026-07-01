<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaults', function (Blueprint $table) {

            if (!Schema::hasColumn('vaults', 'code')) {
                $table->string('code')->unique()->after('id');
            }

            if (!Schema::hasColumn('vaults', 'type')) {
                $table->enum('type', [
                    'HEAD_OFFICE',
                    'MAIN',
                    'RESERVE',
                    'ATM',
                    'CASH_IN_TRANSIT',
                    'TREASURY'
                ])->default('MAIN')->after('name');
            }

            if (!Schema::hasColumn('vaults', 'currency')) {
                $table->string('currency', 3)
                    ->default('NGN')
                    ->after('type');
            }

            if (!Schema::hasColumn('vaults', 'minimum_balance')) {
                $table->decimal('minimum_balance', 24, 2)
                    ->default(0)
                    ->after('currency');
            }

            if (!Schema::hasColumn('vaults', 'maximum_balance')) {
                $table->decimal('maximum_balance', 24, 2)
                    ->default(999999999999)
                    ->after('minimum_balance');
            }

        });
    }

    public function down(): void
    {
        Schema::table('vaults', function (Blueprint $table) {

            $table->dropColumn([
                'code',
                'type',
                'currency',
                'minimum_balance',
                'maximum_balance'
            ]);

        });
    }
};
