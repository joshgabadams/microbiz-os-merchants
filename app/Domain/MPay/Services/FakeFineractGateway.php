<?php

namespace App\Domain\MPay\Services;

use App\Domain\MPay\Contracts\FineractGateway;
use Illuminate\Support\Str;

/**
 * Local dev / automated-test stand-in for FineractGateway. Only bound for
 * the `local` and `testing` environments -- see MPayServiceProvider for the
 * environment-based binding that keeps this out of staging/production.
 */
class FakeFineractGateway implements FineractGateway
{
    protected static array $balances = [];

    public function getAccountBalance(string $fincoreAccountId): array
    {
        $balance = static::$balances[$fincoreAccountId] ?? 5_000_00;

        return [
            'balance_minor' => $balance,
            'available_balance_minor' => $balance,
            'currency' => 'NGN',
        ];
    }

    public function postTransaction(
        string $fincoreAccountId,
        int $amountMinor,
        string $currency,
        string $reference,
        string $narration
    ): array {
        $current = static::$balances[$fincoreAccountId] ?? 5_000_00;
        static::$balances[$fincoreAccountId] = $current + $amountMinor;

        return [
            'fincore_reference' => 'FAKE-' . Str::uuid(),
            'status' => 200,
        ];
    }
}
