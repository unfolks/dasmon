<?php

namespace App\Services\Zabbix;

use App\Exceptions\ZabbixException;
use Illuminate\Support\Facades\Cache;

class AccessPointService
{
    public function __construct(private ZabbixClient $client) {}

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function snapshot(string $site, bool $refresh = false): array
    {
        $siteConfig = config('zabbix.sites.'.$site);
        $groupId = $siteConfig['access_point_group'] ?? null;
        if (! $groupId) {
            return $this->emptySnapshot('unconfigured', 'Grup Access Point untuk lokasi ini belum dikonfigurasi.');
        }
        $cacheKey = 'zabbix:access-points:'.hash('sha256', json_encode([
            config('zabbix.url'), config('zabbix.token'), $site, $groupId,
            config('zabbix.max_item_age'), config('zabbix.latency_warning_ms'), config('zabbix.loss_warning_percent'),
        ]));
        if (! $refresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        try {
            $groups = $this->client->call('hostgroup.get', [
                'groupids' => [(string) $groupId], 'output' => ['groupid', 'name'],
            ]);
            if ($groups === []) {
                throw new ZabbixException('Grup Access Point tidak ditemukan atau tidak dapat diakses. Periksa ID grup dan izin Read pengguna pemilik token.');
            }
            $hosts = $this->client->call('host.get', [
                'groupids' => [(string) $groupId], 'monitored_hosts' => true,
                'output' => ['hostid', 'name'],
                'selectInterfaces' => ['ip', 'dns', 'useip', 'main'],
                'selectInventory' => ['location'], 'sortfield' => 'name',
            ]);
            $items = $hosts === [] ? [] : $this->client->call('item.get', [
                'hostids' => array_column($hosts, 'hostid'), 'monitored' => true,
                'filter' => ['key_' => ['icmpping', 'icmppingsec', 'icmppingloss']],
                'output' => ['itemid', 'hostid', 'key_', 'lastvalue', 'lastclock', 'state'],
            ]);
            $itemsByHost = [];
            foreach ($items as $item) {
                $itemsByHost[$item['hostid']][$item['key_']] = $item;
            }
            $snapshot = [
                'data' => array_map(fn (array $host): array => $this->normalize($host, $itemsByHost[$host['hostid']] ?? [], $siteConfig['name']), $hosts),
                'meta' => ['source' => 'zabbix', 'status' => 'live', 'updatedAt' => now()->toIso8601String(), 'message' => $hosts === [] ? 'Tidak ada host Access Point aktif di grup ini.' : null],
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
        return ['data' => [], 'meta' => ['source' => 'zabbix', 'status' => $status, 'updatedAt' => null, 'message' => $message]];
    }

    /**
     * @param  array<string, mixed>  $host
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function normalize(array $host, array $items, string $siteName): array
    {
        $ping = $this->metric($items['icmpping'] ?? null, 0, 1);
        $latencySeconds = $this->metric($items['icmppingsec'] ?? null, 0);
        $latency = $latencySeconds === null ? null : round($latencySeconds * 1000, 3);
        $loss = $this->metric($items['icmppingloss'] ?? null, 0, 100);
        $reachable = match ($ping) {
            1.0 => true,
            0.0 => false,
            default => null,
        };
        $health = 'Unknown';
        $status = 'Unknown';
        if ($reachable === false) {
            $health = 'Loss';
            $status = 'Offline';
        } elseif ($reachable === true && $latency !== null && $loss !== null) {
            if ($loss >= config('zabbix.loss_warning_percent')) {
                $health = 'Loss';
            } elseif ($latency >= config('zabbix.latency_warning_ms')) {
                $health = 'Low';
            } else {
                $health = 'Normal';
            }
            $status = $health === 'Normal' ? 'Online' : 'Warning';
        }
        $interface = collect($host['interfaces'] ?? [])->firstWhere('main', '1') ?? ($host['interfaces'][0] ?? []);
        $clock = (int) ($items['icmpping']['lastclock'] ?? 0);

        return [
            'id' => (string) $host['hostid'],
            'name' => $host['name'],
            'location' => ($host['inventory']['location'] ?? '') ?: $siteName,
            'ip' => ($interface['useip'] ?? '1') === '1' ? ($interface['ip'] ?? '—') : ($interface['dns'] ?? '—'),
            'status' => $status,
            'health' => $health,
            'isReachable' => $reachable,
            'latencyMs' => $latency,
            'packetLossPercent' => $loss,
            'lastCheckedAt' => $clock > 0 ? date(DATE_ATOM, $clock) : null,
            'detail' => $status === 'Unknown' ? 'Data ICMP belum lengkap, tidak didukung, atau sudah kedaluwarsa.' : $health.' · '.($latency === null ? '—' : $latency.' ms').' · Loss '.($loss === null ? '—' : $loss.'%'),
        ];
    }

    /** @param array<string, mixed>|null $item */
    private function metric(?array $item, float $minimum, float $maximum = PHP_FLOAT_MAX): ?float
    {
        if ($item === null || (string) ($item['state'] ?? '1') !== '0' || ! is_numeric($item['lastvalue'] ?? null)) {
            return null;
        }
        $age = now()->timestamp - (int) ($item['lastclock'] ?? 0);
        $value = (float) $item['lastvalue'];
        if ($age < -60 || $age > config('zabbix.max_item_age') || ! is_finite($value) || $value < $minimum || $value > $maximum) {
            return null;
        }

        return $value;
    }
}
