<?php

namespace App\Services\Fineract;

use App\Domain\MPay\Contracts\FineractGateway;
use Illuminate\Support\Facades\Http;

class FineractClient implements FineractGateway
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('fineract.url');
    }

    protected function request()
    {
        return Http::withBasicAuth(
            config('fineract.user'),
            config('fineract.password')
        )->withHeaders([
            'Fineract-Platform-TenantId' =>
                config('fineract.tenant'),

            'Accept' => 'application/json'
        ]);
    }

    public function getOffices()
    {
        $response = $this->request()
            ->get($this->baseUrl . '/offices');

        return [
            'status' => $response->status(),
            'body' => $response->json()
        ];
    }

    public function getGlAccounts()
    {
        $response = $this->request()
            ->get($this->baseUrl . '/glaccounts');

        return [
            'status' => $response->status(),
            'body' => $response->json()
        ];
    }

    public function getAccountBalance(string $fincoreAccountId): array
    {
        $response = $this->request()
            ->get($this->baseUrl . "/savingsaccounts/{$fincoreAccountId}");

        $summary = $response->json('summary') ?? [];

        return [
            'balance_minor' => (int) round(($summary['accountBalance'] ?? 0) * 100),
            'available_balance_minor' => (int) round(($summary['availableBalance'] ?? 0) * 100),
            'currency' => $summary['currency']['code'] ?? null,
        ];
    }

    /**
     * Direction isn't a separate parameter on this contract -- amountMinor's
     * sign is what encodes deposit vs withdrawal (positive = credit/deposit,
     * negative = debit/withdrawal). Revisit if a real caller needs something
     * more explicit than sign-encoding.
     */
    public function postTransaction(
        string $fincoreAccountId,
        int $amountMinor,
        string $currency,
        string $reference,
        string $narration
    ): array {
        $command = $amountMinor >= 0 ? 'deposit' : 'withdrawal';

        $response = $this->request()
            ->post($this->baseUrl . "/savingsaccounts/{$fincoreAccountId}/transactions?command={$command}", [
                'transactionDate' => now()->format('d MMMM Y'),
                'transactionAmount' => abs($amountMinor) / 100,
                'locale' => 'en',
                'dateFormat' => 'd MMMM Y',
                'note' => "{$narration} (ref: {$reference})",
            ]);

        return [
            'fincore_reference' => (string) $response->json('resourceId'),
            'status' => $response->status(),
        ];
    }
}