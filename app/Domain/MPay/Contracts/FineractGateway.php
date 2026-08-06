<?php

namespace App\Domain\MPay\Contracts;

interface FineractGateway
{
    /**
     * Look up the real, authoritative balance for a FinCore account.
     * Never trust a locally cached balance for authorization decisions.
     */
    public function getAccountBalance(string $fincoreAccountId): array;

    /**
     * Post a transaction against a FinCore account. Returns the FinCore
     * posting reference on success.
     */
    public function postTransaction(
        string $fincoreAccountId,
        int $amountMinor,
        string $currency,
        string $reference,
        string $narration
    ): array;
}
