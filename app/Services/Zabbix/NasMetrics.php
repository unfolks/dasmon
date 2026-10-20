<?php

namespace App\Services\Zabbix;

class NasMetrics
{
    /**
     * @param  array<string, mixed>  $host
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function normalize(array $host, array $items, string $siteName): array
    {
        $interface = collect($host['interfaces'] ?? [])->first(fn (array $entry): bool => (string) ($entry['type'] ?? '') === '2' && (string) ($entry['main'] ?? '') === '1') ?? [];
        $snmp = match ((string) ($interface['available'] ?? '0')) {
            '1' => 'Available', '2' => 'Unavailable', default => 'Unknown',
        };
        $reachable = match ($this->number($items['icmpping'] ?? null, 1)) {
            1.0 => true, 0.0 => false, default => null,
        };
        $checks = [];
        foreach (['systemStatus' => 'System', 'powerStatus' => 'Power', 'cpuFanStatus' => 'CPU fan', 'systemFanStatus' => 'System fan'] as $key => $label) {
            $checks[] = ['name' => $label, 'status' => $this->mappedStatus($items['synoSystem.'.$key] ?? null), 'lastCheckedAt' => $this->checkedAt($items['synoSystem.'.$key] ?? null)];
        }
        $volumes = $this->volumes($items);
        $disks = $this->disks($items);
        $raids = $this->raids($items);
        $smart = $this->smart($items);
        $idle = $this->number($items['synoSystem.ssCpuIdle'] ?? null, 100);
        $cpu = $idle === null ? null : 100 - $idle;
        $temperature = $this->number($items['synoSystem.temperature'] ?? null, 150);
        $warnings = [];
        foreach ([...$checks, ...$raids, ...$disks] as $check) {
            if (! in_array($check['status'], ['Normal', 'Unknown'], true)) {
                $warnings[] = $check['name'].': '.$check['status'];
            }
        }
        foreach ($disks as $disk) {
            if ($disk['badSectors'] !== null && $disk['badSectors'] > 0) {
                $warnings[] = $disk['name'].': bad sector '.$disk['badSectors'];
            }
            if ($disk['temperature'] !== null && $disk['temperature'] >= config('zabbix.nas_thresholds.disk_temperature')) {
                $warnings[] = $disk['name'].': suhu tinggi';
            }
        }
        foreach ($volumes as $volume) {
            if ($volume['usedPercent'] !== null && $volume['usedPercent'] >= config('zabbix.nas_thresholds.storage')) {
                $warnings[] = $volume['name'].' hampir penuh';
            }
        }
        if ($smart['warning'] > 0) {
            $warnings[] = 'SMART: '.$smart['warning'].' item membutuhkan perhatian';
        }
        if ($cpu !== null && $cpu >= config('zabbix.nas_thresholds.cpu')) {
            $warnings[] = 'CPU tinggi';
        }
        if ($temperature !== null && $temperature >= config('zabbix.nas_thresholds.temperature')) {
            $warnings[] = 'Suhu NAS tinggi';
        }
        $completeHealth = $raids !== [] && $disks !== [] && $smart['total'] > 0 && $smart['unknown'] === 0
            && collect([...$checks, ...$raids, ...$disks])->every(fn (array $entry): bool => $entry['status'] === 'Normal')
            && collect($disks)->every(fn (array $entry): bool => $entry['badSectors'] !== null);
        $health = $warnings !== [] ? 'Warning' : ($completeHealth && $snmp === 'Available' ? 'Normal' : 'Unknown');
        if ($snmp === 'Unavailable') {
            $warnings[] = 'SNMP tidak dapat diakses; status ping terpisah';
        }
        $completeStorage = $volumes !== [] && collect($volumes)->every(fn (array $entry): bool => $entry['usedBytes'] !== null && $entry['totalBytes'] !== null && $entry['totalBytes'] > 0);
        $total = $completeStorage ? array_sum(array_column($volumes, 'totalBytes')) : null;
        $used = $completeStorage ? array_sum(array_column($volumes, 'usedBytes')) : null;
        $storageUsage = $total !== null && $total > 0 ? round($used / $total * 100, 2) : null;
        $status = $reachable === false ? 'Offline' : ($warnings !== [] ? 'Warning' : ($snmp === 'Available' && $health === 'Normal' && $completeStorage ? 'Online' : 'Unknown'));

        return [
            'id' => (string) $host['hostid'], 'name' => $host['name'],
            'location' => ($host['inventory']['location'] ?? '') ?: $siteName,
            'ip' => (string) ($interface['useip'] ?? '1') === '1' ? ($interface['ip'] ?? '—') : ($interface['dns'] ?? '—'),
            'status' => $status, 'isReachable' => $reachable, 'snmpStatus' => $snmp,
            'health' => $health, 'backupStatus' => 'Belum tersedia',
            'model' => $this->text($items['synoSystem.modelName'] ?? null),
            'version' => $this->text($items['synoSystem.version'] ?? null),
            'cpuPercent' => $cpu, 'temperature' => $temperature,
            'memory' => [
                'totalBytes' => $this->bytes($items['ucdavis.memory.memTotalReal'] ?? null),
                'freeBytes' => $this->bytes($items['ucdavis.memory.memAvailReal'] ?? null),
                'cachedBytes' => $this->bytes($items['ucdavis.memory.memCached'] ?? null),
                'bufferBytes' => $this->bytes($items['ucdavis.memory.memBuffer'] ?? null),
            ],
            'storageUsage' => $storageUsage, 'totalBytes' => $total, 'usedBytes' => $used,
            'freeBytes' => $total === null ? null : $total - $used,
            'checks' => $checks, 'volumes' => $volumes, 'raids' => $raids, 'disks' => $disks, 'smart' => $smart,
            'warnings' => $warnings, 'lastCheckedAt' => $this->checkedAt($items['synoSystem.systemStatus'] ?? null),
            'detail' => 'Storage '.($storageUsage === null ? 'Unknown' : $storageUsage.'%').' · Health '.$health.' · Backup belum tersedia',
        ];
    }

    /** @param array<string, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function volumes(array $items): array
    {
        $groups = [];
        foreach ($items as $key => $item) {
            if (preg_match('/^host\.hrStorage\.hrStorageTable\.hrStorageEntry\.hrStorage(Size|Used)\[(\d+)(,pct)?\]$/', $key, $matches)) {
                $groups[$matches[2]][isset($matches[3]) ? 'percent' : $matches[1]] = $item;
            }
        }
        ksort($groups, SORT_NATURAL);
        $volumes = [];
        foreach ($groups as $index => $group) {
            $total = $this->bytes($group['Size'] ?? null);
            $used = $this->bytes($group['Used'] ?? null);
            if ($total !== null && $used !== null && $used > $total) {
                $used = null;
            }
            $percent = $total !== null && $total > 0 && $used !== null ? round($used / $total * 100, 2) : $this->number($group['percent'] ?? null, 100);
            $item = $group['Size'] ?? $group['Used'] ?? $group['percent'];
            $name = preg_replace('/^Storage (?:Size|Used) on | \(%\)$/', '', $item['name'] ?? '') ?: 'Volume #'.$index;
            $volumes[] = ['index' => (string) $index, 'name' => $name, 'totalBytes' => $total, 'usedBytes' => $used,
                'freeBytes' => $total !== null && $used !== null ? $total - $used : null, 'usedPercent' => $percent,
                'lastCheckedAt' => $this->checkedAt($group['Used'] ?? $group['percent'] ?? null)];
        }

        return $volumes;
    }

    /** @param array<string, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function disks(array $items): array
    {
        $groups = [];
        foreach ($items as $key => $item) {
            if (preg_match('/^synoDisk\.diskTable\.diskEntry\.disk(Model|Status|Temperature|Type|BadSector)\.\[(\d+)\]$/', $key, $matches)) {
                $groups[$matches[2]][$matches[1]] = $item;
            }
        }
        ksort($groups, SORT_NATURAL);
        $disks = [];
        foreach ($groups as $index => $group) {
            $item = $group['Status'] ?? $group['Model'] ?? reset($group);
            $name = preg_replace('/ (?:Status|Model|Temperature|Type|Bad sectors count)$/i', '', $item['name'] ?? '') ?: 'Disk SNMP #'.$index;
            $disks[] = ['index' => (string) $index, 'name' => $name, 'model' => $this->text($group['Model'] ?? null),
                'type' => $this->text($group['Type'] ?? null), 'status' => $this->mappedStatus($group['Status'] ?? null),
                'temperature' => $this->number($group['Temperature'] ?? null, 150), 'badSectors' => $this->number($group['BadSector'] ?? null),
                'lastCheckedAt' => $this->checkedAt($group['Status'] ?? null)];
        }
        usort($disks, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return $disks;
    }

    /** @param array<string, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function raids(array $items): array
    {
        $groups = [];
        foreach ($items as $key => $item) {
            if (preg_match('/^synoRaid\.raidTable\.raidEntry\.raid(Name|Status)\.\[(\d+)\]$/', $key, $matches)) {
                $groups[$matches[2]][$matches[1]] = $item;
            }
        }
        ksort($groups, SORT_NATURAL);
        $raids = [];
        foreach ($groups as $index => $group) {
            $raids[] = ['index' => (string) $index, 'name' => $this->text($group['Name'] ?? null) ?? 'RAID entry #'.$index,
                'status' => $this->mappedStatus($group['Status'] ?? null), 'lastCheckedAt' => $this->checkedAt($group['Status'] ?? null)];
        }

        return $raids;
    }

    /** @param array<string, array<string, mixed>> $items
     * @return array{total: int, ok: int, warning: int, unknown: int, entries: list<array<string, mixed>>}
     */
    private function smart(array $items): array
    {
        $summary = ['total' => 0, 'ok' => 0, 'warning' => 0, 'unknown' => 0, 'entries' => []];
        foreach ($items as $key => $item) {
            if (! str_starts_with($key, 'synologyDiskSMART.diskSMARTTable.diskSMARTEntry.')) {
                continue;
            }
            $value = $this->text($item);
            $status = $value === null || trim($value) === '' ? 'Unknown' : (strtoupper(trim($value)) === 'OK' ? 'OK' : 'Warning');
            $summary['total']++;
            $summary[match ($status) {
                'OK' => 'ok', 'Warning' => 'warning', default => 'unknown'
            }]++;
            $summary['entries'][] = ['id' => (string) $item['itemid'], 'name' => $item['name'] ?? $key, 'status' => $status,
                'value' => $value, 'lastCheckedAt' => $this->checkedAt($item)];
        }

        return $summary;
    }

