#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_12_000003_add_commission_amount_to_agent_services_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes another real gap ahead of production: agent_transactions.
 * fee_amount and commission_amount have existed since AG-07 but were
 * never populated. This adds the configuration side -- a flat,
 * per-agent-per-service commission amount, mirroring the existing
 * fee_amount field exactly, rather than inventing a percentage/split
 * rule that isn't confirmed anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_services', function (Blueprint $table) {
            $table->decimal('commission_amount', 24, 2)->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('agent_services', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
        });
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
        'commission_amount',
        'authentication_required',
        'effective_date',
        'expiry_date',
        'enabled_by',
        'enabled_at',
    ];

    protected $casts = [
        'limit_override' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
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
        ?string $expiryDate = null,
        ?float $commissionAmount = null
    ): AgentService {
        return AgentService::updateOrCreate(
            ['agent_id' => $agent->id, 'service_type' => $serviceType],
            [
                'status' => 'ENABLED',
                'limit_override' => $limitOverride,
                'fee_amount' => $feeAmount,
                'commission_amount' => $commissionAmount,
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

cat > app/Services/Payments/AgentFeeCalculationService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\Agent;
use App\Models\AgentService;

/**
 * Blueprint Module 14: "the frontend must therefore show fees before
 * confirmation." Fee and commission are configured per agent per
 * service via agent_services (flat amounts, mirroring how limits are
 * already configured -- no percentage/split rule invented here since
 * none is confirmed anywhere in the Blueprint).
 *
 * Scope is deliberately limited to calculation, disclosure, and
 * commission crediting -- NOT fee collection. How a fee is actually
 * charged (deducted from the transaction amount, a separate debit, or
 * absorbed from commission) is a real business decision that isn't
 * specified anywhere in the Blueprint text available, and guessing at
 * it risks moving real money the wrong way. previewFee() exists so a
 * real frontend confirmation step can disclose the fee before the
 * transaction executes; the transaction services populate and store
 * fee_amount/commission_amount for audit purposes and credit the
 * agent's pending_commission, but never touch the customer's balance
 * for the fee itself.
 */
class AgentFeeCalculationService
{
    public function calculateFee(Agent $agent, string $serviceType): float
    {
        $service = $this->findEnabledService($agent, $serviceType);

        return $service && $service->fee_amount !== null ? (float) $service->fee_amount : 0.0;
    }

    public function calculateCommission(Agent $agent, string $serviceType): float
    {
        $service = $this->findEnabledService($agent, $serviceType);

        return $service && $service->commission_amount !== null ? (float) $service->commission_amount : 0.0;
    }

    /**
     * Real disclosure primitive for Module 14 -- a frontend calls this
     * to show the fee before the customer confirms, without executing
     * anything.
     */
    public function previewFee(Agent $agent, string $serviceType): array
    {
        return [
            'fee_amount' => $this->calculateFee($agent, $serviceType),
            'commission_amount' => $this->calculateCommission($agent, $serviceType),
        ];
    }

    protected function findEnabledService(Agent $agent, string $serviceType): ?AgentService
    {
        return AgentService::where('agent_id', $agent->id)
            ->where('service_type', $serviceType)
            ->where('status', 'ENABLED')
            ->first();
    }
}
MBOS_EOF

echo "Fee Part A applied (migration, model, config service, calculation service)."
