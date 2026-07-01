<?php

namespace App\Services\Fineract;

use Illuminate\Support\Facades\Http;

class FineractClient
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
}