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
 * AG-08: Cash-Out. Follows Module 10's exact workflow and Blueprint
 * §13.3's exact accounting model -- the reverse of AG-07's cash-in:
 *
 *   Debit: Customer deposit liability
 *   Credit: Agent electronic-float liability
 *
 * The agent pays physical cash to the customer, so
 * declared_physical_cash decreases here -- the same automatic
 * derivation AG-07 uses on the cash-in side, not a separate manual
 * declaration step.
 *
 * Physical liquidity is a real, load-bearing guard check here (unlike
 * cash-in, where it isn't relevant) -- an agent with sufficient
 * electronic float but no real cash on hand must not be able to hand
 * out cash that doesn't exist.
 *
 * No real customer-authentication subsystem exists yet in this
 * codebase, so this service requires explicit proof of authentication
 * as an input rather than inventing one -- the caller must have
 * already verified the customer before calling this.
 */
class AgentCashOutService
{
    public function __construct(
        protected AgentOperationGuard $guard,
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected GlPostingService $glPostingService,
        protected AgentFeeCalculationService $feeCalculationService
    ) {
    }

    /**
     * @throws Exception
     */
    public function cashOut(
        AgentOperator $operator,
        AgentTerminal $terminal,
        CustomerAccount $customerAccount,
        float $amount,
        string $idempotencyKey,
        float $latitude,
        float $longitude,
        int $performedBy,
        bool $customerAuthenticated,
        ?string $customerReference = null,
        ?string $narration = null
    ): AgentTransaction {
        if ($amount <= 0) {
            throw new Exception('Cash-out amount must be greater than zero.');
        }

        if (! $customerAuthenticated) {
            throw new Exception('Customer authentication is required before cash-out can proceed.');
        }

        // Idempotency: return the original transaction rather than
        // erroring or re-processing, same as AG-07.
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

        $this->guard->guardCashOutOperation($agent, $location, $operator, $terminal, $amount);

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

            if (! $balance || (float) $balance->declared_physical_cash < $amount) {
                throw new Exception("Agent {$agent->agent_code} has insufficient physical cash liquidity for this transaction.");
            }

            $transactionDate = now();
            $transactionNo = $this->transactionNumberService->generate('AGT');

            $feeAmount = $this->feeCalculationService->calculateFee($agent, 'CASH_OUT');
            $commissionAmount = $this->feeCalculationService->calculateCommission($agent, 'CASH_OUT');

            $agentTransaction = AgentTransaction::create([
                'transaction_no' => $transactionNo,
                'idempotency_key' => $idempotencyKey,
                'agent_id' => $agent->id,
                'agent_location_id' => $location->id,
                'agent_terminal_id' => $terminal->id,
                'agent_operator_id' => $operator->id,
                'transaction_type' => 'CASH_OUT',
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
            ]);

            // This is called BEFORE crediting the agent's float, so it
            // performs its own real, tested customer-balance-sufficiency
            // check (Customer balance checked, per Module 10) without
            // duplicating that logic here.
            $this->customerAccountService->withdraw(
                $customerAccount,
                $amount,
                $performedBy,
                $customerReference,
                $narration ?? 'Agent cash-out'
            );

            // Blueprint §13.3 exact direction -- the reverse of AG-07.
            $balance->ledger_float += $amount;
            $balance->available_float += $amount;
            $balance->declared_physical_cash -= $amount;

            if ($commissionAmount > 0) {
                $balance->pending_commission += $commissionAmount;
            }

            $balance->save();

            $cashLedger = CashLedger::create([
                'reference_no' => $transactionNo,
                'branch_id' => $agent->branch_id,
                'vault_id' => null,
                'teller_id' => null,
                'user_id' => $performedBy,
                'transaction_type' => 'AGENT_CASH_OUT',
                'source_type' => AgentTransaction::class,
                'source_id' => $agentTransaction->id,
                'entry_type' => 'CREDIT',
                'account_type' => 'AGENCY_FLOAT',
                'account_code' => 'AGENCY_FLOAT',
                'debit_account_key' => 'CUSTOMER_DEPOSIT_CONTROL',
                'credit_account_key' => 'AGENCY_FLOAT',
                'debit' => 0,
                'credit' => $amount,
                'running_balance' => $balance->ledger_float,
                'currency' => $balance->currency,
                'narration' => $narration ?? 'Agent cash-out',
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
     * Same haversine-distance geo-fence check as AgentCashInService.
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
