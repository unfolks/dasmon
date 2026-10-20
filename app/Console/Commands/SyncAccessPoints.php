<?php

namespace App\Console\Commands;

use App\Services\Zabbix\AccessPointService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zabbix:access-points {site=krian}')]
#[Description('Fetch and cache the current Zabbix Access Point snapshot')]
class SyncAccessPoints extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AccessPointService $service): int
    {
        $site = (string) $this->argument('site');
        if (! array_key_exists($site, config('zabbix.sites'))) {
            $this->error('Lokasi tidak dikenal.');

            return self::FAILURE;
        }
        $snapshot = $service->snapshot($site, refresh: true);
        $this->info('Status: '.$snapshot['meta']['status'].' | Access Point: '.count($snapshot['data']));
        if ($snapshot['meta']['message']) {
            $this->warn($snapshot['meta']['message']);
        }

        return $snapshot['meta']['status'] === 'live' ? self::SUCCESS : self::FAILURE;
    }
}
