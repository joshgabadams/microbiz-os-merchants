<?php

namespace App\Services\Payments;

use App\Domain\MPay\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentLocation;
use App\Models\AgentOperator;
use App\Models\AgentService;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Services\Branch\BranchBusinessDayService;
use Exception;

/**
 * Blueprint §10: Cash Transaction Guard.
 *
 * Every agent financial transaction must pass through the appropriate
 * composite guard. Controllers and transaction services should not
 * independently duplicate these common operational checks.
 *
 * Implemented controls include:
 *
 *   1.  Agent is active.
 *   2.  Agreement is active.
 *   3.  KYC review is valid.
 *   4.  Location is active.
 *   5.  Operator is active.
 *   7.  Terminal is active.
 *   9.  Terminal heartbeat is fresh.
 *   10. Requested service is enabled.
 *   11. Branch business day is open.
 *   12. Single transaction limit is not exceeded.
 *   13. Daily cumulative transaction limit is not exceeded.
 *   15. Agent electronic float is sufficient where required.
 *   16. Agent physical cash liquidity is sufficient where required.
 *   17. Idempotency support is available to transaction services.
 *   20. No suspension or restriction is active.
 *
 * Transaction-specific controls remain in their owning services where
 * they depend on request or transaction context rather than static
 * agent state. In particular:
 *
 *   - Geo-fence validation is performed against the transaction's real
 *     coordinates by the customer-facing transaction services.
 *   - Customer-account eligibility and customer authentication are
 *     enforced by the applicable transaction service.
 *   - Idempotency payload matching and replay handling are performed by
 *     the transaction idempotency service.
 *   - Approval requirements remain with workflows that genuinely use
 *     maker-checker approval rather than being simulated here.
 *   - Risk-rule orchestration remains separate from this guard until a
 *     dedicated risk service is introduced.
 */
class AgentOperationGuard
{
    public function __construct(
        protected BranchBusinessDayService $branchBusinessDayService
    ) {
    }

    /**
     * Require the agent to be ACTIVE.
     *
     * @throws Exception
     */
    public function checkAgentActive(Agent $agent): void
    {
        if ($agent->status !== AgentStatus::ACTIVE->value) {
            throw new Exception(
                "Agent {$agent->agent_code} is not ACTIVE."
            );
        }
    }

    /**
     * Require the agent to have an active agreement.
     *
     * @throws Exception
     */
    public function checkAgreementActive(Agent $agent): void
    {
        $hasActiveAgreement = $agent->agreements()
            ->where('status', 'ACTIVE')
            ->exists();

        if (! $hasActiveAgreement) {
            throw new Exception(
                "Agent {$agent->agent_code} has no active agreement."
            );
        }
    }

    /**
     * Require the agent's KYC process to be completed.
     *
     * @throws Exception
     */
    public function checkKycValid(Agent $agent): void
    {
        if ($agent->kyc_status !== 'COMPLETED') {
            throw new Exception(
                "Agent {$agent->agent_code} KYC is not completed."
            );
        }
    }

    /**
     * Reject suspended or restricted agents.
     *
     * @throws Exception
     */
    public function checkNotSuspendedOrRestricted(Agent $agent): void
    {
        if (
            in_array(
                $agent->status,
                [
                    AgentStatus::SUSPENDED->value,
                    AgentStatus::RESTRICTED->value,
                ],
                true
            )
        ) {
            throw new Exception(
                "Agent {$agent->agent_code} is {$agent->status} and cannot transact."
            );
        }
    }

    /**
     * Enforce the agent's configured single-transaction limit.
     *
     * @throws Exception
     */
    public function checkTransactionLimit(
        Agent $agent,
        float $amount
    ): void {
        if (
            $agent->single_transaction_limit !== null
            && $amount > (float) $agent->single_transaction_limit
        ) {
            throw new Exception(
                "Amount exceeds agent {$agent->agent_code}'s single transaction limit."
            );
        }
    }

    /**
     * Require the assigned agent location to be ACTIVE.
     *
     * @throws Exception
     */
    public function checkLocationActive(AgentLocation $location): void
    {
        if ($location->status !== 'ACTIVE') {
            throw new Exception(
                "Location {$location->location_code} is not ACTIVE."
            );
        }
    }

    /**
     * Require the agent operator to be ACTIVE.
     *
     * @throws Exception
     */
    public function checkOperatorActive(AgentOperator $operator): void
    {
        if ($operator->status !== 'ACTIVE') {
            throw new Exception(
                'Operator is not ACTIVE.'
            );
        }
    }

    /**
     * Require the assigned terminal to be ACTIVE.
     *
     * @throws Exception
     */
    public function checkTerminalActive(AgentTerminal $terminal): void
    {
        if ($terminal->status !== 'ACTIVE') {
            throw new Exception(
                "Terminal {$terminal->terminal_id} is not ACTIVE."
            );
        }
    }

