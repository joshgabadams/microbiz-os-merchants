<?php

namespace App\Services\Payments;

use App\Models\Merchant;
use App\Models\MerchantBeneficialOwner;
use App\Models\MerchantDocument;

class MerchantKycService
{
    public function listOwners(Merchant $merchant)
    {
        return $merchant->owners()->get();
    }

    public function addOwner(Merchant $merchant, array $data): MerchantBeneficialOwner
    {
        return $merchant->owners()->create([
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'identification_type' => $data['identification_type'] ?? null,
            'identification_number' => $data['identification_number'] ?? null,
            'ownership_percentage' => $data['ownership_percentage'] ?? 0,
            'is_director' => $data['is_director'] ?? false,
            'is_pep' => $data['is_pep'] ?? false,
        ]);
    }

    public function listDocuments(Merchant $merchant)
    {
        return $merchant->documents()->get();
    }

    public function addDocument(Merchant $merchant, array $data): MerchantDocument
    {
        return $merchant->documents()->create([
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }
}
