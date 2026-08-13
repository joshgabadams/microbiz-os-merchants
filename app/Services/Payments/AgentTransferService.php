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
        protected AgentFeeCalculationService $feeCalculationService,
        protected AgentTransactionIdempotencyService $idempotencyService
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

        $agent = $operator->agent;
        $location = $operator->location;

        $existing = $this->idempotencyService->findExisting(
            $idempotencyKey,
            'TRANSFER',
            $agent->id,
            $location->id,
            $terminal->id,
            $operator->id,
            $fromAccount->id,
            $amount,
            [
                'to_customer_account_id' => $toAccount->id,
                'to_account_no' => $toAccount->account_no,
            ]
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
