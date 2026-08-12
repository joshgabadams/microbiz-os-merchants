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
use App\Models\AgentService;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
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
     * Real, load-bearing check closing a genuine production gap:
     * Blueprint Module 7 states explicitly that "an agent must not
     * automatically receive all service permissions merely because the
     * agent has been activated." Every active agent could do cash-in/
     * cash-out/transfer unconditionally before this existed.
     *
     * @throws Exception
     */
    public function checkServiceEnabled(Agent $agent, string $serviceType): void
    {
        $service = AgentService::where('agent_id', $agent->id)
            ->where('service_type', $serviceType)
            ->where('status', 'ENABLED')
            ->first();

        if (! $service) {
            throw new Exception("Agent {$agent->agent_code} does not have {$serviceType} enabled.");
        }

        if ($service->effective_date && $service->effective_date->isFuture()) {
            throw new Exception("The {$serviceType} service for agent {$agent->agent_code} is not yet effective.");
        }

        if ($service->expiry_date && $service->expiry_date->isPast()) {
            throw new Exception("The {$serviceType} service for agent {$agent->agent_code} has expired.");
        }
    }

    /**
     * Real, load-bearing check -- now genuinely buildable since
     * agent_transactions has real volume from AG-07/08/09. $limitOverride
     * lets callers pass a type-specific limit (e.g. Agent's own
     * daily_cash_out_limit for cash-out) instead of the general
     * daily_transaction_limit.
     *
     * @throws Exception
     */
    public function checkDailyCumulativeLimit(Agent $agent, float $amount, ?float $limitOverride = null): void
    {
        $limit = $limitOverride ?? ($agent->daily_transaction_limit !== null ? (float) $agent->daily_transaction_limit : null);

        if ($limit === null) {
            return;
        }

        $todayTotal = (float) AgentTransaction::where('agent_id', $agent->id)
            ->where('status', 'COMPLETED')
            ->whereDate('transaction_date', now()->toDateString())
            ->sum('amount');

        if (($todayTotal + $amount) > $limit) {
            throw new Exception("This would exceed agent {$agent->agent_code}'s daily cumulative limit.");
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
        $this->checkServiceEnabled($agent, 'CASH_IN');
        $this->checkDailyCumulativeLimit($agent, $amount);
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
        $this->checkServiceEnabled($agent, 'CASH_OUT');
        $this->checkDailyCumulativeLimit(
            $agent,
            $amount,
            $agent->daily_cash_out_limit === null ? null : (float) $agent->daily_cash_out_limit
        );
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
        float $amount,
        string $serviceType = 'TRANSFER'
    ): void {
        $this->checkAgentActive($agent);
        $this->checkAgreementActive($agent);
        $this->checkKycValid($agent);
        $this->checkNotSuspendedOrRestricted($agent);
        $this->checkTransactionLimit($agent, $amount);
        $this->checkLocationActive($location);
        $this->checkOperatorActive($operator);
        $this->checkTerminalActive($terminal);
        $this->checkServiceEnabled($agent, $serviceType);
        $this->checkDailyCumulativeLimit($agent, $amount);
    }
}
MBOS_EOF

cat > app/Services/Payments/AgentInterbankTransferService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\CustomerAccount;
use App\Services\Common\TransactionNumberService;
use Exception;

/**
 * AG-09: Interbank transfer via card-present agent terminal (customer's
 * own debit card, tapped/swiped on the agent's terminal, sending to an
 * account at a different bank).
 *
 * This is deliberately an honest shell, not a working feature. Two
 * genuine external dependencies are missing from this codebase and
 * cannot be built here:
 *
 *   1. Card acceptance -- reading the card, PIN entry, EMV/scheme
 *      processing. Normally handled by a certified PTSP (Payment
 *      Terminal Service Provider), not built in-house -- exactly why
 *      agent_terminals already has a `provider` column (Blueprint
 *      Module 6: "PTSP or technical provider"). This service never
 *      handles a PIN itself -- it accepts $pinVerified as proof the
 *      PTSP already did, per Blueprint §19's "No storage of customer
 *      PIN" and the same pattern AG-08 used for customer authentication.
 *
 *   2. Interbank rails -- actually moving money to a bank outside
 *      MicroBiz requires a real NIBSS (or equivalent switch)
 *      connection. Module 11 itself flags this as "Interbank transfer
 *      through approved integration" -- an external dependency, not an
 *      internal coding gap.
 *
 * What this DOES do honestly: validates the full real agent/location/
 * operator/terminal/geo-fence chain (identical rigor to cash-in/
 * cash-out/transfer), creates a real, audited agent_transactions
 * record, then calls dispatchToProcessor() -- the actual integration
 * point for a real PTSP/NIBSS connection once one exists. It always
 * throws today, and the transaction is correctly marked FAILED rather
 * than silently pretending to succeed. A "successful" interbank
 * transfer that doesn't actually move real money would be far more
 * dangerous than an honest, audited failure.
 *
 * The customer's source account is never touched here -- no debit is
 * applied unless dispatchToProcessor() genuinely confirms the transfer
 * was accepted by a real processor, which cannot happen today.
 */
