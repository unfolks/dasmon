<?php

namespace App\Services\Zabbix;

use App\Exceptions\ZabbixException;
use Illuminate\Support\Facades\Cache;

class ComputerService
{
    public function __construct(private ZabbixClient $client, private ComputerMetrics $metrics) {}

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function snapshot(string $site, bool $refresh = false): array
    {
        $siteConfig = config('zabbix.sites.'.$site);
        $groupId = $siteConfig['computer_group'] ?? null;
        if (! $groupId) {
            return $this->emptySnapshot('unconfigured', 'Grup Computers untuk lokasi ini belum dikonfigurasi.');
        }
        $cacheKey = 'zabbix:computers:'.hash('sha256', json_encode([
            'schema' => 'computer-agent-v1',
            config('zabbix.url'), config('zabbix.token'), $site, $groupId,
            config('zabbix.computer_thresholds'), config('zabbix.max_item_age'),
            config('zabbix.latency_warning_ms'), config('zabbix.loss_warning_percent'),
        ]));
        if (! $refresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        try {
            $groups = $this->client->call('hostgroup.get', ['groupids' => [(string) $groupId], 'output' => ['groupid', 'name']]);
            if ($groups === []) {
                throw new ZabbixException('Grup Computers tidak ditemukan atau tidak dapat diakses. Periksa ID grup dan izin Read pengguna pemilik token.');
            }
            $hosts = $this->client->call('host.get', [
                'groupids' => [(string) $groupId], 'monitored_hosts' => true,
                'output' => ['hostid', 'name'], 'sortfield' => 'name',
                'selectInterfaces' => ['ip', 'dns', 'useip', 'main', 'type'],
                'selectInventory' => ['location', 'os'],
            ]);
            $items = $hosts === [] ? [] : $this->client->call('item.get', [
                'hostids' => array_column($hosts, 'hostid'), 'monitored' => true,
                'search' => ['key_' => ['icmpping*', 'agent.ping', 'agent.hostname', 'system.hostname', 'system.uname', 'system.uptime', 'system.cpu.util', 'system.name', 'system.descr[*]', 'system.location[*]', 'system.hw.uptime[*]', 'system.net.uptime[*]', 'zabbix[host,active_agent,available]', 'zabbix[host,agent,available]', 'zabbix[host,snmp,available]', 'vm.memory.*', 'vfs.fs.*', 'net.if.in[*]', 'net.if.out[*]', 'net.if.speed[*]', 'net.if.status[*]']],
                'searchByAny' => true, 'searchWildcardsEnabled' => true,
                'output' => ['itemid', 'hostid', 'key_', 'name', 'units', 'lastvalue', 'lastclock', 'state', 'status', 'delay'],
                'selectPreprocessing' => ['type', 'params'],
                'selectTags' => ['tag', 'value'],
            ]);
            $itemsByHost = [];
            foreach ($items as $item) {
                $itemsByHost[$item['hostid']][$item['key_']] = $item;
            }
            $snapshot = [
                'data' => array_map(fn (array $host): array => $this->metrics->normalize($host, $itemsByHost[$host['hostid']] ?? [], $siteConfig['name']), $hosts),
                'meta' => ['source' => 'zabbix', 'status' => 'live', 'updatedAt' => now()->toIso8601String(), 'message' => $hosts === [] ? 'Tidak ada host Computer aktif di grup ini.' : null, 'thresholds' => config('zabbix.computer_thresholds')],
            ];
            Cache::put($cacheKey.':last-good', $snapshot, now()->addDay());
        } catch (ZabbixException $exception) {
            $snapshot = Cache::get($cacheKey.':last-good') ?? $this->emptySnapshot('unavailable', $exception->getMessage());
            if ($snapshot['meta']['status'] === 'live') {
                $snapshot['meta']['status'] = 'stale';
                $snapshot['meta']['message'] = $exception->getMessage().' Menampilkan snapshot terakhir; status perangkat belum terverifikasi ulang.';
            }
        }
        Cache::put($cacheKey, $snapshot, config('zabbix.cache_seconds'));

        return $snapshot;
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    private function emptySnapshot(string $status, string $message): array
    {
        return ['data' => [], 'meta' => ['source' => 'zabbix', 'status' => $status, 'updatedAt' => null, 'message' => $message, 'thresholds' => config('zabbix.computer_thresholds')]];
    }
}
