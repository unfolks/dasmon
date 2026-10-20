<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZabbixNasTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $overrides = [];

    private bool $unavailable = false;

    private string $snmpAvailable = '1';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'inertia.ssr.enabled' => false,
            'zabbix.url' => 'http://zabbix.test/api_jsonrpc.php', 'zabbix.token' => 'private-nas-token',
            'zabbix.sites.krian.nas_group' => '27', 'zabbix.sites.sidoarjo.nas_group' => null,
            'zabbix.nas_thresholds' => ['cpu' => 85, 'storage' => 85, 'temperature' => 60, 'disk_temperature' => 55],
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_nas_discovery_maps_real_disk_names_and_preprocessed_bytes_without_assuming_ping(): void
    {
        $this->fakeInventory();
        $response = $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.status', 'live')->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.snmpStatus', 'Available')->assertJsonPath('data.0.isReachable', null)
            ->assertJsonPath('data.0.health', 'Normal')->assertJsonPath('data.0.backupStatus', 'Belum tersedia')
            ->assertJsonPath('data.0.model', 'RS2421+')->assertJsonPath('data.0.cpuPercent', 1)
            ->assertJsonPath('data.0.totalBytes', 113997365510144)->assertJsonPath('data.0.usedBytes', 78039060971520)
            ->assertJsonPath('data.0.storageUsage', 68.46)->assertJsonPath('data.0.volumes.0.index', '55')
            ->assertJsonPath('data.0.volumes.0.name', '/volume1')->assertJsonPath('data.0.disks.0.index', '0')
            ->assertJsonPath('data.0.disks.0.name', 'Disk 11')->assertJsonPath('data.0.disks.0.badSectors', 0)
            ->assertJsonPath('data.0.smart.ok', 1)->assertJsonPath('data.0.memory.totalBytes', 4079534080);
        $this->assertStringNotContainsString('private-nas-token', $response->getContent());
        $this->assertStringNotContainsString('private-snmp-error', $response->getContent());
        $this->assertStringNotContainsString('preprocessing', $response->getContent());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && $request->hasHeader('Authorization', 'Bearer private-nas-token')
            && $request['params']['hostids'] === ['10705']
            && $request['params']['selectValueMap'] === ['mappings']
            && $request['params']['monitored'] === true);
    }

    public function test_storage_summary_weights_multiple_dynamic_volumes_by_capacity(): void
    {
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageSize[55]'] = ['lastvalue' => '1000'];
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[55]'] = ['lastvalue' => '100'];
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageSize[89]'] = ['lastvalue' => '3000', 'units' => 'B', 'name' => 'Storage Size on /volume2'];
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[89]'] = ['lastvalue' => '2400', 'units' => 'B', 'name' => 'Storage Used on /volume2'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonCount(2, 'data.0.volumes')
            ->assertJsonPath('data.0.storageUsage', 62.5)->assertJsonPath('data.0.freeBytes', 1500);
    }

    public function test_mapped_raid_failure_and_smart_failure_raise_health_warning(): void
    {
        $this->overrides['synoRaid.raidTable.raidEntry.raidStatus.[7]'] = ['lastvalue' => '11'];
        $this->overrides['synologyDiskSMART.diskSMARTTable.diskSMARTEntry.diskSMARTStatus.[27]'] = ['lastvalue' => 'FAIL'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.health', 'Warning')->assertJsonPath('data.0.raids.0.status', 'Degraded')
            ->assertJsonPath('data.0.smart.warning', 1)->assertJsonPath('data.0.backupStatus', 'Belum tersedia');
    }

    public function test_disk_and_capacity_thresholds_produce_actionable_warnings(): void
    {
        $this->overrides['synoDisk.diskTable.diskEntry.diskBadSector.[0]'] = ['lastvalue' => '3'];
        $this->overrides['synoDisk.diskTable.diskEntry.diskTemperature.[0]'] = ['lastvalue' => '55'];
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[55]'] = ['lastvalue' => '102597628959130'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.warnings', ['Disk 11: bad sector 3', 'Disk 11: suhu tinggi', '/volume1 hampir penuh']);
    }

    public function test_unavailable_snmp_does_not_invent_an_offline_ping_result(): void
    {
        $this->snmpAvailable = '2';
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.isReachable', null)->assertJsonPath('data.0.health', 'Unknown');
    }

    public function test_actual_ping_failure_can_mark_nas_offline(): void
    {
        $this->overrides['icmpping'] = ['lastvalue' => '0'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Offline')
            ->assertJsonPath('data.0.isReachable', false);
    }

    public function test_stale_unsupported_and_disabled_metrics_are_unknown_while_slow_metadata_is_retained(): void
    {
        $this->overrides['synoSystem.temperature'] = ['lastclock' => (string) now()->subMinutes(10)->timestamp];
        $this->overrides['synoSystem.ssCpuIdle'] = ['lastclock' => '0', 'lastvalue' => '0'];
        $this->overrides['synoDisk.diskTable.diskEntry.diskBadSector.[0]'] = ['state' => '1'];
        $this->overrides['synologyDiskSMART.diskSMARTTable.diskSMARTEntry.diskSMARTStatus.[27]'] = ['status' => '1'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.temperature', null)->assertJsonPath('data.0.cpuPercent', null)
            ->assertJsonPath('data.0.disks.0.badSectors', null)->assertJsonPath('data.0.smart.unknown', 1)
            ->assertJsonPath('data.0.model', 'RS2421+');
    }

    public function test_missing_or_invalid_capacity_is_not_reported_as_zero_usage(): void
    {
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageSize[55]'] = ['lastvalue' => '0'];
        $this->overrides['host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[55,pct]'] = ['state' => '1'];
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.storageUsage', null)->assertJsonPath('data.0.freeBytes', null);
    }

    public function test_cache_retains_snapshot_timestamp_on_failure_and_isolates_credentials(): void
    {
        $this->fakeInventory();
        $initial = $this->getJson('/api/v1/nas/krian')->json();
        $this->getJson('/api/v1/nas/krian')->assertOk();
        Http::assertSentCount(3);
        $this->travel(21)->seconds();
        $this->unavailable = true;
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('meta.status', 'stale')
            ->assertJsonPath('meta.updatedAt', $initial['meta']['updatedAt'])->assertJsonCount(1, 'data');
        config(['zabbix.token' => 'new-token']);
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable')->assertJsonCount(0, 'data');
    }

    public function test_unconfigured_sites_and_inaccessible_groups_have_explicit_empty_states(): void
    {
        $this->getJson('/api/v1/nas/sidoarjo')->assertOk()->assertJsonPath('meta.status', 'unconfigured')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/nas/unknown')->assertNotFound();
        Http::assertNothingSent();
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])]);
        $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable')->assertJsonCount(0, 'data');
    }

    public function test_upstream_errors_are_sanitized_and_home_only_exposes_local_endpoints(): void
    {
        $home = $this->get('/')->assertOk();
        $this->assertSame('/api/v1/nas/krian', $home->viewData('page')['props']['nasEndpoints']['krian']);
        Http::assertNothingSent();
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['data' => 'private-nas-token']])]);
        $response = $this->getJson('/api/v1/nas/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable');
        $this->assertStringNotContainsString('private-nas-token', $response->getContent());
    }

    public function test_console_forces_refresh_and_rejects_unknown_sites(): void
    {
        $this->fakeInventory();
        $this->getJson('/api/v1/nas/krian');
        $this->artisan('zabbix:nas', ['site' => 'krian'])->expectsOutputToContain('Status: live | NAS: 1')->assertSuccessful();
        Http::assertSentCount(6);
        $this->artisan('zabbix:nas', ['site' => 'unknown'])->assertFailed();
    }

    private function fakeInventory(): void
    {
        $values = [
            'synoSystem.systemStatus' => '1', 'synoSystem.cpuFanStatus' => '1', 'synoSystem.systemFanStatus' => '1', 'synoSystem.powerStatus' => '1',
            'synoSystem.modelName' => 'RS2421+', 'synoSystem.version' => 'DSM 7.1-42962', 'synoSystem.ssCpuIdle' => '99', 'synoSystem.temperature' => '47',
            'ucdavis.memory.memTotalReal' => '4079534080', 'ucdavis.memory.memAvailReal' => '332902400',
            'host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageSize[55]' => '113997365510144',
            'host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[55]' => '78039060971520',
            'host.hrStorage.hrStorageTable.hrStorageEntry.hrStorageUsed[55,pct]' => '68',
            'synoDisk.diskTable.diskEntry.diskModel.[0]' => 'HAT5300-16T', 'synoDisk.diskTable.diskEntry.diskStatus.[0]' => '1',
            'synoDisk.diskTable.diskEntry.diskTemperature.[0]' => '39', 'synoDisk.diskTable.diskEntry.diskType.[0]' => 'SATA',
            'synoDisk.diskTable.diskEntry.diskBadSector.[0]' => '0',
            'synoRaid.raidTable.raidEntry.raidName.[7]' => 'Storage Pool 1', 'synoRaid.raidTable.raidEntry.raidStatus.[7]' => '1',
            'synologyDiskSMART.diskSMARTTable.diskSMARTEntry.diskSMARTStatus.[27]' => 'OK',
        ];
        foreach ($this->overrides as $key => $override) {
            $values[$key] ??= (string) ($override['lastvalue'] ?? '');
        }
        Http::fake(['zabbix.test/*' => function (Request $request) use ($values) {
            if ($this->unavailable) {
                return Http::response('unavailable', 503);
            }
            $items = [];
            foreach ($values as $key => $value) {
                $item = ['itemid' => (string) (54000 + count($items)), 'hostid' => '10705', 'key_' => $key, 'name' => $key,
                    'lastvalue' => $value, 'lastclock' => (string) now()->timestamp, 'state' => '0', 'status' => '0', 'delay' => '1m',
                    'units' => str_contains($key, 'hrStorage') || str_contains($key, 'memory') ? 'B' : '',
                    'preprocessing' => [['type' => '1', 'params' => '1024']],
                    'valuemap' => ['mappings' => [['type' => '0', 'value' => '1', 'newvalue' => 'Normal'], ['type' => '0', 'value' => '11', 'newvalue' => 'Degraded']]],
                ];
                if (str_contains($key, 'hrStorageSize')) {
                    $item['name'] = 'Storage Size on /volume1';
                } elseif (str_contains($key, 'hrStorageUsed')) {
                    $item['name'] = 'Storage Used on /volume1';
                }
                if (str_contains($key, 'diskStatus.')) {
                    $item['name'] = 'Disk 11 Status';
                }
                if (in_array($key, ['synoSystem.modelName', 'synoSystem.version'], true)) {
                    $item['delay'] = '1h';
                    $item['lastclock'] = (string) now()->subMinutes(40)->timestamp;
                }
                if (str_contains($key, 'diskBadSector')) {
                    $item['delay'] = '12h';
                    $item['lastclock'] = (string) now()->subHours(6)->timestamp;
                }
                $items[] = array_replace($item, $this->overrides[$key] ?? []);
            }

            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($request['method']) {
                'hostgroup.get' => [['groupid' => '27', 'name' => 'Krian/NAS']],
                'host.get' => [['hostid' => '10705', 'name' => 'Nas', 'interfaces' => [['type' => '2', 'main' => '1', 'useip' => '1', 'ip' => '192.168.20.2', 'available' => $this->snmpAvailable, 'error' => 'private-snmp-error']], 'inventory' => []]],
                'item.get' => $items, default => [],
            }]);
        }]);
    }
}