    /** @param array<string, mixed>|null $item */
    private function mappedStatus(?array $item): string
    {
        $value = $this->number($item);
        if ($value === null) {
            return 'Unknown';
        }
        foreach ($item['valuemap']['mappings'] ?? [] as $mapping) {
            if ((string) ($mapping['type'] ?? '') === '0' && (string) $mapping['value'] === (string) $value) {
                return $mapping['newvalue'];
            }
        }

        return $value === 1.0 ? 'Normal' : 'Status '.$value;
    }

    /** @param array<string, mixed>|null $item */
    private function bytes(?array $item): ?float
    {
        return ($item['units'] ?? '') === 'B' ? $this->number($item) : null;
    }

    /** @param array<string, mixed>|null $item */
    private function number(?array $item, float $maximum = PHP_FLOAT_MAX): ?float
    {
        if (! $this->fresh($item) || ! is_numeric($item['lastvalue'] ?? null)) {
            return null;
        }
        $value = (float) $item['lastvalue'];

        return is_finite($value) && $value >= 0 && $value <= $maximum ? $value : null;
    }

    /** @param array<string, mixed>|null $item */
    private function text(?array $item): ?string
    {
        return $this->fresh($item) && is_string($item['lastvalue'] ?? null) ? $item['lastvalue'] : null;
    }

