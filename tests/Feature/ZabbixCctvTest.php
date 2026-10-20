<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZabbixCctvTest extends TestCase
{
    private bool $upstreamUnavailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config([
            'inertia.ssr.enabled' => false,
            'zabbix.url' => 'http://zabbix.test/api_jsonrpc.php',
            'zabbix.token' => 'private-cctv-token',
            'zabbix.sites.krian.cctv_group' => '28',
            'zabbix.sites.sidoarjo.cctv_group' => null,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_camera_sample_is_one_host_with_ping_latency_and_packet_loss(): void
    {
        $this->fakeInventory([$this->host('10731')], [
            $this->item('10731', 'icmpping', '1'),
            $this->item('10731', 'icmppingloss', '0'),
            $this->item('10731', 'icmppingsec', '0.00024566666666666666'),
        ]);
        $response = $this->getJson('/api/v1/cctv/krian')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.status', 'live')
            ->assertJsonPath('meta.source', 'zabbix')
            ->assertJsonPath('data.0.id', '10731')
            ->assertJsonPath('data.0.name', 'Camera 192.168.15.9')
            ->assertJsonPath('data.0.ip', '192.168.15.9')
            ->assertJsonPath('data.0.location', 'Gate In')
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.isReachable', true)
            ->assertJsonPath('data.0.latencyMs', 0.246)
            ->assertJsonPath('data.0.packetLossPercent', 0)
            ->assertJsonPath('data.0.lastCheckedAt', date(DATE_ATOM, now()->timestamp));
        $this->assertArrayNotHasKey('recording', $response->json('data.0'));
        $this->assertStringNotContainsString('private-cctv-token', $response->getContent());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'host.get'
            && $request['params']['groupids'] === ['28']
            && $request['params']['monitored_hosts'] === true);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && $request->hasHeader('Authorization', 'Bearer private-cctv-token')
            && $request['params']['hostids'] === ['10731']
            && $request['params']['filter']['key_'] === ['icmpping', 'icmppingsec', 'icmppingloss']
            && in_array('lastclock', $request['params']['output'], true)
            && in_array('state', $request['params']['output'], true));
    }

    public function test_status_depends_only_on_valid_ping_and_items_are_grouped_by_camera(): void
    {
        $hosts = array_map(fn (int $id): array => $this->host((string) $id), [1, 2, 3, 4]);
        $this->fakeInventory($hosts, [
            $this->item('3', 'icmppingloss', '90'),
            $this->item('2', 'icmpping', '0'),
            $this->item('1', 'icmpping', '1'),
            $this->item('3', 'icmpping', '1'),
            $this->item('3', 'icmppingsec', '0.5'),
        ]);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.status', 'Online')->assertJsonPath('data.0.latencyMs', null)
            ->assertJsonPath('data.1.status', 'Offline')->assertJsonPath('data.1.isReachable', false)
            ->assertJsonPath('data.2.status', 'Online')->assertJsonPath('data.2.latencyMs', 500)
            ->assertJsonPath('data.2.packetLossPercent', 90)
            ->assertJsonPath('data.3.status', 'Unknown')->assertJsonPath('data.3.isReachable', null);
    }

    public function test_multiple_switch_groups_are_merged_without_double_counting_shared_cameras(): void
    {
        config(['zabbix.sites.krian.cctv_group' => ' 28, 29,28, ']);
        $groups = [
            ['groupid' => '29', 'name' => 'Krian/CCTV 10.238'],
            ['groupid' => '28', 'name' => 'Krian/CCTV 15.248'],
        ];
        $first = array_replace($this->host('1'), ['hostgroups' => [
            ['groupid' => '28', 'name' => 'Old name'], ['groupid' => '26', 'name' => 'Other devices'],
        ]]);
        $second = array_replace($this->host('2'), ['hostgroups' => [['groupid' => '29', 'name' => 'Krian/CCTV 10.238']]]);
        $shared = array_replace($this->host('3'), ['hostgroups' => $groups]);
        $this->fakeInventory([$first, $second, $shared, $shared], [
            $this->item('3', 'icmpping', '1'), $this->item('2', 'icmpping', '0'), $this->item('1', 'icmpping', '1'),
        ], $groups);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.switchGroups', [
                ['id' => '28', 'name' => 'Krian/CCTV 15.248'], ['id' => '29', 'name' => 'Krian/CCTV 10.238'],
            ])
            ->assertJsonCount(1, 'data.0.switchGroups')
            ->assertJsonPath('data.0.switchGroups.0.name', 'Krian/CCTV 15.248')
            ->assertJsonPath('data.1.switchGroups.0.id', '29')
            ->assertJsonPath('data.1.status', 'Offline')
            ->assertJsonCount(2, 'data.2.switchGroups');
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'host.get'
            && $request['params']['groupids'] === ['28', '29']
            && $request['params']['selectHostGroups'] === ['groupid', 'name']);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && $request['params']['hostids'] === ['1', '2', '3']);
    }

    public function test_empty_switch_group_is_retained_in_filter_metadata(): void
    {
        config(['zabbix.sites.krian.cctv_group' => ['28', '29']]);
        $groups = [['groupid' => '28', 'name' => 'Switch A'], ['groupid' => '29', 'name' => 'Switch B']];
        $host = array_replace($this->host('1'), ['hostgroups' => [$groups[0]]]);
        $this->fakeInventory([$host], [$this->item('1', 'icmpping', '1')], $groups);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'meta.switchGroups')->assertJsonPath('data.0.switchGroups', [['id' => '28', 'name' => 'Switch A']]);
    }

    public function test_partial_group_visibility_is_reported_instead_of_a_misleading_partial_total(): void
    {
        config(['zabbix.sites.krian.cctv_group' => '28,29']);
        $this->fakeInventory([$this->host('1')], [$this->item('1', 'icmpping', '1')]);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable')
            ->assertJsonCount(0, 'data')->assertJsonCount(0, 'meta.switchGroups');
        Http::assertSentCount(1);
    }

    public function test_switch_group_configuration_changes_do_not_reuse_another_groups_cache(): void
    {
        Http::fake(function (Request $request) {
            $groupId = config('zabbix.sites.krian.cctv_group');
            $hostId = $groupId === '28' ? '1' : '2';

            return Http::response([
                'jsonrpc' => '2.0', 'id' => 1,
                'result' => match ($request['method']) {
                    'hostgroup.get' => [['groupid' => $groupId, 'name' => 'Switch '.$groupId]],
                    'host.get' => [$this->host($hostId)],
                    'item.get' => [$this->item($hostId, 'icmpping', $hostId === '1' ? '1' : '0')],
                    default => [],
                },
            ]);
        });
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('data.0.id', '1');
        config(['zabbix.sites.krian.cctv_group' => '29']);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('data.0.id', '2')
            ->assertJsonPath('data.0.status', 'Offline')->assertJsonPath('data.0.switchGroups.0.name', 'Switch 29');
        Http::assertSentCount(6);
    }

    public function test_stale_unsupported_missing_and_invalid_ping_values_are_unknown(): void
    {
        $invalidPings = [
            ['lastclock' => (string) now()->subMinutes(10)->timestamp],
            ['state' => '1', 'error' => 'private internal detail'],
            ['lastclock' => '0'],
            ['lastclock' => (string) now()->addMinutes(10)->timestamp],
            ['lastvalue' => '0.5'],
            ['lastvalue' => 'invalid'],
            ['lastvalue' => '2'],
        ];
        $hosts = [];
        $items = [];
        foreach ($invalidPings as $index => $overrides) {
            $id = (string) ($index + 1);
            $hosts[] = $this->host($id);
            $items[] = array_replace($this->item($id, 'icmpping', '1'), $overrides);
        }
        $hosts[] = $this->host('missing');
        $this->fakeInventory($hosts, $items);
        $response = $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(8, 'data');
        foreach ($response->json('data') as $camera) {
            $this->assertSame('Unknown', $camera['status']);
            $this->assertNull($camera['isReachable']);
        }
        $this->assertStringNotContainsString('private internal detail', $response->getContent());
    }

    public function test_host_without_ping_is_retained_and_missing_metadata_is_not_inferred_from_name(): void
    {
        $host = ['hostid' => '10731', 'name' => 'Camera 192.168.15.9'];
        $this->fakeInventory([$host], []);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'Unknown')->assertJsonPath('data.0.ip', 'Unknown')
            ->assertJsonPath('data.0.location', 'Unknown')->assertJsonPath('data.0.lastCheckedAt', null);
    }

    public function test_cache_reuses_snapshot_and_refreshes_after_expiry(): void
    {
        $this->fakeInventory([$this->host('10731')], [$this->item('10731', 'icmpping', '1')]);
        $this->getJson('/api/v1/cctv/krian')->assertOk();
        $this->getJson('/api/v1/cctv/krian')->assertOk();
        Http::assertSentCount(3);
        $this->travel(301)->seconds();
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('data.0.status', 'Unknown');
        Http::assertSentCount(6);
    }

    public function test_upstream_failure_preserves_inventory_and_timestamp_but_clears_current_status(): void
    {
        $this->fakeInventory([$this->host('10731')], [$this->item('10731', 'icmpping', '1')]);
        $initial = $this->getJson('/api/v1/cctv/krian')->json();
        $this->travel(21)->seconds();
        $this->upstreamUnavailable = true;
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.status', 'stale')
            ->assertJsonPath('meta.updatedAt', $initial['meta']['updatedAt'])
            ->assertJsonPath('data.0.lastCheckedAt', $initial['data'][0]['lastCheckedAt'])
            ->assertJsonPath('data.0.status', 'Unknown')->assertJsonPath('data.0.isReachable', null)
            ->assertJsonPath('data.0.latencyMs', null)->assertJsonPath('data.0.packetLossPercent', null);
    }

    public function test_visible_empty_group_is_live_without_demo_cameras(): void
    {
        $this->fakeInventory([], []);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.status', 'live');
        Http::assertSentCount(2);
    }

    public function test_inaccessible_group_is_unavailable(): void
    {
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])]);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.status', 'unavailable')->assertJsonPath('meta.updatedAt', null);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_unavailable_without_demo_cameras(): void
    {
        Http::fake(['zabbix.test/*' => Http::failedConnection()]);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.status', 'unavailable');
    }

    public function test_api_errors_do_not_expose_credentials_or_upstream_details(): void
    {
        Http::fake(['zabbix.test/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'error' => ['code' => -32602, 'message' => 'private-cctv-token', 'data' => 'internal detail'],
        ])]);
        $response = $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable');
        $this->assertStringNotContainsString('private-cctv-token', $response->getContent());
        $this->assertStringNotContainsString('internal detail', $response->getContent());
    }

    public function test_unknown_unconfigured_and_missing_credentials_do_not_send_requests(): void
    {
        $this->getJson('/api/v1/cctv/unknown')->assertNotFound();
        $this->getJson('/api/v1/cctv/sidoarjo')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.status', 'unconfigured');
        config(['zabbix.token' => null]);
        $this->getJson('/api/v1/cctv/krian')->assertOk()->assertJsonPath('meta.status', 'unavailable');
        Http::assertNothingSent();
    }

    public function test_home_exposes_camera_endpoints_without_zabbix_credentials(): void
    {
        $response = $this->get('/')->assertOk();
        $this->assertSame('/api/v1/cctv/krian', $response->viewData('page')['props']['cctvEndpoints']['krian']);
        $this->assertStringNotContainsString('private-cctv-token', $response->getContent());
        $this->assertStringNotContainsString('zabbix.test', $response->getContent());
        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function host(string $id): array
    {
        return ['hostid' => $id, 'name' => 'Camera 192.168.15.9', 'inventory' => ['location' => 'Gate In'],
            'interfaces' => [['main' => '1', 'useip' => '1', 'ip' => '192.168.15.9']]];
    }

    /** @return array<string, mixed> */
    private function item(string $hostId, string $key, string $value): array
    {
        return ['hostid' => $hostId, 'key_' => $key, 'lastvalue' => $value, 'state' => '0', 'lastclock' => (string) now()->timestamp];
    }

    /**
     * @param  list<array<string, mixed>>  $hosts
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>|null  $groups
     */
    private function fakeInventory(array $hosts, array $items, ?array $groups = null): void
    {
        Http::fake(fn (Request $request) => $this->upstreamUnavailable ? Http::response('down', 503) : Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'result' => match ($request['method']) {
                'hostgroup.get' => $groups ?? [['groupid' => '28', 'name' => 'Krian/CCTV Cameras']],
                'host.get' => $hosts,
                'item.get' => $items,
                default => [],
            },
        ]));
    }
}