    /**
     * Require the terminal to have a recent heartbeat.
     *
     * An ACTIVE status alone is not sufficient for transaction processing.
     * The terminal must also have communicated with the platform within the
     * configured heartbeat freshness window.
     *
     * @throws Exception
     */
    public function checkTerminalHeartbeatFresh(
        AgentTerminal $terminal
    ): void {
        if ($terminal->last_heartbeat_at === null) {
            throw new Exception(
                "Terminal {$terminal->terminal_id} has not sent a heartbeat."
            );
        }

        $timeoutSeconds = (int) config(
            'agency.terminal.heartbeat_timeout_seconds',
            300
        );

        if (
            $terminal->last_heartbeat_at
                ->lt(now()->subSeconds($timeoutSeconds))
        ) {
            throw new Exception(
                "Terminal {$terminal->terminal_id} heartbeat is stale."
            );
        }
    }

    /**
     * Require sufficient electronic agent float.
     *
     * Cash-in debits the agent's electronic float, so a cash-in must not
     * proceed when the available float is below the transaction amount.
     *
     * @throws Exception
     */
    public function checkAgentFloatSufficient(
        Agent $agent,
        float $amount
    ): void {
        $balance = AgentBalance::where(
            'agent_id',
            $agent->id
        )->first();

        if (
            ! $balance
            || (float) $balance->available_float < $amount
        ) {
            throw new Exception(
                "Agent {$agent->agent_code} has insufficient float for this transaction."
            );
        }
    }

    /**
     * Require sufficient physical cash liquidity.
     *
     * declared_physical_cash is derived from real cash-in/cash-out activity.
     * Cash-out therefore blocks when the system has not established enough
     * physical cash on hand to pay the customer.
     *
     * @throws Exception
     */
    public function checkAgentPhysicalLiquiditySufficient(
        Agent $agent,
        float $amount
    ): void {
        $balance = AgentBalance::where(
            'agent_id',
            $agent->id
        )->first();

        if (
            ! $balance
            || (float) $balance->declared_physical_cash < $amount
        ) {
            throw new Exception(
                "Agent {$agent->agent_code} has insufficient physical cash liquidity for this transaction."
            );
        }
    }

    /**
     * General-purpose idempotency uniqueness check.
     *
     * Transaction services may use more specific idempotency handling where
     * an existing request must be compared with the incoming request and the
     * original transaction returned safely.
     *
     * @throws Exception
     */
    public function checkIdempotencyKeyUnique(
        string $idempotencyKey,
        string $modelClass,
        string $column = 'idempotency_key'
    ): void {
        if (
            $modelClass::where(
                $column,
                $idempotencyKey
            )->exists()
        ) {
            throw new Exception(
                'This request has already been processed (duplicate idempotency key).'
            );
        }
    }

    /**
     * Require the requested agency service to be enabled and effective.
     *
     * Agent activation does not automatically grant every transaction
     * capability. Each service must be explicitly enabled for the agent
     * and must be within its configured effective period.
     *
     * @throws Exception
     */
    public function checkServiceEnabled(
        Agent $agent,
        string $serviceType
    ): void {
        $service = AgentService::where(
            'agent_id',
            $agent->id
        )
            ->where(
                'service_type',
                $serviceType
            )
            ->where(
                'status',
                'ENABLED'
            )
            ->first();

        if (! $service) {
            throw new Exception(
                "Agent {$agent->agent_code} does not have {$serviceType} enabled."
            );
        }

        if (
            $service->effective_date
            && $service->effective_date->isFuture()
        ) {
            throw new Exception(
                "The {$serviceType} service for agent {$agent->agent_code} is not yet effective."
            );
        }

        if (
            $service->expiry_date
            && $service->expiry_date->isPast()
        ) {
            throw new Exception(
                "The {$serviceType} service for agent {$agent->agent_code} has expired."
            );
        }
    }

    /**
     * Require the agent's assigned branch to have an open business day.
     *
     * @throws Exception
     */
    public function checkBusinessDayOpen(Agent $agent): void
    {
        if ($agent->branch_id === null) {
            throw new Exception(
                "Agent {$agent->agent_code} is not assigned to a branch."
            );
        }

        $this->branchBusinessDayService->requireOpenDay(
            $agent->branch_id
        );
    }

    /**
     * Enforce the agent's daily cumulative transaction limit.
     *
     * By default, all COMPLETED agent transactions for the current date are
     * counted against daily_transaction_limit. A caller may provide a limit
     * override and transaction type for a type-specific limit such as the
     * agent's daily cash-out limit.
     *
     * @throws Exception
     */
    public function checkDailyCumulativeLimit(
        Agent $agent,
        float $amount,
        ?float $limitOverride = null,
        ?string $transactionType = null
    ): void {
        $limit = $limitOverride
            ?? (
                $agent->daily_transaction_limit !== null
                    ? (float) $agent->daily_transaction_limit
                    : null
            );

        if ($limit === null) {
            return;
        }

        $query = AgentTransaction::where(
            'agent_id',
            $agent->id
        )
            ->where(
                'status',
                'COMPLETED'
            )
            ->whereDate(
                'transaction_date',
                now()->toDateString()
            );

        if ($transactionType !== null) {
            $query->where(
                'transaction_type',
                $transactionType
            );
        }

        $todayTotal = (float) $query->sum('amount');

        if (($todayTotal + $amount) > $limit) {
            throw new Exception(
                "This would exceed agent {$agent->agent_code}'s daily cumulative limit."
            );
        }
    }

