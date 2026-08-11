<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use Exception;

/**
 * Blueprint §10: Cash Transaction Guard. Every agent financial
 * transaction must pass through this guard -- "no controller should
 * independently duplicate these checks."
 *
 * The Blueprint lists 20 checks. Each real, buildable check below is
 * its own independently callable method; composite guardXOperation()
 * methods call only the subset genuinely relevant to that operation
 * type, rather than faking checks that don't apply.
 *
 * Implemented now (Blueprint §10 numbering):
 *   1.  Agent is active
 *   2.  Agreement is active
 *   3.  KYC review is valid
 *   12. Transaction limit is not exceeded
 *   17. Idempotency key is unique (general-purpose; not used by
 *       guardFloatOperation() since agent_balances has no idempotency
 *       key column -- relevant once agent_transactions exists)
 *   20. No suspension or restriction is active
 *
 * Deliberately deferred, not faked:
 *   4-9   (location/operator/terminal/heartbeat/geo-fence) -- only
 *         relevant to customer-facing terminal transactions
 *         (AgentCashInService/AgentCashOutService, AG-07/08), not float
 *         allocation, which has no terminal or device involved.
 *   10    (requested service enabled) -- needs
 *         AgentServiceConfigurationService, not built.
 *   11    (business date open) -- MicroBiz OS has no independent
 *         business-date concept of its own yet.
 *   13    (daily cumulative limit) -- needs a real per-agent
 *         transaction log to sum against; agent_transactions
 *         (Blueprint §8.7) is AG-07/08 scope, not yet built.
 *   14-16 (customer eligibility, float/physical liquidity sufficiency)
 *         -- not applicable to float allocation itself (the agent's
 *         float is increasing, not being drawn down); relevant to
 *         AgentCashOutService instead.
 *   18    (risk rules) -- needs AgentRiskService, not built.
 *   19    (required approval obtained) -- handled structurally by
 *         AgentFloatService going through the existing maker-checker
 *         approval workflow, not by this guard directly.
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
     * General-purpose idempotency check -- not called by
     * guardFloatOperation() (agent_balances has no idempotency key
     * column); available for AG-07+ once agent_transactions exists.
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
     * Composite guard for float allocation/return -- only the checks
     * that genuinely apply to a back-office bank<->agent float
     * movement, not customer-facing terminal transaction checks.
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
}
