<?php

namespace App\Services\Zabbix;

use App\Exceptions\ZabbixException;
use Illuminate\Support\Facades\Cache;

class NasService
{
    public function __construct(private ZabbixClient $client, private NasMetrics $metrics) {}

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function snapshot(string $site, bool $refresh = false): array
    {
        $siteConfig = config('zabbix.sites.'.$site);
        $groupId = $siteConfig['nas_group'] ?? null;
        if (! $groupId) {
            return $this->emptySnapshot('unconfigured', 'Grup NAS untuk lokasi ini belum dikonfigurasi.');
        }
        $cacheKey = 'zabbix:nas:'.hash('sha256', json_encode([
            config('zabbix.url'), config('zabbix.token'), $site, $groupId,
            config('zabbix.nas_thresholds'), config('zabbix.max_item_age'),
        ]));
        if (! $refresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        try {
            $groups = $this->client->call('hostgroup.get', ['groupids' => [(string) $groupId], 'output' => ['groupid', 'name']]);
            if ($groups === []) {
                throw new ZabbixException('Grup NAS tidak ditemukan atau tidak dapat diakses. Periksa ID grup dan izin Read pengguna pemilik token.');
            }
            $hosts = $this->client->call('host.get', [
                'groupids' => [(string) $groupId], 'monitored_hosts' => true,
                'output' => ['hostid', 'name'], 'sortfield' => 'name',
                'selectInterfaces' => ['ip', 'dns', 'useip', 'main', 'type', 'available'],
                'selectInventory' => ['location'],
            ]);
            $items = $hosts === [] ? [] : $this->client->call('item.get', [
                'hostids' => array_column($hosts, 'hostid'), 'monitored' => true,
                'search' => ['key_' => ['icmpping', 'synoSystem.*', 'ucdavis.memory.*', 'host.hrStorage.hrStorageTable.hrStorageEntry.*', 'synoDisk.*', 'synoRaid.*', 'synologyDiskSMART.*']],
                'searchByAny' => true, 'searchWildcardsEnabled' => true,
                'output' => ['itemid', 'hostid', 'key_', 'name', 'units', 'lastvalue', 'lastclock', 'state', 'status', 'delay'],
                'selectPreprocessing' => ['type', 'params'],
                'selectValueMap' => ['mappings'],
            ]);
            $itemsByHost = [];
            foreach ($items as $item) {
                $itemsByHost[$item['hostid']][$item['key_']] = $item;
            }
            $snapshot = [
                'data' => array_map(fn (array $host): array => $this->metrics->normalize($host, $itemsByHost[$host['hostid']] ?? [], $siteConfig['name']), $hosts),
                'meta' => ['source' => 'zabbix', 'status' => 'live', 'updatedAt' => now()->toIso8601String(), 'message' => $hosts === [] ? 'Tidak ada host NAS aktif di grup ini.' : null, 'thresholds' => config('zabbix.nas_thresholds')],
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
        return ['data' => [], 'meta' => ['source' => 'zabbix', 'status' => $status, 'updatedAt' => null, 'message' => $message, 'thresholds' => config('zabbix.nas_thresholds')]];
    }
}
