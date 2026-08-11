<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MaxMind GeoLite2 -- Agent Locator monitoring (issue #10)
    |--------------------------------------------------------------------------
    |
    | Monitoring only, never enforcement -- physical geofence enforcement
    | happens on the terminal's own hardware/binary, which this backend
    | never controls in real time. This is an independent signal used to
    | flag anomalies (a terminal's network traffic resolving to a
    | location far from its own GPS-reported position), not to allow or
    | block anything.
    |
    | Database files are downloaded via `geoipupdate` (see
    | scripts/generate-geoip-conf.sh) into MAXMIND_DB_PATH -- they are
    | large binaries under MaxMind's license, never git-committed.
    |
    */

    'database_path' => env('MAXMIND_DB_PATH', storage_path('app/geoip')),

    'city_database' => 'GeoLite2-City.mmdb',

    // Distance beyond which an IP-derived location is considered a
    // mismatch against the terminal's GPS-reported position -- wide on
    // purpose, since city-level IP geolocation is imprecise; this is a
    // "does this look wrong" flag, not a precise measurement.
    'mismatch_threshold_km' => 100,

];
