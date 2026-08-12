#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

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

/**
 * Blueprint §10: Cash Transaction Guard. Every agent financial
 * transaction must pass through this guard -- "no controller should
 * independently duplicate these checks."
 *
 * Implemented now (Blueprint §10 numbering):
 *   1.  Agent is active
 *   2.  Agreement is active
 *   3.  KYC review is valid
 *   4.  Location is active           (AG-07 addition)
 *   5.  Operator is active           (AG-07 addition)
 *   7.  Terminal is active           (AG-07 addition)
 *   12. Transaction limit is not exceeded
 *   15. Agent float is sufficient    (AG-07 addition -- cash-in debits
 *       the agent's electronic float per Blueprint §13.2, so it must
 *       be checked here, unlike float allocation which only credits it)
 *   17. Idempotency key is unique (used directly by
 *       AgentCashInService now that agent_transactions exists)
 *   20. No suspension or restriction is active
 *
 * Deliberately deferred, not faked:
 *   6, 8, 9 (terminal assigned, heartbeat, geo-fence device check) --
 *         geo-fence pass/fail is checked directly by
 *         AgentCashInService against real coordinates, not duplicated
 *         here as a generic guard check.
 *   10    (requested service enabled) -- needs
 *         AgentServiceConfigurationService, not built.
 *   11    (business date open) -- MicroBiz OS has no independent
 *         business-date concept of its own yet.
 *   13    (daily cumulative limit) -- needs real historical
 *         agent_transactions volume to sum against meaningfully.
 *   14    (customer account eligible) -- checked directly by
 *         AgentCashInService against the real CustomerAccount status.
 *   16    (physical liquidity sufficient) -- no physical-cash
 *         declaration workflow exists yet.
 *   18    (risk rules) -- needs AgentRiskService, not built.
 *   19    (required approval obtained) -- handled structurally by
 *         AgentFloatService going through the existing maker-checker
 *         approval workflow for float; cash-in is a direct
 *         customer-facing transaction, not a maker-checker request.
 */
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
     * Real, load-bearing check -- not deferred, unlike earlier sprints.
     * declared_physical_cash is derived automatically from real
     * cash-in/cash-out flow (see AgentCashInService), so this defaults
     * to blocking cash-out until an agent has genuinely received cash
     * through the system, rather than allowing withdrawals against
     * physical cash that was never actually confirmed on hand.
     *
     * @throws Exception
     */
    public function checkAgentPhysicalLiquiditySufficient(Agent $agent, float $amount): void
    {
        $balance = AgentBalance::where('agent_id', $agent->id)->first();

        if (! $balance || (float) $balance->declared_physical_cash < $amount) {
            throw new Exception("Agent {$agent->agent_code} has insufficient physical cash liquidity for this transaction.");
        }
    }

    /**
     * General-purpose idempotency check.
     *
     * @throws Exception
     */
    public function checkIdempotencyKeyUnique(string $idempotencyKey, string $modelClass, string $column = 'idempotency_key'): void
    {
        if ($modelClass::where($column, $idempotencyKey)->exists()) {
            throw new Exception('This request has already been processed (duplicate idempotency key).');
        }
    }

    /**
     * Composite guard for float allocation/return.
     *
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
     * Composite guard for cash-in -- adds the location/operator/
     * terminal/float-sufficiency checks that only apply to a real
     * customer-facing terminal transaction. Deliberately does not
     * include the geo-fence check here -- that's computed by
     * AgentCashInService against real coordinates, not a static
     * precondition this guard can check in isolation.
     *
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

    /**
     * Composite guard for cash-out -- same base checks as cash-in, but
     * checks physical cash liquidity (the agent must have enough real
     * cash on hand to pay the customer) rather than electronic float
     * sufficiency, per Blueprint §13.3 and Module 10's controls list.
     * Geo-fence and customer-authentication are checked directly by
     * AgentCashOutService, not duplicated here.
     *
     * @throws Exception
     */
    public function guardCashOutOperation(
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
        $this->checkAgentPhysicalLiquiditySufficient($agent, $amount);
    }

    /**
     * Composite guard for transfers (both internal customer-to-customer
     * and interbank) -- same agent/location/operator/terminal checks as
     * cash-in, but deliberately without float-sufficiency, since a
     * transfer never touches the agent's own electronic float or
     * physical cash -- the agent is only the facilitating channel.
     *
     * @throws Exception
     */
    public function guardTransferOperation(
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
    }
}
MBOS_EOF

echo "AG-09 Part 1a of 3 applied (guard extension)."
