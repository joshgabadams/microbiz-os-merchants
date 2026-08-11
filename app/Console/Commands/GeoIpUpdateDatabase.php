<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Refreshes the local MaxMind GeoLite2 database files used by the Agent
 * Locator monitoring feature (issue #10). MaxMind publishes updates
 * roughly weekly; this just needs to run at least that often.
 */
class GeoIpUpdateDatabase extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Download the latest MaxMind GeoLite2 database files via geoipupdate';

    public function handle(): int
    {
        $confPath = storage_path('app/GeoIP.conf');

        if (! file_exists($confPath)) {
            $this->error('storage/app/GeoIP.conf does not exist -- run scripts/generate-geoip-conf.sh first.');

            return self::FAILURE;
        }

        $result = Process::run(['geoipupdate', '-f', $confPath, '-v']);

        $this->output->write($result->output());
        $this->output->write($result->errorOutput());

        if (! $result->successful()) {
            $this->error('geoipupdate failed.');

            return self::FAILURE;
        }

        $this->info('GeoLite2 databases updated.');

        return self::SUCCESS;
    }
}
