<?php

namespace App\Services\Zabbix;

class ComputerMetrics
{
    /**
     * @param  array<string, mixed>  $host
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function normalize(array $host, array $items, string $siteName): array
    {
        $ping = $this->number($items['icmpping'] ?? null, 1);
        $pingReachable = match ($ping) {
            1.0 => true,
            0.0 => false,
            default => null,
        };
        $snmp = $this->number($items['zabbix[host,snmp,available]'] ?? null, 2);
        $snmpStatus = match ($snmp) {
            1.0 => 'Available',
            0.0 => 'Unavailable',
            default => 'Unknown',
        };
        $isActiveAgent = isset($items['zabbix[host,active_agent,available]']);
        $isAgent = $isActiveAgent || isset($items['zabbix[host,agent,available]']) || isset($items['agent.ping']);
        $agentAvailabilityKey = $isActiveAgent ? 'zabbix[host,active_agent,available]' : 'zabbix[host,agent,available]';
        $agentAvailability = $this->number($items[$agentAvailabilityKey] ?? null, 2);
        $agentStatus = match ($agentAvailability) {
            1.0 => 'Available',
            $isActiveAgent ? 2.0 : 0.0 => 'Unavailable',
            default => 'Unknown',
        };
        if ($isAgent && ! isset($items[$agentAvailabilityKey])) {
            $agentStatus = $this->number($items['agent.ping'] ?? null, 1) === 1.0 ? 'Available' : 'Unknown';
        }
        $monitoringType = $isAgent ? ($isActiveAgent ? 'Zabbix agent (active)' : 'Zabbix agent') : 'SNMP';
        $monitoringStatus = $isAgent ? $agentStatus : $snmpStatus;
        $monitoringItem = $isAgent ? ($items[$agentAvailabilityKey] ?? $items['agent.ping'] ?? null) : ($items['zabbix[host,snmp,available]'] ?? null);
        $reachable = $isAgent && $monitoringStatus === 'Available' ? true : $pingReachable;
        $cpu = $this->number($items['system.cpu.util'] ?? null, 100);
        $latency = $this->number($items['icmppingsec'] ?? null);
        $loss = $this->number($items['icmppingloss'] ?? null, 100);
        $memory = $isAgent ? $this->agentMemory($items) : $this->storage($items, true);
        $disks = $isAgent ? $this->agentDisks($items) : $this->storage($items, false);
        $warnings = [];
        $thresholds = config('zabbix.computer_thresholds');
        if ($monitoringStatus === 'Unavailable') {
            $warnings[] = ($isAgent ? 'Zabbix agent' : 'SNMP').' tidak dapat diakses';
        }
        if ($cpu !== null && $cpu >= $thresholds['cpu']) {
            $warnings[] = 'CPU tinggi';
        }
        foreach ($memory as $module) {
            if ($module['usedPercent'] !== null && $module['usedPercent'] >= $thresholds['memory']) {
                $warnings[] = $module['name'].' hampir penuh';
            }
        }
        foreach ($disks as $disk) {
            if ($disk['usedPercent'] !== null && $disk['usedPercent'] >= $thresholds['disk']) {
                $warnings[] = $disk['name'].' hampir penuh';
            }
        }
        if ($latency !== null && $latency * 1000 >= config('zabbix.latency_warning_ms')) {
            $warnings[] = 'Latency tinggi';
        }
        if ($loss !== null && $loss >= config('zabbix.loss_warning_percent')) {
            $warnings[] = 'Packet loss tinggi';
        }
        $complete = $monitoringStatus === 'Available' && $cpu !== null && $memory !== [] && $disks !== []
            && collect([...$memory, ...$disks])->every(fn (array $storage): bool => $storage['usedPercent'] !== null);
        $status = 'Unknown';
        if ($reachable === false) {
            $status = 'Offline';
        } elseif ($reachable === true || $monitoringStatus === 'Unavailable') {
            $status = $warnings !== [] ? 'Warning' : ($complete ? 'Online' : 'Unknown');
        }
        $interface = collect($host['interfaces'] ?? [])->first(fn (array $interface): bool => (string) ($interface['type'] ?? '') === ($isAgent ? '1' : '2') && (string) ($interface['main'] ?? '') === '1')
            ?? collect($host['interfaces'] ?? [])->firstWhere('main', '1') ?? [];
        $cpuSummary = $cpu === null ? 'Unknown' : round($cpu, 1).'%';
        $memorySummary = $memory[0]['usedPercent'] ?? null;

        return [
            'id' => (string) $host['hostid'],
            'name' => $host['name'],
            'location' => ($host['inventory']['location'] ?? '') ?: ($this->text($items['system.location[sysLocation.0]'] ?? null) ?: $siteName),
            'ip' => (string) ($interface['useip'] ?? '1') === '1' ? ($interface['ip'] ?? '—') : ($interface['dns'] ?? '—'),
            'status' => $status,
            'isReachable' => $reachable,
            'pingReachable' => $pingReachable,
            'pingLastCheckedAt' => $this->checkedAt($items['icmpping'] ?? null),
            'snmpStatus' => $snmpStatus,
            'agentStatus' => $agentStatus,
            'monitoringType' => $monitoringType,
            'monitoringStatus' => $monitoringStatus,
            'cpuPercent' => $cpu,
            'cpuLastCheckedAt' => $this->checkedAt($items['system.cpu.util'] ?? null),
            'latencyMs' => $latency === null ? null : round($latency * 1000, 3),
            'packetLossPercent' => $loss,
            'systemName' => $isAgent ? ($this->text($items['system.hostname'] ?? null) ?: $this->text($items['agent.hostname'] ?? null)) : $this->text($items['system.name'] ?? null),
            'systemDescription' => $this->text($items[$isAgent ? 'system.uname' : 'system.descr[sysDescr.0]'] ?? null),
            'uptimeSeconds' => $this->number($items[$isAgent ? 'system.uptime' : 'system.hw.uptime[hrSystemUptime.0]'] ?? null),
            'networkUptimeSeconds' => $isAgent ? null : $this->number($items['system.net.uptime[sysUpTime.0]'] ?? null),
            'memory' => $memory,
            'disks' => $disks,
            'interfaces' => $this->network($items),
            'warnings' => $warnings,
            'lastCheckedAt' => $this->checkedAt($monitoringItem),
            'detail' => 'CPU '.$cpuSummary.' · RAM '.($memorySummary === null ? 'Unknown' : round($memorySummary, 1).'%').' · '.$monitoringType.' '.$monitoringStatus,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return list<array{index: string, name: string, usedBytes: ?float, totalBytes: ?float, usedPercent: ?float, lastCheckedAt: ?string}>
     */
    private function agentMemory(array $items): array
    {
        if (! isset($items['vm.memory.util']) && ! isset($items['vm.memory.size[total]']) && ! isset($items['vm.memory.size[used]'])) {
            return [];
        }

        return [$this->storageEntry('physical', 'Physical Memory',
            $this->number($items['vm.memory.size[used]'] ?? null),
            $this->number($items['vm.memory.size[total]'] ?? null),
            $this->number($items['vm.memory.util'] ?? null, 100),
            $this->checkedAt($items['vm.memory.util'] ?? $items['vm.memory.size[used]'] ?? null),
        )];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return list<array{index: string, name: string, usedBytes: ?float, totalBytes: ?float, usedPercent: ?float, lastCheckedAt: ?string}>
     */
    private function agentDisks(array $items): array
    {
        $drives = [];
        foreach (array_keys($items) as $key) {
            if (preg_match('/^vfs\.fs\.dependent(?:\.size)?\[([^,\]]+),(?:data|used|total|pused|free)\]$/', $key, $matches)) {
                $drives[$matches[1]] = $matches[1];
            }
        }
        ksort($drives, SORT_NATURAL);
        $disks = [];
        foreach ($drives as $drive) {
            $dataItem = $items['vfs.fs.dependent['.$drive.',data]'] ?? null;
            $data = json_decode($this->text($dataItem) ?? '', true);
            $data = is_array($data) ? $data : [];
            $values = [];
            $metricItems = [];
            foreach (['used', 'total', 'pused'] as $metric) {
                $maximum = $metric === 'pused' ? 100 : PHP_FLOAT_MAX;
                $metricItem = $items['vfs.fs.dependent.size['.$drive.','.$metric.']'] ?? null;
                $value = $this->number($metricItem, $maximum);
                if ($value === null) {
                    $metricItem = $dataItem === null ? null : array_replace($dataItem, ['lastvalue' => $data['bytes'][$metric] ?? null]);
                    $value = $this->number($metricItem, $maximum);
                }
                $values[$metric] = $value;
                $metricItems[$metric] = $value === null ? null : $metricItem;
            }
            $disks[] = $this->storageEntry($drive, $drive, $values['used'], $values['total'], $values['pused'],
                $this->checkedAt($metricItems['pused'] ?? $metricItems['used'] ?? null),
            );
        }

        return $disks;
    }

    /** @return array{index: string, name: string, usedBytes: ?float, totalBytes: ?float, usedPercent: ?float, lastCheckedAt: ?string} */
    private function storageEntry(string $index, string $name, ?float $used, ?float $total, ?float $percent, ?string $checkedAt): array
    {
        if ($percent === null && $used !== null && $total !== null && $total > 0 && $used <= $total) {
            $percent = 100 * $used / $total;
        }

        return ['index' => $index, 'name' => $name, 'usedBytes' => $used, 'totalBytes' => $total, 'usedPercent' => $percent, 'lastCheckedAt' => $checkedAt];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return list<array{index: string, name: string, usedBytes: ?float, totalBytes: ?float, usedPercent: ?float, lastCheckedAt: ?string}>
     */
    private function storage(array $items, bool $memory): array
    {
        $pattern = $memory ? '/^vm\.memory\.(?:data|util|used|walk\.data\.total)\[(?:hrStorage(?:Size|Used)\.)?(\d+)\]$/' : '/^vfs\.fs\.(?:pused|used|total|walk\.data)\[(?:hrStorage(?:Size|Used)?\.)?(\d+)\]$/';
        $indices = [];
        foreach (array_keys($items) as $key) {
            if (preg_match($pattern, $key, $matches)) {
                $indices[$matches[1]] = $matches[1];
            }
        }
        ksort($indices, SORT_NATURAL);
        $storage = [];
        foreach ($indices as $index) {
            $dataKey = $memory ? 'vm.memory.data['.$index.']' : 'vfs.fs.walk.data[hrStorage.'.$index.']';
            $data = json_decode($this->text($items[$dataKey] ?? null) ?? '', true);
            $data = is_array($data) ? $data : [];
            if ($memory && isset($data['hrStorageType']) && $data['hrStorageType'] !== '.1.3.6.1.2.1.25.2.1.2') {
                continue;
            }
            $percentKey = ($memory ? 'vm.memory.util[' : 'vfs.fs.pused[').$index.']';
            $totalKey = ($memory ? 'vm.memory.walk.data.total[hrStorageSize.' : 'vfs.fs.total[hrStorageSize.').$index.']';
            $usedKey = ($memory ? 'vm.memory.used[hrStorageUsed.' : 'vfs.fs.used[hrStorageUsed.').$index.']';
            $total = $this->number($items[$totalKey] ?? null);
            $used = $this->number($items[$usedKey] ?? null);
            $percent = $this->number($items[$percentKey] ?? null, 100);
            if ($percent === null && $used !== null && $total !== null && $total > 0 && $used <= $total) {
                $percent = 100 * $used / $total;
            }
            $name = $data['hrStorageDescr'] ?? ($memory ? 'Memory #'.$index : 'Disk #'.$index);
            if (! $memory && preg_match('/^([A-Za-z]:)/', $name, $drive)) {
                $name = $drive[1];
            }
            $storage[] = ['index' => (string) $index, 'name' => $name, 'usedBytes' => $used, 'totalBytes' => $total, 'usedPercent' => $percent,
                'lastCheckedAt' => $this->checkedAt($items[$percentKey] ?? $items[$usedKey] ?? null),
            ];
        }

        return $storage;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return list<array{index: string, name: string, source: string, status: string, inBps: ?float, outBps: ?float, speedBps: ?float}>
     */
    private function network(array $items): array
    {
        $groups = [];
        foreach ($items as $key => $item) {
            if (preg_match('/^net\.if\.(in|out|speed|status)\[[^\]]+\.(\d+)\]$/', $key, $matches)) {
                $groups[$matches[2]][$matches[1]] = $item;
                $groups[$matches[2]]['source'] = 'SNMP';
            } elseif (preg_match('/^net\.if\.(in|out|speed|status)\["(\{[A-Fa-f0-9-]+\})"\]$/', $key, $matches)) {
                $groups[$matches[2]][$matches[1]] = $item;
                $groups[$matches[2]]['source'] = 'Zabbix agent';
            }
        }
        ksort($groups, SORT_NATURAL);
        $interfaces = [];
        foreach ($groups as $index => $group) {
            $item = $group['in'] ?? $group['status'] ?? $group['out'] ?? $group['speed'];
            $name = collect($item['tags'] ?? [])->firstWhere('tag', 'interface')['value'] ?? 'Interface #'.$index;
            $isAgent = $group['source'] === 'Zabbix agent';
            $status = $this->number($group['status'] ?? null, $isAgent ? 12 : 7);
            $interfaces[] = ['index' => (string) $index, 'name' => $name, 'source' => $group['source'],
                'status' => $isAgent ? match ($status) {
                    2.0 => 'Up', 0.0, 7.0 => 'Down', null => 'Unknown', default => 'Other'
                } : match ($status) {
                    1.0 => 'Up', 2.0 => 'Down', null => 'Unknown', default => 'Other'
                },
                'inBps' => $this->bitrate($group['in'] ?? null), 'outBps' => $this->bitrate($group['out'] ?? null), 'speedBps' => $this->bitrate($group['speed'] ?? null),
            ];
        }

        return $interfaces;
    }

    /** @param array<string, mixed>|null $item */
    private function bitrate(?array $item): ?float
    {
        $value = $this->number($item);

        return $value === null ? null : $value * (($item['units'] ?? '') === 'Bps' ? 8 : 1);
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
