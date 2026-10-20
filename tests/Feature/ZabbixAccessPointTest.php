<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZabbixAccessPointTest extends TestCase
{
    private bool $upstreamUnavailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'inertia.ssr.enabled' => false,
            'zabbix.url' => 'http://zabbix.test/api_jsonrpc.php',
            'zabbix.token' => 'private-test-token',
            'zabbix.sites.krian.access_point_group' => '23',
            'zabbix.sites.sidoarjo.access_point_group' => null,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_it_merges_hosts_and_icmp_items_with_units_and_health_status(): void
    {
        $this->fakeInventory();
        $response = $this->getJson('/api/v1/access-points/krian');
        $response->assertOk()->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.status', 'live')
            ->assertJsonPath('data.0.id', '10691')
            ->assertJsonPath('data.0.name', 'Ruijie-AR-1-2')
            ->assertJsonPath('data.0.latencyMs', 0.752)
            ->assertJsonPath('data.0.status', 'Online')
            ->assertJsonPath('data.0.health', 'Normal')
            ->assertJsonPath('data.0.ip', '192.168.88.101')
            ->assertJsonPath('data.1.status', 'Warning')
            ->assertJsonPath('data.1.health', 'Low')
            ->assertJsonPath('data.1.isReachable', true)
            ->assertJsonPath('data.2.health', 'Loss')
            ->assertJsonPath('data.3.status', 'Offline')
            ->assertJsonPath('data.3.isReachable', false)
            ->assertJsonPath('data.4.status', 'Unknown')
            ->assertJsonPath('data.4.latencyMs', null)
            ->assertJsonPath('data.5.status', 'Unknown')
            ->assertJsonPath('data.5.isReachable', null);
        $this->assertStringNotContainsString('private-test-token', $response->getContent());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'item.get'
            && $request->hasHeader('Authorization', 'Bearer private-test-token')
            && $request['params']['hostids'] === ['10691', '10692', '10693', '10694', '10696', '10702']
            && in_array('lastclock', $request['params']['output'], true)
        );
    }

    public function test_cache_prevents_duplicate_requests_and_reloads_after_expiry(): void
    {
        $this->fakeInventory();
        $this->getJson('/api/v1/access-points/krian')->assertOk();
        $this->getJson('/api/v1/access-points/krian')->assertOk();
        Http::assertSentCount(3);
        $this->travel(21)->seconds();
        $this->getJson('/api/v1/access-points/krian')->assertOk();
        Http::assertSentCount(6);
    }

    public function test_empty_group_response_reports_visibility_problem_without_demo_devices(): void
    {
        Http::fake(['zabbix.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])]);
        $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'unavailable')
            ->assertJsonPath('meta.updatedAt', null);
        Http::assertSentCount(1);
    }

    public function test_visible_group_with_no_monitored_hosts_is_a_valid_empty_inventory(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'result' => $request['method'] === 'hostgroup.get' ? [['groupid' => '23', 'name' => 'Krian/Access Point']] : [],
        ]));
        $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'live');
        Http::assertSentCount(2);
    }

    public function test_json_rpc_error_is_sanitized(): void
    {
        Http::fake(['zabbix.test/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'error' => ['code' => -32602, 'message' => 'private-test-token', 'data' => 'internal server detail'],
        ])]);
        $response = $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'unavailable');
        $this->assertStringNotContainsString('private-test-token', $response->getContent());
        $this->assertStringNotContainsString('internal server detail', $response->getContent());
    }

    public function test_connection_failure_returns_unavailable(): void
    {
        Http::fake(['zabbix.test/*' => Http::failedConnection()]);
        $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'unavailable');
    }

    public function test_last_good_snapshot_keeps_its_timestamp_and_is_marked_stale_on_failure(): void
    {
        $this->fakeInventory();
        $initial = $this->getJson('/api/v1/access-points/krian')->json();
        $this->travel(21)->seconds();
        $this->upstreamUnavailable = true;
        $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonCount(6, 'data')->assertJsonPath('meta.status', 'stale')
            ->assertJsonPath('meta.updatedAt', $initial['meta']['updatedAt']);
    }

    public function test_unknown_and_unconfigured_sites_do_not_query_zabbix(): void
    {
        $this->getJson('/api/v1/access-points/unknown')->assertNotFound();
        $this->getJson('/api/v1/access-points/sidoarjo')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'unconfigured');
        Http::assertNothingSent();
    }

    public function test_missing_token_does_not_send_a_request(): void
    {
        config(['zabbix.token' => null]);
        $this->getJson('/api/v1/access-points/krian')->assertOk()
            ->assertJsonPath('meta.status', 'unavailable');
        Http::assertNothingSent();
    }

    public function test_home_exposes_only_local_endpoints_without_credentials(): void
    {
        $response = $this->get('/')->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('/api/v1/access-points/krian', $page['props']['accessPointEndpoints']['krian']);
        $this->assertStringNotContainsString('private-test-token', $response->getContent());
        $this->assertStringNotContainsString('zabbix.test', $response->getContent());
        Http::assertNothingSent();
    }

    private function fakeInventory(): void
    {
        $hosts = [];
        $items = [];
        $ids = ['10691', '10692', '10693', '10694', '10696', '10702'];
        foreach ($ids as $index => $id) {
            $hosts[] = ['hostid' => $id, 'name' => $index === 0 ? 'Ruijie-AR-1-2' : 'AP-'.$id, 'inventory' => ['location' => 'Office'], 'interfaces' => [['main' => '1', 'useip' => '1', 'ip' => '192.168.88.'.(101 + $index)]]];
            $values = ['icmpping' => $index === 3 ? '0' : '1', 'icmppingsec' => $index === 1 ? '0.18' : '0.0007516666666666667', 'icmppingloss' => $index === 2 ? '5' : '0'];
            foreach ($values as $key => $value) {
                $items[] = ['hostid' => $id, 'key_' => $key, 'lastvalue' => $value,
                    'state' => $index === 4 && $key === 'icmppingsec' ? '1' : '0',
                    'lastclock' => (string) ($index === 5 ? now()->subMinutes(10)->timestamp : now()->timestamp),
                ];
            }
        }
        Http::fake(fn (Request $request) => $this->upstreamUnavailable ? Http::response('down', 503) : Http::response([
            'jsonrpc' => '2.0', 'id' => 1,
            'result' => match ($request['method']) {
                'hostgroup.get' => [['groupid' => '23', 'name' => 'Krian/Access Point']],
                'host.get' => $hosts,
                'item.get' => $items,
                default => [],
            },
        ]));
    }
}
