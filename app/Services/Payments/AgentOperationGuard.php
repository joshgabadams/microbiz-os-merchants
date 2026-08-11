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