class AgentInterbankTransferService
{
    public function __construct(
        protected AgentOperationGuard $guard,
        protected TransactionNumberService $transactionNumberService
    ) {
    }

    /**
     * @throws Exception
     */
    public function transfer(
        AgentOperator $operator,
        AgentTerminal $terminal,
        CustomerAccount $sourceAccount,
        string $cardReference,
        bool $pinVerified,
        string $destinationBankCode,
        string $destinationAccountNumber,
        string $destinationAccountName,
        float $amount,
        string $idempotencyKey,
        float $latitude,
        float $longitude,
        int $performedBy,
        ?string $narration = null
    ): AgentTransaction {
        if ($amount <= 0) {
            throw new Exception('Transfer amount must be greater than zero.');
        }

        if (! $pinVerified) {
            throw new Exception('Card PIN verification is required before an interbank transfer can proceed.');
        }

        $existing = AgentTransaction::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $agent = $operator->agent;
        $location = $operator->location;

        if ($terminal->agent_id !== $agent->id) {
            throw new Exception('This terminal does not belong to the specified agent.');
        }

        if ($terminal->agent_location_id !== $location->id) {
            throw new Exception("This terminal is not assigned to the operator's location.");
        }

        $this->guard->guardTransferOperation($agent, $location, $operator, $terminal, $amount, 'EXTERNAL_TRANSFER');

        $geoFencePassed = $this->isWithinGeoFence(
            $latitude,
            $longitude,
            (float) $terminal->registered_latitude,
            (float) $terminal->registered_longitude,
            $terminal->geo_fence_radius_metres
        );

        if (! $geoFencePassed) {
            throw new Exception("Transaction rejected: device is outside the terminal's approved geo-fence.");
        }

        if ($sourceAccount->status !== 'ACTIVE') {
            throw new Exception('Customer account is not active.');
        }

        $transactionNo = $this->transactionNumberService->generate('AGT');

        $agentTransaction = AgentTransaction::create([
            'transaction_no' => $transactionNo,
            'idempotency_key' => $idempotencyKey,
            'agent_id' => $agent->id,
            'agent_location_id' => $location->id,
            'agent_terminal_id' => $terminal->id,
            'agent_operator_id' => $operator->id,
            'transaction_type' => 'INTERBANK_TRANSFER',
            'status' => 'INITIATED',
            'amount' => $amount,
            'currency' => 'NGN',
            'customer_account_id' => $sourceAccount->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geo_fence_passed' => $geoFencePassed,
            'transaction_date' => now(),
            'performed_by' => $performedBy,
            'channel_metadata' => [
                // Never the full card number -- a masked/tokenised
                // reference only, per Blueprint §16.5.
                'card_reference' => $cardReference,
                'destination_bank_code' => $destinationBankCode,
                'destination_account_number' => $destinationAccountNumber,
                'destination_account_name' => $destinationAccountName,
            ],
        ]);

        try {
            $this->dispatchToProcessor($agentTransaction);
        } catch (Exception $e) {
            $agentTransaction->update(['status' => 'FAILED']);

            throw $e;
        }

        return $agentTransaction->fresh();
    }

    /**
     * The real integration point for a certified PTSP + NIBSS
     * connection. Deliberately not implemented -- see the class-level
     * documentation for why. Replace this method's body with a real
     * processor call once a PTSP relationship and NIBSS connectivity
     * exist; nothing else in this service should need to change.
     *
     * @throws Exception
     */
    protected function dispatchToProcessor(AgentTransaction $transaction): void
    {
        throw new Exception(
            'Interbank transfer processing is not available: no PTSP/NIBSS integration is configured. '
            .'This transaction has been recorded as FAILED for audit purposes.'
        );
    }

    protected function isWithinGeoFence(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $radiusMetres
    ): bool {
        $earthRadiusMetres = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceMetres = $earthRadiusMetres * $c;

        return $distanceMetres <= $radiusMetres;
    }
}
MBOS_EOF

echo "Part B applied (guard extension, interbank service update)."
