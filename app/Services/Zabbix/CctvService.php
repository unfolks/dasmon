<?php

namespace App\Services\Zabbix;

use App\Exceptions\ZabbixException;
use Illuminate\Support\Facades\Cache;

class CctvService
{
    public function __construct(private ZabbixClient $client) {}

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function snapshot(string $site, bool $refresh = false): array
    {
        $siteConfig = config('zabbix.sites.'.$site);
        $configuredGroups = $siteConfig['cctv_group'] ?? [];
        $groupIds = collect(is_array($configuredGroups) ? $configuredGroups : explode(',', (string) $configuredGroups))
            ->map(fn (string|int $groupId): string => trim((string) $groupId))
            ->filter(fn (string $groupId): bool => $groupId !== '')
            ->unique()->values()->all();
        if ($groupIds === []) {
            return $this->emptySnapshot('unconfigured', 'Grup CCTV untuk lokasi ini belum dikonfigurasi.');
        }
        $cacheKey = 'zabbix:cctv:v2:'.hash('sha256', json_encode([
            config('zabbix.url'), config('zabbix.token'), $site, $groupIds, config('zabbix.max_item_age'),
        ]));
        if (! $refresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        try {
            $groups = $this->client->call('hostgroup.get', [
                'groupids' => $groupIds, 'output' => ['groupid', 'name'],
            ]);
            if (array_diff($groupIds, array_column($groups, 'groupid')) !== []) {
                throw new ZabbixException('Sebagian atau seluruh grup CCTV tidak ditemukan atau tidak dapat diakses. Periksa ID grup dan izin Read pengguna pemilik token.');
            }
            $groupsById = array_column($groups, null, 'groupid');
            $switchGroups = [];
            foreach ($groupIds as $groupId) {
                $switchGroups[$groupId] = ['id' => $groupId, 'name' => $groupsById[$groupId]['name']];
            }
            $hosts = $this->client->call('host.get', [
                'groupids' => $groupIds, 'monitored_hosts' => true,
                'output' => ['hostid', 'name'],
                'selectHostGroups' => ['groupid', 'name'],
                'selectInterfaces' => ['ip', 'dns', 'useip', 'main'],
                'selectInventory' => ['location'], 'sortfield' => 'name',
            ]);
            $hosts = collect($hosts)->unique('hostid')->values()->all();
            $items = $hosts === [] ? [] : $this->client->call('item.get', [
                'hostids' => array_column($hosts, 'hostid'), 'monitored' => true,
                'filter' => ['key_' => ['icmpping', 'icmppingsec', 'icmppingloss']],
                'output' => ['itemid', 'hostid', 'key_', 'lastvalue', 'lastclock', 'state', 'error'],
            ]);
            $itemsByHost = [];
            foreach ($items as $item) {
                $itemsByHost[$item['hostid']][$item['key_']] = $item;
            }
            $snapshot = [
                'data' => array_map(fn (array $host): array => $this->normalize($host, $itemsByHost[$host['hostid']] ?? [], $switchGroups), $hosts),
                'meta' => ['source' => 'zabbix', 'status' => 'live', 'updatedAt' => now()->toIso8601String(), 'switchGroups' => array_values($switchGroups), 'message' => $hosts === [] ? 'Tidak ada host kamera aktif di grup CCTV ini.' : null],
            ];
            Cache::put($cacheKey.':last-good', $snapshot, now()->addDay());
        } catch (ZabbixException $exception) {
            $snapshot = Cache::get($cacheKey.':last-good') ?? $this->emptySnapshot('unavailable', $exception->getMessage());
            if ($snapshot['meta']['status'] === 'live') {
                $snapshot['meta']['status'] = 'stale';
                $snapshot['meta']['message'] = $exception->getMessage().' Menampilkan daftar kamera terakhir; status belum terverifikasi ulang.';
                $snapshot['data'] = array_map(fn (array $camera): array => array_replace($camera, [
                    'status' => 'Unknown', 'isReachable' => null, 'latencyMs' => null,
                    'packetLossPercent' => null, 'detail' => 'Koneksi monitoring bermasalah; status kamera belum terverifikasi ulang.',
                ]), $snapshot['data']);
            }
        }
        Cache::put($cacheKey, $snapshot, config('zabbix.cache_seconds'));

        return $snapshot;
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    private function emptySnapshot(string $status, string $message): array
    {
        return ['data' => [], 'meta' => ['source' => 'zabbix', 'status' => $status, 'updatedAt' => null, 'switchGroups' => [], 'message' => $message]];
    }

    /**
     * @param  array<string, mixed>  $host
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string|int, array{id: string, name: string}>  $switchGroups
     * @return array<string, mixed>
     */
    private function normalize(array $host, array $items, array $switchGroups): array
    {
        $ping = $this->metric($items['icmpping'] ?? null, 0, 1);
        $latencySeconds = $this->metric($items['icmppingsec'] ?? null, 0);
        $reachable = match ($ping) {
            1.0 => true,
            0.0 => false,
            default => null,
        };
        $status = match ($reachable) {
            true => 'Online',
            false => 'Offline',
            null => 'Unknown',
        };
        $interface = collect($host['interfaces'] ?? [])->firstWhere('main', '1') ?? ($host['interfaces'][0] ?? []);
        $clock = (int) ($items['icmpping']['lastclock'] ?? 0);
        $hostGroupIds = array_map(strval(...), array_column($host['hostgroups'] ?? [], 'groupid'));
        if ($hostGroupIds === [] && count($switchGroups) === 1) {
            $hostGroupIds = array_column($switchGroups, 'id');
        }

        return [
            'id' => (string) $host['hostid'],
            'name' => $host['name'],
            'switchGroups' => array_values(array_filter($switchGroups, fn (array $group): bool => in_array($group['id'], $hostGroupIds, true))),
            'location' => ($host['inventory']['location'] ?? '') ?: 'Unknown',
            'ip' => ($interface['useip'] ?? '1') === '1' ? (($interface['ip'] ?? '') ?: 'Unknown') : (($interface['dns'] ?? '') ?: 'Unknown'),
            'status' => $status,
            'isReachable' => $reachable,
            'latencyMs' => $latencySeconds === null ? null : round($latencySeconds * 1000, 3),
            'packetLossPercent' => $this->metric($items['icmppingloss'] ?? null, 0, 100),
            'lastCheckedAt' => $clock > 0 && $clock <= now()->timestamp + 60 ? date(DATE_ATOM, $clock) : null,
            'detail' => match ($reachable) {
                true => 'Kamera merespons ping.',
                false => 'Kamera tidak merespons ping. Periksa jaringan dan daya perangkat.',
                null => 'Data ping belum tersedia, tidak didukung, tidak valid, atau sudah kedaluwarsa.',
            },
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
