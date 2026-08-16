#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

cat > app/Services/Payments/AgentCashInService.php << 'MBOS_EOF'
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
        protected AgentFeeCalculationService $feeCalculationService
    ) {
    }

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

        // Idempotency: return the original transaction rather than
        // erroring or re-processing, per Blueprint §20's exact test --
        // "Duplicate idempotency request returns original response."
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
MBOS_EOF

cat > app/Services/Payments/AgentCashOutService.php << 'MBOS_EOF'
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
MBOS_EOF

cat > app/Services/Payments/AgentTransferService.php << 'MBOS_EOF'
<?php

namespace App\Services\Payments;

use App\Models\AgentOperator;
use App\Models\AgentTerminal;
use App\Models\AgentTransaction;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountBalance;
use App\Services\Common\TransactionNumberService;
use App\Services\Customer\CustomerAccountService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * AG-09: Transfers (MicroBiz-to-MicroBiz only). An agent-facilitated
 * transfer between two customer accounts within MicroBiz -- not the
 * agent's own float, which is never touched here. The agent is only
 * the facilitating channel, matching Module 11's distinction between
 * this and true interbank transfer (see AgentInterbankTransferService).
 *
 * Reuses CustomerAccountService::withdraw()/deposit() directly (no
 * second cash engine), with the same ordered-balance-locking discipline
 * WalletService::transfer() already established, to avoid deadlocks if
 * two transfers between the same pair of accounts run concurrently.
 *
 * No GL posting -- matching WalletService's exact precedent for
 * same-institution transfers: both accounts are liabilities on the same
 * balance sheet, so the net GL effect of moving money between them is
 * zero. The two CustomerAccountTransaction records (created inside
 * withdraw()/deposit()) provide the full audit trail instead.
 */
class AgentTransferService
{
    public function __construct(
        protected AgentOperationGuard $guard,
        protected CustomerAccountService $customerAccountService,
        protected TransactionNumberService $transactionNumberService,
        protected AgentFeeCalculationService $feeCalculationService
    ) {
    }

    /**
     * @throws Exception
     */
    public function transfer(
        AgentOperator $operator,
        AgentTerminal $terminal,
        CustomerAccount $fromAccount,
        CustomerAccount $toAccount,
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

        if ($fromAccount->id === $toAccount->id) {
            throw new Exception('Cannot transfer an account to itself.');
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

        $this->guard->guardTransferOperation($agent, $location, $operator, $terminal, $amount);

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

        if ($fromAccount->status !== 'ACTIVE') {
            throw new Exception('Source customer account is not active.');
        }

        if ($toAccount->status !== 'ACTIVE') {
            throw new Exception('Destination customer account is not active.');
        }

        return DB::transaction(function () use (
            $agent,
            $location,
            $operator,
            $terminal,
            $fromAccount,
            $toAccount,
            $amount,
            $idempotencyKey,
            $latitude,
            $longitude,
            $geoFencePassed,
            $performedBy,
            $narration
        ) {
            // Lock both balance rows in a consistent order (lower ID
            // first) regardless of transfer direction, matching
            // WalletService::transfer()'s deadlock-avoidance discipline.
            $orderedIds = $fromAccount->id < $toAccount->id
                ? [$fromAccount->id, $toAccount->id]
                : [$toAccount->id, $fromAccount->id];

            CustomerAccountBalance::whereIn('customer_account_id', $orderedIds)
                ->orderBy('customer_account_id')
                ->lockForUpdate()
                ->get();

            $transactionDate = now();
            $transactionNo = $this->transactionNumberService->generate('AGT');

            $feeAmount = $this->feeCalculationService->calculateFee($agent, 'TRANSFER');
            $commissionAmount = $this->feeCalculationService->calculateCommission($agent, 'TRANSFER');

            $agentTransaction = AgentTransaction::create([
                'transaction_no' => $transactionNo,
                'idempotency_key' => $idempotencyKey,
                'agent_id' => $agent->id,
                'agent_location_id' => $location->id,
                'agent_terminal_id' => $terminal->id,
                'agent_operator_id' => $operator->id,
                'transaction_type' => 'TRANSFER',
                'status' => 'INITIATED',
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'commission_amount' => $commissionAmount,
                'currency' => 'NGN',
                'customer_account_id' => $fromAccount->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_fence_passed' => $geoFencePassed,
                'transaction_date' => $transactionDate,
                'performed_by' => $performedBy,
                'channel_metadata' => [
                    'to_customer_account_id' => $toAccount->id,
                    'to_account_no' => $toAccount->account_no,
                ],
            ]);

            $this->customerAccountService->withdraw(
                $fromAccount,
                $amount,
                $performedBy,
                null,
                $narration ?? "Agent transfer to {$toAccount->account_no}"
            );

            $this->customerAccountService->deposit(
                $toAccount,
                $amount,
                $performedBy,
                null,
                $narration ?? "Agent transfer from {$fromAccount->account_no}"
            );

            $agentTransaction->update([
                'status' => 'COMPLETED',
                'posted_at' => now(),
            ]);

            if ($commissionAmount > 0) {
                $balance = \App\Models\AgentBalance::firstOrCreate(
                    ['agent_id' => $agent->id],
                    ['currency' => 'NGN']
                );

                $balance = \App\Models\AgentBalance::where('agent_id', $agent->id)
                    ->lockForUpdate()
                    ->first();

                $balance->pending_commission += $commissionAmount;
                $balance->save();
            }

            $terminal->update([
                'last_transaction_at' => now(),
                'last_latitude' => $latitude,
                'last_longitude' => $longitude,
            ]);

            return $agentTransaction->fresh();
        });
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

echo "Fee Part B applied (cash-in/cash-out/transfer wired with fee/commission)."
