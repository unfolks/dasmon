<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZabbixComputerTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $overrides = [];

    private bool $unavailable = false;

    /** @var list<string> */
    private array $removedKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'inertia.ssr.enabled' => false,
            'zabbix.url' => 'http://zabbix.test/api_jsonrpc.php',
            'zabbix.token' => 'private-computer-token',
            'zabbix.sites.krian.computer_group' => '25',
            'zabbix.sites.sidoarjo.computer_group' => null,
            'zabbix.computer_thresholds' => ['cpu' => 85, 'memory' => 90, 'disk' => 85],
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_computer_items_are_merged_with_dynamic_storage_indices_and_correct_units(): void
    {
        $this->fakeInventory();
        $response = $this->getJson('/api/v1/computers/krian');
        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.status', 'live')
            ->assertJsonPath('data.0.name', 'PC-Server')
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.ip', '192.168.30.78')
            ->assertJsonPath('data.0.latencyMs', 0.594)
            ->assertJsonPath('data.0.cpuPercent', 11.6875)
            ->assertJsonPath('data.0.systemName', 'server1')
            ->assertJsonPath('data.0.uptimeSeconds', 100499)
            ->assertJsonPath('data.0.memory.0.index', '7')
            ->assertJsonPath('data.0.memory.0.totalBytes', 34243543040)
            ->assertJsonPath('data.0.memory.0.usedBytes', 23172087808)
            ->assertJsonPath('data.0.disks.0.index', '9')
            ->assertJsonPath('data.0.disks.0.name', 'C:')
            ->assertJsonPath('data.0.disks.0.totalBytes', 255034900480)
            ->assertJsonPath('data.0.interfaces.0.inBps', 84360)
            ->assertJsonPath('data.0.interfaces.0.speedBps', 1000000000)
            ->assertJsonPath('data.0.interfaces.0.name', 'Ethernet');
        $this->assertStringNotContainsString('private-computer-token', $response->getContent());
        $this->assertStringNotContainsString('preprocessing', $response->getContent());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && $request->hasHeader('Authorization', 'Bearer private-computer-token')
            && $request['params']['hostids'] === ['10703']
            && $request['params']['searchByAny'] === true
            && $request['params']['monitored'] === true
            && in_array('vm.memory.*', $request['params']['search']['key_'], true)
        );
    }

    public function test_resource_warning_keeps_computer_reachable(): void
    {
        $this->overrides['system.cpu.util'] = ['lastvalue' => '95'];
        $this->overrides['vfs.fs.pused[9]'] = ['lastvalue' => '91'];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.warnings', ['CPU tinggi', 'C: hampir penuh']);
    }

    public function test_snmp_failure_is_distinct_from_offline_ping(): void
    {
        $this->overrides['zabbix[host,snmp,available]'] = ['lastvalue' => '0'];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.snmpStatus', 'Unavailable');
    }

    public function test_ping_failure_marks_computer_offline(): void
    {
        $this->overrides['icmpping'] = ['lastvalue' => '0'];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Offline')
            ->assertJsonPath('data.0.isReachable', false);
    }

    public function test_active_agent_metrics_are_available_without_icmp_or_snmp(): void
    {
        $this->fakeInventory(agent: true);
        $response = $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.monitoringType', 'Zabbix agent (active)')
            ->assertJsonPath('data.0.monitoringStatus', 'Available')
            ->assertJsonPath('data.0.agentStatus', 'Available')
            ->assertJsonPath('data.0.snmpStatus', 'Unknown')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.pingReachable', null)
            ->assertJsonPath('data.0.pingLastCheckedAt', null)
            ->assertJsonPath('data.0.latencyMs', null)
            ->assertJsonPath('data.0.systemName', 'LAPTOP-RO1U7RC5')
            ->assertJsonPath('data.0.uptimeSeconds', 3577)
            ->assertJsonPath('data.0.memory.0.usedPercent', 44.55318341662717)
            ->assertJsonPath('data.0.memory.0.totalBytes', 16776155136)
            ->assertJsonPath('data.0.memory.0.usedBytes', 7476604928)
            ->assertJsonCount(2, 'data.0.disks')
            ->assertJsonPath('data.0.disks.0.name', 'C:')
            ->assertJsonPath('data.0.disks.0.usedPercent', 33.5033375737997)
            ->assertJsonPath('data.0.disks.1.name', 'D:')
            ->assertJsonPath('data.0.interfaces.0.status', 'Up')
            ->assertJsonPath('data.0.interfaces.0.source', 'Zabbix agent')
            ->assertJsonPath('data.0.interfaces.0.inBps', 17808)
            ->assertJsonPath('data.0.interfaces.0.outBps', 8408)
            ->assertJsonPath('data.0.interfaces.0.speedBps', 540000000)
            ->assertJsonPath('data.0.ip', '192.168.30.99')
            ->assertJsonPath('data.0.warnings', []);
        $this->assertStringContainsString('Windows LAPTOP-RO1U7RC5', $response->json('data.0.systemDescription'));
        $this->assertNotNull($response->json('data.0.lastCheckedAt'));
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && collect(['agent.ping', 'system.hostname', 'system.uname', 'system.uptime', 'zabbix[host,active_agent,available]', 'zabbix[host,agent,available]'])
                ->every(fn (string $key): bool => in_array($key, $request['params']['search']['key_'], true))
            && in_array('lastclock', $request['params']['output'], true)
            && in_array('state', $request['params']['output'], true)
        );
    }

    public function test_active_agent_connection_remains_available_when_ping_fails(): void
    {
        $this->overrides['icmpping'] = ['lastvalue' => '0'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.pingReachable', false);
    }

    public function test_agent_failure_does_not_claim_the_computer_is_powered_off(): void
    {
        $this->overrides['zabbix[host,active_agent,available]'] = ['lastvalue' => '2'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.agentStatus', 'Unavailable')
            ->assertJsonPath('data.0.isReachable', null)
            ->assertJsonPath('data.0.warnings', ['Zabbix agent tidak dapat diakses']);
    }

    public function test_active_agent_unknown_status_is_not_overridden_by_an_old_ping_value(): void
    {
        $this->overrides['zabbix[host,active_agent,available]'] = ['lastvalue' => '0'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.agentStatus', 'Unknown')
            ->assertJsonPath('data.0.isReachable', null);
    }

    public function test_stale_and_unsupported_agent_metrics_are_not_shown_as_healthy(): void
    {
        $this->overrides['zabbix[host,active_agent,available]'] = ['lastclock' => (string) now()->subMinutes(10)->timestamp];
        $this->overrides['system.cpu.util'] = ['state' => '1', 'lastvalue' => '0'];
        $this->overrides['vm.memory.util'] = ['lastclock' => '0'];
        $this->overrides['vm.memory.size[used]'] = ['state' => '1'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.agentStatus', 'Unknown')
            ->assertJsonPath('data.0.cpuPercent', null)
            ->assertJsonPath('data.0.memory.0.usedPercent', null);
    }

    public function test_agent_disk_json_can_supply_metrics_and_resource_warnings(): void
    {
        $this->removedKeys = ['vfs.fs.dependent.size[C:,used]', 'vfs.fs.dependent.size[C:,total]', 'vfs.fs.dependent.size[C:,pused]'];
        $this->overrides['vfs.fs.dependent[C:,data]'] = ['lastvalue' => json_encode(['bytes' => ['used' => 95, 'total' => 100, 'pused' => 95]])];
        $this->overrides['vm.memory.util'] = ['lastvalue' => '92'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Warning')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.disks.0.usedPercent', 95)
            ->assertJsonPath('data.0.disks.0.usedBytes', 95)
            ->assertJsonPath('data.0.warnings', ['Physical Memory hampir penuh', 'C: hampir penuh']);
    }

    public function test_agent_ping_can_supply_availability_when_internal_item_is_absent(): void
    {
        $this->removedKeys = ['zabbix[host,active_agent,available]'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.monitoringType', 'Zabbix agent');
    }

    public function test_passive_agent_and_snmp_unknown_codes_are_distinct_from_active_agent_codes(): void
    {
        $this->removedKeys = ['zabbix[host,active_agent,available]'];
        $this->overrides['zabbix[host,agent,available]'] = ['lastvalue' => '0'];
        $this->overrides['zabbix[host,snmp,available]'] = ['lastvalue' => '2'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.agentStatus', 'Unavailable')
            ->assertJsonPath('data.0.snmpStatus', 'Unknown')
            ->assertJsonPath('data.0.monitoringType', 'Zabbix agent');
    }

    public function test_windows_interface_status_and_byte_units_are_normalized_without_using_error_counters(): void
    {
        $interface = '{E6E1741D-D11A-4F67-9D99-5A6508B239F9}';
        $this->overrides['net.if.status["'.$interface.'"]'] = ['lastvalue' => '0'];
        $this->overrides['net.if.in["'.$interface.'"]'] = ['units' => 'Bps'];
        $this->overrides['net.if.out["'.$interface.'",errors]'] = ['lastvalue' => '999'];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonCount(1, 'data.0.interfaces')
            ->assertJsonPath('data.0.interfaces.0.status', 'Down')
            ->assertJsonPath('data.0.interfaces.0.inBps', 142464)
            ->assertJsonPath('data.0.interfaces.0.outBps', 8408);
    }

    public function test_mixed_computer_inventory_normalizes_the_six_sample_hosts_independently(): void
    {
        $samples = [
            ['10703', 'PC-Server', false, '1', '7.4375', '52.072189315139276', '79.65177361438434'],
            ['10707', 'PC-HR-1', false, '0', '0', null, null],
            ['10708', 'PC-HR-2', false, '0', '0', null, null],
            ['10712', 'Laptop-it-1', true, '1', '1.551506', '44.55318341662717', '33.5033375737997'],
            ['10713', 'PC-HR-4', true, '1', '0.710132', '53.090594691079986', '29.27318053267774'],
            ['10714', 'PC-HR-3', true, '1', '2.095069', '48.509401546175454', '57.17634276073214'],
        ];
        $hosts = [];
        $items = [];
        foreach ($samples as [$hostId, $name, $agent, $available, $cpu, $memory, $disk]) {
            $hosts[] = ['hostid' => $hostId, 'name' => $name, 'interfaces' => [], 'inventory' => []];
            $values = $agent ? [
                'agent.ping' => '1', 'zabbix[host,active_agent,available]' => $available,
                'system.cpu.util' => $cpu, 'vm.memory.util' => $memory,
                'vfs.fs.dependent.size[C:,pused]' => $disk,
            ] : [
                'icmpping' => '1', 'zabbix[host,snmp,available]' => $available,
                'system.cpu.util' => $cpu, 'vm.memory.util[4]' => $memory, 'vfs.fs.pused[1]' => $disk,
            ];
            foreach ($values as $key => $value) {
                $items[] = ['hostid' => $hostId, 'key_' => $key, 'lastvalue' => $value,
                    'lastclock' => (string) now()->timestamp, 'state' => '0', 'status' => '0', 'delay' => '1m',
                ];
            }
        }
        Http::fake(fn (Request $request) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($request['method']) {
            'hostgroup.get' => [['groupid' => '25', 'name' => 'Krian/Computers']],
            'host.get' => $hosts,
            'item.get' => $items,
            default => [],
        }]));
        $response = $this->getJson('/api/v1/computers/krian')->assertOk()->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.monitoringType', 'SNMP')
            ->assertJsonPath('data.1.status', 'Warning')
            ->assertJsonPath('data.2.status', 'Warning');
        foreach ([3, 4, 5] as $index) {
            $response->assertJsonPath('data.'.$index.'.status', 'Online')
                ->assertJsonPath('data.'.$index.'.monitoringType', 'Zabbix agent (active)')
                ->assertJsonPath('data.'.$index.'.pingReachable', null);
        }
        $response->assertJsonPath('data.3.memory.0.usedPercent', 44.55318341662717)
            ->assertJsonPath('data.4.memory.0.usedPercent', 53.090594691079986)
            ->assertJsonPath('data.5.disks.0.usedPercent', 57.17634276073214);
    }

    public function test_sample_values_without_collection_metadata_are_not_treated_as_live(): void
    {
        $this->overrides['agent.ping'] = ['lastclock' => '0'];
        $this->overrides['zabbix[host,active_agent,available]'] = ['state' => null, 'lastclock' => null];
        $this->fakeInventory(agent: true);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.agentStatus', 'Unknown')
            ->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.lastCheckedAt', null);
    }

    public function test_stale_performance_is_unknown_without_discarding_unchanged_metadata(): void
    {
        $this->overrides['system.cpu.util'] = ['lastclock' => (string) now()->subMinutes(10)->timestamp];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.cpuPercent', null)
            ->assertJsonPath('data.0.systemName', 'server1')
            ->assertJsonPath('data.0.interfaces.0.speedBps', 1000000000);
    }

    public function test_metadata_is_unknown_after_its_heartbeat_window(): void
    {
        $this->overrides['system.name'] = ['lastclock' => (string) now()->subHours(14)->timestamp];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.systemName', null)
            ->assertJsonPath('data.0.status', 'Online');
    }

    public function test_unsupported_invalid_and_never_collected_metrics_remain_unknown(): void
    {
        $this->overrides['system.cpu.util'] = ['state' => '1', 'lastvalue' => '0'];
        $this->overrides['icmpping'] = ['lastclock' => '0', 'lastvalue' => '1'];
        $this->overrides['icmppingsec'] = ['lastvalue' => 'not-a-number'];
        $this->overrides['vm.memory.util[7]'] = ['lastvalue' => '101'];
        $this->overrides['vm.memory.used[hrStorageUsed.7]'] = ['state' => '1'];
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('data.0.status', 'Unknown')
            ->assertJsonPath('data.0.isReachable', null)
            ->assertJsonPath('data.0.cpuPercent', null)
            ->assertJsonPath('data.0.latencyMs', null)
            ->assertJsonPath('data.0.memory.0.usedPercent', null);
    }

    public function test_cache_and_last_good_snapshot_preserve_original_timestamp_on_failure(): void
    {
        $this->fakeInventory();
        $initial = $this->getJson('/api/v1/computers/krian')->json();
        $this->getJson('/api/v1/computers/krian')->assertOk();
        Http::assertSentCount(3);
        $this->travel(21)->seconds();
        $this->unavailable = true;
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('meta.status', 'stale')
            ->assertJsonPath('meta.updatedAt', $initial['meta']['updatedAt'])
            ->assertJsonCount(1, 'data');
    }

    public function test_new_credentials_cannot_reuse_previous_tokens_snapshot(): void
    {
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk();
        $this->unavailable = true;
        config(['zabbix.token' => 'replacement-token']);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('meta.status', 'unavailable')->assertJsonCount(0, 'data');
    }

    public function test_inaccessible_groups_return_an_explicit_empty_state(): void
    {
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'result' => [], 'id' => 1])]);
        $this->getJson('/api/v1/computers/krian')->assertOk()
            ->assertJsonPath('meta.status', 'unavailable')->assertJsonCount(0, 'data');
    }

    public function test_unconfigured_and_unknown_sites_do_not_send_requests(): void
    {
        $this->getJson('/api/v1/computers/sidoarjo')->assertOk()
            ->assertJsonPath('meta.status', 'unconfigured')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/computers/unknown')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_computer_endpoint_does_not_leak_upstream_errors_or_tokens(): void
    {
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['message' => 'private-computer-token', 'data' => 'sensitive-internal-error']])]);
        $response = $this->getJson('/api/v1/computers/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable');
        $this->assertStringNotContainsString('private-computer-token', $response->getContent());
        $this->assertStringNotContainsString('sensitive-internal-error', $response->getContent());
    }

    public function test_home_returns_local_computer_endpoints_without_querying_zabbix(): void
    {
        $response = $this->get('/')->assertOk();
        $this->assertSame('/api/v1/computers/krian', $response->viewData('page')['props']['computerEndpoints']['krian']);
        $this->assertStringNotContainsString('private-computer-token', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_console_command_forces_refresh_and_handles_unknown_sites(): void
    {
        $this->fakeInventory();
        $this->getJson('/api/v1/computers/krian')->assertOk();
        $this->artisan('zabbix:computers', ['site' => 'krian'])->expectsOutputToContain('Status: live | Computers: 1')->assertSuccessful();
        Http::assertSentCount(6);
        $this->artisan('zabbix:computers', ['site' => 'unknown'])->assertFailed();
    }

    private function fakeInventory(bool $agent = false): void
    {
        $values = [
            'icmpping' => '1', 'icmppingsec' => '0.000594', 'icmppingloss' => '0',
            'zabbix[host,snmp,available]' => '1', 'system.cpu.util' => '11.6875',
            'system.name' => 'server1', 'system.descr[sysDescr.0]' => 'Windows Version 6.3 (Build 26200)',
            'system.hw.uptime[hrSystemUptime.0]' => '100499', 'system.net.uptime[sysUpTime.0]' => '80812',
            'vm.memory.data[7]' => json_encode(['hrStorageDescr' => 'Physical Memory', 'hrStorageType' => '.1.3.6.1.2.1.25.2.1.2']),
            'vm.memory.util[7]' => '67.66848798599084', 'vm.memory.walk.data.total[hrStorageSize.7]' => '34243543040', 'vm.memory.used[hrStorageUsed.7]' => '23172087808',
            'vfs.fs.walk.data[hrStorage.9]' => json_encode(['hrStorageDescr' => 'C:\\ Label:OS']),
            'vfs.fs.pused[9]' => '84.95243829618154', 'vfs.fs.total[hrStorageSize.9]' => '255034900480', 'vfs.fs.used[hrStorageUsed.9]' => '216658366464',
            'net.if.in[ifInOctets.2]' => '84360', 'net.if.out[ifOutOctets.2]' => '54880',
            'net.if.speed[ifHighSpeed.2]' => '1000000000', 'net.if.status[ifOperStatus.2]' => '1',
        ];
        if ($agent) {
            $values = [
                'agent.ping' => '1', 'zabbix[host,active_agent,available]' => '1',
                'system.cpu.util' => '1.551506', 'system.hostname' => 'LAPTOP-RO1U7RC5',
                'system.uname' => 'Windows LAPTOP-RO1U7RC5 10.0.26200 Microsoft Windows 11 Home Single Language x64',
                'system.uptime' => '3577', 'vm.memory.util' => '44.55318341662717',
                'vm.memory.size[total]' => '16776155136', 'vm.memory.size[used]' => '7476604928',
                'vfs.fs.dependent[C:,data]' => '',
                'vfs.fs.dependent.size[C:,used]' => '98228162560',
                'vfs.fs.dependent.size[C:,total]' => '293189185536',
                'vfs.fs.dependent.size[C:,pused]' => '33.5033375737997',
                'vfs.fs.dependent.size[D:,used]' => '1856557056',
                'vfs.fs.dependent.size[D:,total]' => '215748702208',
                'vfs.fs.dependent.size[D:,pused]' => '0.8605182960545097',
                'net.if.in["{E6E1741D-D11A-4F67-9D99-5A6508B239F9}"]' => '17808',
                'net.if.out["{E6E1741D-D11A-4F67-9D99-5A6508B239F9}"]' => '8408',
                'net.if.speed["{E6E1741D-D11A-4F67-9D99-5A6508B239F9}"]' => '540000000',
                'net.if.status["{E6E1741D-D11A-4F67-9D99-5A6508B239F9}"]' => '2',
            ];
        }
        $values += array_fill_keys(array_keys($this->overrides), null);
        $values = array_diff_key($values, array_flip($this->removedKeys));
        Http::fake(function (Request $request) use ($values, $agent) {
            if ($this->unavailable) {
                return Http::response('unavailable', 503);
            }
            $items = [];
            foreach ($values as $key => $value) {
                $item = ['itemid' => (string) (50000 + count($items)), 'hostid' => '10703', 'key_' => $key, 'lastvalue' => $value,
                    'state' => '0', 'status' => '0', 'delay' => '1m', 'lastclock' => (string) now()->timestamp,
                    'preprocessing' => [], 'tags' => [['tag' => 'interface', 'value' => 'Ethernet']],
                ];
                if ($key === 'system.name') {
                    $item['lastclock'] = (string) now()->subHours(10)->timestamp;
                    $item['delay'] = '15m';
                    $item['preprocessing'] = [['type' => '20', 'params' => '12h']];
                }
                if ($key === 'net.if.speed[ifHighSpeed.2]') {
                    $item['lastclock'] = (string) now()->subMinutes(30)->timestamp;
                    $item['delay'] = '0';
                    $item['preprocessing'] = [['type' => '20', 'params' => '1h']];
                }
                $items[] = array_replace($item, $this->overrides[$key] ?? []);
            }

            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($request['method']) {
                'hostgroup.get' => [['groupid' => '25', 'name' => 'Krian/Computers']],
                'host.get' => [['hostid' => '10703', 'name' => 'PC-Server', 'interfaces' => [
                    ['type' => '2', 'main' => '1', 'useip' => '1', 'ip' => '192.168.30.78'],
                    ...($agent ? [['type' => '1', 'main' => '1', 'useip' => '1', 'ip' => '192.168.30.99']] : []),
                ], 'inventory' => []]],
                'item.get' => array_values(array_filter($items, fn (array $item): bool => collect($request['params']['search']['key_'])
                    ->contains(fn (string $pattern): bool => preg_match('/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/', $item['key_']) === 1))),
                default => [],
            }]);
        });
    }
}
