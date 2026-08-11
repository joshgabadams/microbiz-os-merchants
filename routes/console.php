<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First scheduled task in this application -- for this to actually run
// in production, cron must call `php artisan schedule:run` every
// minute (standard Laravel deployment requirement, not yet configured
// anywhere in this project as far as this session has seen).
Schedule::command('tessa:detect-alerts')->everyFifteenMinutes();

// MaxMind publishes GeoLite2 updates roughly weekly -- daily is a safe
// margin. Same cron caveat as above: needs `php artisan schedule:run`
// wired into an actual crontab to take effect anywhere.
Schedule::command('geoip:update')->daily();
