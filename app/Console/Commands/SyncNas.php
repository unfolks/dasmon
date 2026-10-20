<?php

namespace App\Console\Commands;

use App\Services\Zabbix\NasService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zabbix:nas {site=krian}')]
#[Description('Fetch and cache the current Zabbix NAS snapshot')]
class SyncNas extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NasService $service): int
    {
        $site = (string) $this->argument('site');
        if (! array_key_exists($site, config('zabbix.sites'))) {
            $this->error('Lokasi tidak dikenal.');

            return self::FAILURE;
        }
        $snapshot = $service->snapshot($site, refresh: true);
        $this->info('Status: '.$snapshot['meta']['status'].' | NAS: '.count($snapshot['data']));
        if ($snapshot['meta']['message']) {
            $this->warn($snapshot['meta']['message']);
        }
        $this->table(['NAS', 'Status', 'SNMP', 'Health'], array_map(
            fn (array $nas): array => [$nas['name'], $nas['status'], $nas['snmpStatus'], $nas['detail']], $snapshot['data'],
        ));

        return $snapshot['meta']['status'] === 'live' ? self::SUCCESS : self::FAILURE;
    }
}
