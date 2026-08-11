<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * Resolves an IP address to an approximate location using a local
 * MaxMind GeoLite2 database file.
 *
 * Monitoring only (Agent Locator, issue #10) -- never used to allow or
 * block a transaction. Physical geofence enforcement happens on the
 * terminal's own hardware, independent of this service.
 */
class GeoIpLookupService
{
    public function lookup(string $ipAddress): ?GeoIpResult
    {
        if (! $this->isPublicIp($ipAddress)) {
            return null;
        }

        $path = rtrim(config('geoip.database_path'), '/').'/'.config('geoip.city_database');

        if (! file_exists($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);
            $record = $reader->city($ipAddress);
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }

        return new GeoIpResult(
            latitude: $record->location->latitude,
            longitude: $record->location->longitude,
            city: $record->city->name,
            state: $record->mostSpecificSubdivision->name,
            country: $record->country->isoCode,
        );
    }

    /**
     * Local/private/reserved addresses (localhost, LAN ranges) never
     * resolve to a real location -- lookups against them are pointless
     * and would otherwise throw on every local dev request.
     */
    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
