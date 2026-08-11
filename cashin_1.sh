#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > database/migrations/2026_08_12_000001_create_agent_transactions_table.php << 'MBOS_EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_operator_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type')->index();
            $table->string('status')->default('INITIATED')->index();
            $table->decimal('amount', 24, 2);
            $table->decimal('fee_amount', 24, 2)->default(0);
            $table->decimal('commission_amount', 24, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('customer_account_id')
                ->nullable()
                ->constrained('customer_accounts');
            $table->string('customer_reference')->nullable();
            $table->string('processor_reference')->nullable()->index();
            $table->string('channel_reference')->nullable()->index();
            $table->string('rrn')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('geo_fence_passed');
            $table->timestamp('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->json('risk_metadata')->nullable();
            $table->json('channel_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_transactions');
    }
};
MBOS_EOF

cat > app/Models/AgentTransaction.php << 'MBOS_EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTransaction extends Model
{
    protected $fillable = [
        'transaction_no',
        'idempotency_key',
        'agent_id',
        'agent_location_id',
        'agent_terminal_id',
        'agent_operator_id',
        'transaction_type',
        'status',
        'amount',
        'fee_amount',
        'commission_amount',
        'currency',
        'customer_account_id',
        'customer_reference',
        'processor_reference',
        'channel_reference',
        'rrn',
        'latitude',
        'longitude',
        'geo_fence_passed',
        'transaction_date',
        'posted_at',
        'reversed_at',
        'performed_by',
        'approved_by',
        'risk_metadata',
        'channel_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geo_fence_passed' => 'boolean',
        'transaction_date' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'risk_metadata' => 'array',
        'channel_metadata' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function location()
    {
        return $this->belongsTo(AgentLocation::class, 'agent_location_id');
    }

    public function terminal()
    {
        return $this->belongsTo(AgentTerminal::class, 'agent_terminal_id');
    }

    public function operator()
    {
        return $this->belongsTo(AgentOperator::class, 'agent_operator_id');
    }

    public function customerAccount()
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentOperationGuard.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use Exception;

class AgentOperationGuard
{
    /**
     * @throws Exception
     */
    public function checkAgentActive(Agent $agent): void
    {
        if ($agent->status !== AgentStatus::ACTIVE->value) {
            throw new Exception("Agent {$agent->agent_code} is not ACTIVE.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkAgreementActive(Agent $agent): void
    {
        $hasActiveAgreement = $agent->agreements()->where('status', 'ACTIVE')->exists();

        if (! $hasActiveAgreement) {
            throw new Exception("Agent {$agent->agent_code} has no active agreement.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkKycValid(Agent $agent): void
    {
        if ($agent->kyc_status !== 'COMPLETED') {
            throw new Exception("Agent {$agent->agent_code} KYC is not completed.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkNotSuspendedOrRestricted(Agent $agent): void
    {
        if (in_array($agent->status, [AgentStatus::SUSPENDED->value, AgentStatus::RESTRICTED->value], true)) {
            throw new Exception("Agent {$agent->agent_code} is {$agent->status} and cannot transact.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkTransactionLimit(Agent $agent, float $amount): void
    {
        if ($agent->single_transaction_limit !== null && $amount > (float) $agent->single_transaction_limit) {
            throw new Exception("Amount exceeds agent {$agent->agent_code}'s single transaction limit.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkLocationActive(AgentLocation $location): void
    {
        if ($location->status !== 'ACTIVE') {
            throw new Exception("Location {$location->location_code} is not ACTIVE.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkOperatorActive(AgentOperator $operator): void
    {
        if ($operator->status !== 'ACTIVE') {
            throw new Exception('Operator is not ACTIVE.');
        }
    }

    /**
     * @throws Exception
     */
    public function checkTerminalActive(AgentTerminal $terminal): void
    {
        if ($terminal->status !== 'ACTIVE') {
            throw new Exception("Terminal {$terminal->terminal_id} is not ACTIVE.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkAgentFloatSufficient(Agent $agent, float $amount): void
    {
        $balance = AgentBalance::where('agent_id', $agent->id)->first();

        if (! $balance || (float) $balance->available_float < $amount) {
            throw new Exception("Agent {$agent->agent_code} has insufficient float for this transaction.");
        }
    }

    /**
     * @throws Exception
     */
    public function checkIdempotencyKeyUnique(string $idempotencyKey, string $modelClass, string $column = 'idempotency_key'): void
    {
        if ($modelClass::where($column, $idempotencyKey)->exists()) {
            throw new Exception('This request has already been processed (duplicate idempotency key).');
        }
    }

    /**
     * @throws Exception
     */
    public function guardFloatOperation(Agent $agent, float $amount): void
    {
        $this->checkAgentActive($agent);
        $this->checkAgreementActive($agent);
        $this->checkKycValid($agent);
        $this->checkNotSuspendedOrRestricted($agent);
        $this->checkTransactionLimit($agent, $amount);
    }

    /**
     * @throws Exception
     */
    public function guardCashInOperation(
        Agent $agent,
        AgentLocation $location,
        AgentOperator $operator,
        AgentTerminal $terminal,
        float $amount
    ): void {
        $this->checkAgentActive($agent);
        $this->checkAgreementActive($agent);
        $this->checkKycValid($agent);
        $this->checkNotSuspendedOrRestricted($agent);
        $this->checkTransactionLimit($agent, $amount);
        $this->checkLocationActive($location);
        $this->checkOperatorActive($operator);
        $this->checkTerminalActive($terminal);
        $this->checkAgentFloatSufficient($agent, $amount);
    }
}
MBOS_EOF

echo "AG-07 Part 1 of 2 applied (migration, model, guard extension)."