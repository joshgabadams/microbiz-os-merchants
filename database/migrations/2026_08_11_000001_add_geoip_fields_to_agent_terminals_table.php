<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Locator (issue #10) -- IP-derived location, stored separately
 * from the existing GPS fields (last_latitude/last_longitude). This is
 * a monitoring signal, never used in the geo_fence_compliant check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_terminals', function (Blueprint $table) {
            $table->string('last_ip_address')->nullable()->after('geo_fence_compliant');
            $table->decimal('ip_latitude', 10, 7)->nullable()->after('last_ip_address');
            $table->decimal('ip_longitude', 10, 7)->nullable()->after('ip_latitude');
            $table->string('ip_city')->nullable()->after('ip_longitude');
            $table->string('ip_state')->nullable()->after('ip_city');
            $table->string('ip_country', 2)->nullable()->after('ip_state');
            $table->boolean('ip_location_mismatch')->default(false)->after('ip_country');
            $table->timestamp('ip_checked_at')->nullable()->after('ip_location_mismatch');
        });
    }

    public function down(): void
    {
        Schema::table('agent_terminals', function (Blueprint $table) {
            $table->dropColumn([
                'last_ip_address',
                'ip_latitude',
                'ip_longitude',
                'ip_city',
                'ip_state',
                'ip_country',
                'ip_location_mismatch',
                'ip_checked_at',
            ]);
        });
    }
};
