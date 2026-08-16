<?php

namespace App\Services\Payments;

use App\Models\AgentBalance;
use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\CashLedger;
use App\Models\CustomerAccount;
use App\Services\Accounting\GlPostingService;
use App\Services\Common\TransactionNumberService;
use App\Services\Customer\CustomerAccountService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * AG-07: Cash-In. Follows Module 9's exact workflow and Blueprint
 * §13.2's exact accounting model:
 *
 *   Debit: Agent electronic-float liability
 *   Credit: Customer deposit liability
 *
 * This is the non-obvious direction -- cash-in DECREASES the agent's
 * tracked electronic float, not increases it, because the agent now
 * holds untracked physical cash while the customer's balance moves
 * electronically. Physical cash itself is not tracked here (no
 * declared-physical-cash workflow exists yet); only the electronic
 * float liability moves.
 *
 * Reuses CustomerAccountService::deposit() (no second cash engine),
 * and the same CashLedger/GlPostingService pipeline as every other
 * financial movement in this codebase.
 */
class AgentCashInService
{
    public function __construct(
        protected AgentOperationGuard $guard,
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService,
        protected AgentFeeCalculationService $feeCalculationService,
        protected AgentTransactionIdempotencyService $idempotencyService,
        protected AgentTransactionRiskService $riskService
    ) {}

    /**
     * @throws Exception
     */
    public function cashIn(
        AgentOperator $operator,
        AgentTerminal $terminal,
        CustomerAccount $customerAccount,
        float $amount,
        string $idempotencyKey,
        float $latitude,
        float $longitude,
        int $performedBy,
        ?string $customerReference = null,
        ?string $narration = null
    ): AgentTransaction {
        if ($amount <= 0) {
            throw new Exception('Cash-in amount must be greater than zero.');
        }

        $agent = $operator->agent;
        $location = $operator->location;

        $existing = $this->idempotencyService->findExisting(
            $idempotencyKey,
            'CASH_IN',
            $agent->id,
            $location->id,
            $terminal->id,
            $operator->id,
            $customerAccount->id,
            $amount
        );

        if ($existing) {
            return $existing;
        }

        if ($terminal->agent_id !== $agent->id) {
            throw new Exception('This terminal does not belong to the specified agent.');
        }

        if ($terminal->agent_location_id !== $location->id) {
            throw new Exception("This terminal is not assigned to the operator's location.");
        }

        $this->guard->guardCashInOperation($agent, $location, $operator, $terminal, $amount);

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

        if ($customerAccount->status !== 'ACTIVE') {
            throw new Exception('Customer account is not active.');
        }

        return DB::transaction(function () use (
            $agent,
            $location,
            $operator,
            $terminal,
            $customerAccount,
            $amount,
            $idempotencyKey,
            $latitude,
            $longitude,
            $geoFencePassed,
            $performedBy,
            $customerReference,
            $narration
        ) {
            $balance = AgentBalance::where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();

            if (! $balance || (float) $balance->available_float < $amount) {
                throw new Exception("Agent {$agent->agent_code} has insufficient float for this transaction.");
            }

            $transactionDate = now();
            $transactionNo = $this->transactionNumberService->generate('AGT');

            // Blueprint Module 14: fee/commission calculated and stored
            // for disclosure/audit here. Deliberately NOT deducted from
            // the customer -- see class-level note on why fee
            // collection is out of scope for this pass.
            $feeAmount = $this->feeCalculationService->calculateFee($agent, 'CASH_IN');
            $commissionAmount = $this->feeCalculationService->calculateCommission($agent, 'CASH_IN');
            // Risk is observational at this stage: assess the transaction and
            // persist the result for audit/monitoring, but do not block processing
            // based on the resulting risk level until an explicit enforcement
            // policy is introduced.
            $riskAssessment = $this->riskService->assess(
                $agent,
                $terminal,
                'CASH_IN',
                $amount
            );

            $agentTransaction = AgentTransaction::create([

                'transaction_no' => $transactionNo,
                'idempotency_key' => $idempotencyKey,
                'agent_id' => $agent->id,
                'agent_location_id' => $location->id,
                'agent_terminal_id' => $terminal->id,
                'agent_operator_id' => $operator->id,
                'transaction_type' => 'CASH_IN',
                'status' => 'INITIATED',
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'commission_amount' => $commissionAmount,
                'currency' => $balance->currency,
                'customer_account_id' => $customerAccount->id,
                'customer_reference' => $customerReference,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_fence_passed' => $geoFencePassed,
                'transaction_date' => $transactionDate,
                'performed_by' => $performedBy,
                'risk_metadata' => $riskAssessment,
            ]);

            // Blueprint §13.2 exact direction.
            $balance->ledger_float -= $amount;
            $balance->available_float -= $amount;
            // The agent physically receives cash from the customer here
            // -- declared_physical_cash is derived from real cash-in/
            // cash-out flow rather than requiring a separate manual
            // "declare cash" workflow, which makes the physical-liquidity
            // check AG-08's cash-out relies on meaningful by default.
            $balance->declared_physical_cash += $amount;

            if ($commissionAmount > 0) {
                $balance->pending_commission += $commissionAmount;
            }

            $balance->save();

            $this->customerAccountService->deposit(
                $customerAccount,
                $amount,
                $performedBy,
                $customerReference,
                $narration ?? 'Agent cash-in'
            );

            $cashLedger = CashLedger::create([
                'reference_no' => $transactionNo,
                'branch_id' => $agent->branch_id,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_CASH_IN',
                'source_type' => AgentTransaction::class,
                'source_id' => $agentTransaction->id,
                'entry_type' => 'DEBIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'AGENCY_FLOAT',
                'credit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'debit' => $amount,
                'credit' => 0,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Agent cash-in',
                'status' => 'PENDING',
                'approved_by' => null,
                'transaction_date' => $transactionDate,
            ]);

            $this->glPostingService->postFromCashLedger($cashLedger);

            $cashLedger->status = 'APPROVED';
            $cashLedger->save();

            $balance->last_transaction_id = $cashLedger->id;
            $balance->save();

            $agentTransaction->update([
                'status' => 'COMPLETED',
                'posted_at' => now(),
            ]);

            $terminal->update([
                'last_transaction_at' => now(),
                'last_latitude' => $latitude,
                'last_longitude' => $longitude,
            ]);

            return $agentTransaction->fresh();
        });
    }

    /**
     * Haversine-distance geo-fence check. A dedicated
     * AgentGeoFenceService (Blueprint §9) would be the real home for
     * this once terminal heartbeat/remote-suspension logic (AG-05)
     * exists too -- this is the minimal, correct version needed now.
     */
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
