#!/bin/sh
set -e
cd /Users/user/microbiz/microbiz-os

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

echo "AG-09 Part 1c of 3 applied (interbank shell)."
