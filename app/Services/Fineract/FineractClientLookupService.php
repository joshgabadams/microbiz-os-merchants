<?php

namespace App\Services\Fineract;

use Illuminate\Support\Facades\Http;

/**
 * Resolves a Fineract client by account number for the merchant
 * registration preview/confirm flow. Separate from FineractClient
 * (GL accounts, offices, savings-account balance/posting) since this is
 * a distinct concern -- client identity lookup, not accounting.
 */
class FineractClientLookupService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('fineract360.url');
    }

    protected function request()
    {
        return Http::withBasicAuth(
            config('fineract360.user'),
            config('fineract360.password')
        )->withHeaders([
            'Fineract-Platform-TenantId' => config('fineract360.tenant'),
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Searches Fineract's global search for clients matching the given
     * account number. Returns the raw list of matches (usually zero or
     * one) -- each entry has entityId, which is what getClient() expects.
     */
    public function searchByAccountNumber(string $accountNumber): array
    {
        $response = $this->request()->get($this->baseUrl . '/search', [
            'query' => $accountNumber,
            'resource' => 'clients',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Fineract search failed: HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Fetches full client detail by Fineract client id. Always called
     * server-side with a server-resolved id -- never trust a client id
     * sent back from the frontend without having independently resolved
     * it via searchByAccountNumber() first, or you lose the guarantee
     * that the account number the officer typed actually matches this id.
     */
    public function getClient(int $clientId): array
    {
        $response = $this->request()->get($this->baseUrl . "/clients/{$clientId}");

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Fineract client fetch failed: HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }
}
