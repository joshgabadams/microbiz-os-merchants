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

    /**
     * Creates a real Fincore360 client for an applicant with no existing
     * account -- the "new merchant" path (as opposed to searchByAccountNumber
     * + getClient(), which is the "existing customer" path). $data is
     * already-validated, Person/Entity-branched input from
     * CreateMerchantIdentityRequest; this method just maps it onto
     * Fineract's PostClientsRequest shape and creates the client active
     * immediately (activationDate = submittedOnDate), matching how the
     * existing test clients in this instance behave.
     */
    public function createClient(array $data): array
    {
        $today = now()->format('d F Y');

        $payload = [
            'officeId' => $data['office_id'],
            'legalFormId' => $data['legal_form_id'],
            'mobileNo' => $data['phone'],
            'emailAddress' => $data['email'] ?? null,
            'active' => true,
            'activationDate' => $today,
            'submittedOnDate' => $today,
            'locale' => 'en',
            'dateFormat' => 'dd MMMM yyyy',
        ];

        if ($data['legal_form_id'] === 1) {
            $payload['firstname'] = $data['first_name'];
            $payload['lastname'] = $data['last_name'];
            if (! empty($data['date_of_birth'])) {
                // Applicant sends a normal Y-m-d date; Fineract wants it in
                // whatever dateFormat this payload declares ('dd MMMM yyyy').
                $payload['dateOfBirth'] = \Carbon\Carbon::parse($data['date_of_birth'])->format('d F Y');
            }
        } else {
            $payload['fullname'] = $data['full_name'];
        }

        $response = $this->request()->post($this->baseUrl . '/clients', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Fineract client creation failed: HTTP {$response->status()}: {$response->body()}"
            );
        }

        $created = $response->json();

        // The create endpoint only returns {officeId, clientId, resourceId,
        // ...} -- fetch the full record so callers get the same shape
        // getClient() already returns elsewhere in the registration flow.
        return $this->getClient($created['clientId']);
    }
}