    /**
     * Composite guard for float allocation and return operations.
     *
     * Float operations validate the agent-level eligibility controls that
     * can be established without customer-facing terminal context.
     *
     * @throws Exception
     */
    public function guardFloatOperation(
        Agent $agent,
        float $amount
    ): void {
        $this->checkAgentActive($agent);
        $this->checkAgreementActive($agent);
        $this->checkKycValid($agent);
        $this->checkNotSuspendedOrRestricted($agent);
        $this->checkTransactionLimit($agent, $amount);
    }

    /**
     * Composite guard for customer cash-in.
     *
     * Cash-in requires the common agent eligibility controls, an open branch
     * business day, active location/operator/terminal, a fresh terminal
     * heartbeat, sufficient electronic float, explicit CASH_IN permission,
     * and compliance with the agent's daily cumulative transaction limit.
     *
     * Geo-fence validation remains in AgentCashInService because it requires
     * the coordinates supplied for the actual transaction.
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
        $this->checkBusinessDayOpen($agent);
        $this->checkTransactionLimit($agent, $amount);
        $this->checkLocationActive($location);
        $this->checkOperatorActive($operator);
        $this->checkTerminalActive($terminal);
        $this->checkTerminalHeartbeatFresh($terminal);
        $this->checkAgentFloatSufficient($agent, $amount);
        $this->checkServiceEnabled($agent, 'CASH_IN');
        $this->checkDailyCumulativeLimit($agent, $amount);
    }

    /**
     * Composite guard for customer cash-out.
     *
     * Cash-out requires the common eligibility and terminal controls but
     * checks physical cash liquidity rather than electronic float because
     * the agent must have enough real cash on hand to pay the customer.
     *
     * Both the general daily transaction limit and the cash-out-specific
     * daily limit are enforced when configured.
     *
     * Geo-fence and customer-authentication checks remain in
     * AgentCashOutService because they depend on transaction context.
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
        $this->checkBusinessDayOpen($agent);
        $this->checkTransactionLimit($agent, $amount);
        $this->checkLocationActive($location);
        $this->checkOperatorActive($operator);
        $this->checkTerminalActive($terminal);
        $this->checkTerminalHeartbeatFresh($terminal);
        $this->checkAgentPhysicalLiquiditySufficient(
            $agent,
            $amount
        );
        $this->checkServiceEnabled(
            $agent,
            'CASH_OUT'
        );

        /*
         * General daily transaction limit:
         * all COMPLETED transaction types count.
         */
        $this->checkDailyCumulativeLimit(
            $agent,
            $amount
        );

        /*
         * Cash-out-specific daily limit:
         * only COMPLETED CASH_OUT transactions count.
         */
        if ($agent->daily_cash_out_limit !== null) {
            $this->checkDailyCumulativeLimit(
                $agent,
                $amount,
                (float) $agent->daily_cash_out_limit,
                'CASH_OUT'
            );
        }
    }

    /**
     * Composite guard for internal and interbank transfers.
     *
     * Transfers use the same agent/location/operator/terminal eligibility
     * controls as other customer-facing transactions, including heartbeat
     * freshness and an open branch business day.
     *
     * They deliberately do not require agent electronic float or physical
     * cash liquidity because the agent is facilitating the transfer rather
     * than funding it from the agent's own balances.
     *
     * The caller supplies the applicable service type so explicit service
     * enablement remains enforced for the transaction being attempted.
     *
     * @throws Exception
     */
    public function guardTransferOperation(
        Agent $agent,
        AgentLocation $location,
        AgentOperator $operator,
        AgentTerminal $terminal,
        float $amount,
        string $serviceType = 'TRANSFER'
    ): void {
        $this->checkAgentActive($agent);
        $this->checkAgreementActive($agent);
        $this->checkKycValid($agent);
        $this->checkNotSuspendedOrRestricted($agent);
        $this->checkBusinessDayOpen($agent);
        $this->checkTransactionLimit($agent, $amount);
        $this->checkLocationActive($location);
        $this->checkOperatorActive($operator);
        $this->checkTerminalActive($terminal);
        $this->checkTerminalHeartbeatFresh($terminal);
        $this->checkServiceEnabled(
            $agent,
            $serviceType
        );
        $this->checkDailyCumulativeLimit(
            $agent,
            $amount
        );
    }
}