    /** @param array<string, mixed>|null $item */
    private function fresh(?array $item): bool
    {
        if ($item === null || (string) ($item['state'] ?? '1') !== '0' || (string) ($item['status'] ?? '0') !== '0' || (int) ($item['lastclock'] ?? 0) <= 0) {
            return false;
        }
        $delay = $this->seconds((string) ($item['delay'] ?? '0'));
        $maxAge = max((int) config('zabbix.max_item_age'), 3 * $delay);
        foreach ($item['preprocessing'] ?? [] as $step) {
            if ((string) $step['type'] === '20') {
                $maxAge = max($maxAge, $this->seconds($step['params']) + max(300, 3 * $delay));
            }
        }
        $age = now()->timestamp - (int) $item['lastclock'];

        return $age >= -60 && $age <= $maxAge;
    }

    private function seconds(string $interval): int
    {
        if (! preg_match('/^(\d+)([smhdw]?)$/', $interval, $matches)) {
            return 0;
        }

        return (int) $matches[1] * match ($matches[2]) {
            'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800, default => 1
        };
    }

    /** @param array<string, mixed>|null $item */
    private function checkedAt(?array $item): ?string
    {
        $clock = (int) ($item['lastclock'] ?? 0);

        return $clock > 0 ? date(DATE_ATOM, $clock) : null;
    }
}
