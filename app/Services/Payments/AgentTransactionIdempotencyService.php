<?php

namespace App\Services\Payments;

use App\Models\AgentTransaction;
use Exception;

class AgentTransactionIdempotencyService
{
    /**
     * Return the original transaction when the key represents the exact
     * same immutable request, reject reuse for a different request, or
     * return null when the key has never been used.
     *
     * @throws Exception
     */
    public function findExisting(
        string $idempotencyKey,
        string $transactionType,
        int $agentId,
        int $locationId,
        int $terminalId,
        int $operatorId,
        int $customerAccountId,
        float $amount,
        array $channelIdentity = []
    ): ?AgentTransaction {
        $existing = AgentTransaction::where(
            'idempotency_key',
            $idempotencyKey
        )->first();

        if (! $existing) {
            return null;
        }

        $matches =
            $existing->transaction_type === $transactionType
            && (int) $existing->agent_id === $agentId
            && (int) $existing->agent_location_id === $locationId
            && (int) $existing->agent_terminal_id === $terminalId
            && (int) $existing->agent_operator_id === $operatorId
            && (int) $existing->customer_account_id === $customerAccountId
            && $this->amountsMatch($existing->amount, $amount)
            && $this->channelIdentityMatches(
                $existing->channel_metadata ?? [],
                $channelIdentity
            );

        if (! $matches) {
            throw new Exception(
                'Idempotency key has already been used for a different transaction request.'
            );
        }

        return $existing;
    }

    protected function amountsMatch(mixed $storedAmount, float $requestedAmount): bool
    {
        return number_format((float) $storedAmount, 2, '.', '')
            === number_format($requestedAmount, 2, '.', '');
    }

    protected function channelIdentityMatches(
        array $storedMetadata,
        array $requestedIdentity
    ): bool {
        foreach ($requestedIdentity as $key => $value) {
            if (! array_key_exists($key, $storedMetadata)) {
                return false;
            }

            if ((string) $storedMetadata[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
