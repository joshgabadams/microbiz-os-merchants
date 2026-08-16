#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_12_000002_create_agent_services_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes a real gap ahead of production: Module 7 states explicitly
 * "An agent must not automatically receive all service permissions
 * merely because the agent has been activated." Every active agent
 * could do cash-in/cash-out/transfer unconditionally until now.
 *
 * Scoped to the fields Module 7 lists that are actually consumed today
 * (status, limit override, fee, authentication requirement, effective/
 * expiry dates) -- approval threshold and channel availability are not
 * yet used anywhere and can be added later without a breaking change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->string('status')->default('ENABLED');
            $table->decimal('limit_override', 24, 2)->nullable();
            $table->decimal('fee_amount', 24, 2)->nullable();
            $table->boolean('authentication_required')->default(true);
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_services');
    }
};
MBOS_EOF

cat > app/Models/AgentService.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentService extends Model
{
    protected $fillable = [
        'agent_id',
        'service_type',
        'status',
        'limit_override',
        'fee_amount',
        'authentication_required',
        'effective_date',
        'expiry_date',
        'enabled_by',
        'enabled_at',
    ];

    protected $casts = [
        'limit_override' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'authentication_required' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'enabled_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function enabledBy()
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentServiceConfigurationService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentService;
use Exception;

/**
 * Blueprint §9/Module 7: per-agent explicit service enablement. An
 * agent must not automatically receive all service permissions merely
 * because the agent has been activated -- this is the service that
 * makes that opt-in, not opt-out.
 */
class AgentServiceConfigurationService
{
    public function enableService(
        Agent $agent,
        string $serviceType,
        int $enabledBy,
        ?float $limitOverride = null,
        ?float $feeAmount = null,
        bool $authenticationRequired = true,
        ?string $effectiveDate = null,
        ?string $expiryDate = null
    ): AgentService {
        return AgentService::updateOrCreate(
            ['agent_id' => $agent->id, 'service_type' => $serviceType],
            [
                'status' => 'ENABLED',
                'limit_override' => $limitOverride,
                'fee_amount' => $feeAmount,
                'authentication_required' => $authenticationRequired,
                'effective_date' => $effectiveDate,
                'expiry_date' => $expiryDate,
                'enabled_by' => $enabledBy,
                'enabled_at' => now(),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function disableService(Agent $agent, string $serviceType): AgentService
    {
        $service = AgentService::where('agent_id', $agent->id)
            ->where('service_type', $serviceType)
            ->first();

        if (! $service) {
            throw new Exception("Agent {$agent->agent_code} does not have {$serviceType} configured.");
        }

        $service->update(['status' => 'DISABLED']);

        return $service->fresh();
    }
}
MBOS_EOF

echo "Part A applied (migration, model, service)."
