<?php

namespace App\Services\Payments;

use App\Models\Merchant;
use App\Models\MerchantLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantLocationService
{
    public function list(Merchant $merchant)
    {
        return $merchant->locations()->get();
    }

    public function add(Merchant $merchant, array $data): MerchantLocation
    {
        return DB::transaction(function () use ($merchant, $data) {
            return $merchant->locations()->create([
                'branch_id' => $data['branch_id'] ?? $merchant->branch_id,
                'location_code' => $this->generateLocationCode(),
                'name' => $data['name'],
                'trading_name' => $data['trading_name'] ?? null,
                'address' => $data['address'],
                'state' => $data['state'] ?? null,
                'local_government' => $data['local_government'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'operating_hours' => $data['operating_hours'] ?? null,
                'risk_classification' => $data['risk_classification'] ?? null,
            ]);
        });
    }

    protected function generateLocationCode(): string
    {
        do {
            $code = 'LOC-'.strtoupper(Str::random(8));
        } while (MerchantLocation::where('location_code', $code)->exists());

        return $code;
    }
}
