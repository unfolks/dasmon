<?php

namespace App\Console\Commands;

use App\Services\Zabbix\ComputerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zabbix:computers {site=krian}')]
#[Description('Fetch and cache the current Zabbix Computer snapshot')]
class SyncComputers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ComputerService $service): int
    {
        $site = (string) $this->argument('site');
        if (! array_key_exists($site, config('zabbix.sites'))) {
            $this->error('Lokasi tidak dikenal.');

            return self::FAILURE;
        }
        $snapshot = $service->snapshot($site, refresh: true);
        $this->info('Status: '.$snapshot['meta']['status'].' | Computers: '.count($snapshot['data']));
        if ($snapshot['meta']['message']) {
            $this->warn($snapshot['meta']['message']);
        }
        $this->table(['Computer', 'Status', 'Monitoring', 'Connection', 'Health'], array_map(
            fn (array $computer): array => [$computer['name'], $computer['status'], $computer['monitoringType'], $computer['monitoringStatus'], $computer['detail']], $snapshot['data'],
        ));

        return $snapshot['meta']['status'] === 'live' ? self::SUCCESS : self::FAILURE;
    }
}
