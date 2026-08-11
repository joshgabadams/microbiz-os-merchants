<?php

namespace App\Services\GeoIp;

class GeoIpResult
{
    public function __construct(
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
    ) {
    }
}
