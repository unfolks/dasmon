<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class DahuaSwitchTemplateTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $export;

    /** @var array<string, mixed> */
    private array $template;

    protected function setUp(): void
    {
        parent::setUp();
        $document = Yaml::parseFile(dirname(__DIR__, 2).'/zabbix-api/template_module_dahua_video_camera_modification.yaml');
        $this->export = $document['zabbix_export'];
        $this->template = $this->export['templates'][0];
    }

    public function test_import_preserves_template_and_original_item_identity(): void
    {
        $this->assertSame('7.0', $this->export['version']);
        $this->assertSame('7159126b467d4b9db1ecacbdcade9c2f', $this->template['uuid']);
        $this->assertSame('Dahua Video Camera', $this->template['template']);
        $this->assertSame('Dahua Switch by SNMP', $this->template['name']);
        $this->assertArrayHasKey('template_groups', $this->export);
        $originalItems = [
            'cpuUsage.0' => '8ef77937b774416b847e75f09d9845b9',
            'deviceClass.0' => '7b9ecfca1da5475993f26d9c9cf772ca',
            'deviceType.0' => '1944b14cad3b44e6bc2940da56ba5d79',
            'ipAddr.0' => 'c2760bc29eea47b2b20e36a857819b3b',
            'macAddr.0' => 'e763b00f85c7418ea4532b0634ad05b3',
            'machineName.0' => '53f599c907804aefb6c1d6c27157976d',
            'serialNumber.0' => '29d34edc176b4b2888a9b9c03bd7d867',
            'systeme.uptime[sysUpTime]' => 'd8c30c53c0b44b5d8a4345fe7f3398c0',
            'systemVersion.0' => '234f1e2ec9f94e24aa8997870cf42b5a',
        ];
        foreach ($originalItems as $key => $uuid) {
            $this->assertSame($uuid, $this->item($key)['uuid']);
        }
        $this->assertSame('0.01', $this->item('systeme.uptime[sysUpTime]')['preprocessing'][0]['parameters'][0]);
    }

    public function test_all_uuids_keys_and_local_references_are_valid_and_unique(): void
    {
        $uuids = [];
        array_walk_recursive($this->export, function (mixed $value, string|int $key) use (&$uuids): void {
            $this->assertIsString($value, 'Zabbix export scalar '.$key.' must retain its string type.');
            if ($key === 'uuid') {
                $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $value);
                $uuids[] = $value;
            }
        });
        $this->assertSame($uuids, array_values(array_unique($uuids)));
        $items = $this->template['items'];
        $allKeys = array_column($items, 'key');
        $rootKeys = $allKeys;
        $valueMaps = array_column($this->template['valuemaps'], 'name');
        $graphs = $this->export['graphs'];
        foreach ($this->template['discovery_rules'] as $rule) {
            $allKeys[] = $rule['key'];
            $items = [...$items, ...$rule['item_prototypes']];
            $graphs = [...$graphs, ...($rule['graph_prototypes'] ?? [])];
            $allKeys = [...$allKeys, ...array_column($rule['item_prototypes'], 'key')];
            if (isset($rule['master_item'])) {
                $this->assertContains($rule['master_item']['key'], $rootKeys);
            }
        }
        $this->assertSame($allKeys, array_values(array_unique($allKeys)));
        foreach ($items as $item) {
            if (isset($item['master_item'])) {
                $this->assertContains($item['master_item']['key'], $rootKeys);
            }
            if (isset($item['valuemap'])) {
                $this->assertContains($item['valuemap']['name'], $valueMaps);
            }
            foreach ($item['preprocessing'] ?? [] as $step) {
                $this->assertNotSame('CUSTOM_VALUE', $step['error_handler'] ?? null, 'Missing SNMP data must not become a fabricated zero.');
            }
        }
        foreach ($graphs as $graph) {
            foreach ($graph['graph_items'] as $graphItem) {
                $this->assertSame($this->template['template'], $graphItem['item']['host']);
                $this->assertContains($graphItem['item']['key'], $allKeys);
            }
        }
        $this->assertNotContains('net.if.discovery', $allKeys);
        foreach (['cctv.ports.total', 'cctv.ports.up', 'cctv.ports.down'] as $legacyKey) {
            $this->assertNotContains($legacyKey, $allKeys);
        }
    }

    public function test_discovery_and_totals_do_not_require_aliases_or_optional_ifxtable(): void
    {
        $result = $this->snapshot([
            $this->interfaceRow('1', '6', '1'),
            $this->interfaceRow('2', '6', '2'),
            $this->interfaceRow('25', '117', '1'),
            $this->interfaceRow('100', '135', '1'),
            $this->interfaceRow('101', '24', '1'),
        ]);
        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame(['total' => 3, 'up' => 2, 'down' => 1, 'unknown' => 0], $result['snapshot']['ports']);
        $this->assertSame(['1', '2', '25'], array_column($result['snapshot']['interfaces'], 'index'));
        $this->assertStringNotContainsString('1.3.6.1.2.1.31', $this->item('dahua.switch.interfaces')['snmp_oid']);
    }

    public function test_missing_and_non_binary_statuses_are_unknown_not_down(): void
    {
        $rows = [];
        for ($status = 1; $status <= 7; $status++) {
            $rows[] = $this->interfaceRow((string) $status, '6', (string) $status);
        }
        $missingStatus = $this->interfaceRow('8', '6', '1');
        unset($missingStatus['ifOperStatus'], $missingStatus['ifAdminStatus']);
        $rows[] = $missingStatus;
        $rows[] = $this->interfaceRow('9', '6', 'invalid');
        $result = $this->snapshot($rows);
        $this->assertTrue($result['ok']);
        $this->assertSame(['total' => 9, 'up' => 1, 'down' => 1, 'unknown' => 7], $result['snapshot']['ports']);
        $this->assertSame(0, $result['snapshot']['interfaces'][7]['operStatus']);
        $this->assertSame(0, $result['snapshot']['interfaces'][7]['adminStatus']);
    }

    public function test_empty_malformed_or_incomplete_identity_tables_do_not_report_zero_ports(): void
    {
        $missingType = $this->interfaceRow('1', '6', '1');
        unset($missingType['ifType']);
        $missingDescription = $this->interfaceRow('1', '6', '1');
        unset($missingDescription['ifDescr']);
        $inputs = [
            '[]', '{}', 'invalid JSON', '[null]',
            json_encode([$missingType], JSON_THROW_ON_ERROR),
            json_encode([$missingDescription], JSON_THROW_ON_ERROR),
            json_encode([$this->interfaceRow('1', '24', '1')], JSON_THROW_ON_ERROR),
            json_encode([$this->interfaceRow('1', '6', '1'), $this->interfaceRow('1', '6', '2')], JSON_THROW_ON_ERROR),
            json_encode([$this->interfaceRow('1.2', '6', '1')], JSON_THROW_ON_ERROR),
        ];
        foreach ($inputs as $input) {
            $result = $this->runSnapshotScript($input);
            $this->assertFalse($result['ok'], $input);
            $this->assertArrayNotHasKey('snapshot', $result);
        }
    }

    public function test_interface_names_are_preserved_without_changing_index_based_identity(): void
    {
        $row = $this->interfaceRow('42', '6', '1');
        $row['ifDescr'] = 'Port "Gate In" / PoE';
        $result = $this->snapshot([$row]);
        $this->assertTrue($result['ok']);
        $this->assertSame($row['ifDescr'], $result['snapshot']['interfaces'][0]['description']);
        $this->assertSame('42', $result['snapshot']['interfaces'][0]['index']);
    }

    public function test_counter64_traffic_and_standard_poe_indexes_are_used_without_invented_port_wattage(): void
    {
        $interfaceRule = $this->template['discovery_rules'][0];
        $prototypes = array_column($interfaceRule['item_prototypes'], null, 'key');
        foreach (['in' => '6', 'out' => '10'] as $direction => $oidSuffix) {
            $item = $prototypes['dahua.switch.port.'.$direction.'[{#IFINDEX}]'];
            $this->assertSame('1.3.6.1.2.1.31.1.1.1.'.$oidSuffix.'.{#IFINDEX}', $item['snmp_oid']);
            $this->assertSame('CHANGE_PER_SECOND', $item['preprocessing'][0]['type']);
            $this->assertSame(['8'], $item['preprocessing'][1]['parameters']);
        }
        $poe = $this->template['discovery_rules'][1];
        foreach ($poe['item_prototypes'] as $item) {
            $this->assertStringEndsWith('.{#SNMPINDEX}', $item['snmp_oid']);
            $this->assertStringNotContainsString('{#IFINDEX}', $item['key']);
            $this->assertNotSame('W', $item['units'] ?? null);
        }
        $maps = array_column($this->template['valuemaps'], 'mappings', 'name');
        $statuses = array_column($maps['Dahua switch PoE detection status'], 'newvalue', 'value');
        $this->assertSame('Delivering power', $statuses['3']);
        $macros = array_column($this->template['macros'], 'value', 'macro');
        $this->assertSame('0', $macros['{$DAHUA.PORT.ALERT}']);
    }

    /** @return array<string, mixed> */
    private function item(string $key): array
    {
        foreach ($this->template['items'] as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }
        $this->fail('Missing template item '.$key);
    }

    /** @return array<string, string> */
    private function interfaceRow(string $index, string $type, string $status): array
    {
        return ['{#SNMPINDEX}' => $index, 'ifType' => $type, 'ifDescr' => 'Port '.$index,
            'ifAdminStatus' => '1', 'ifOperStatus' => $status];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function snapshot(array $rows): array
    {
        return $this->runSnapshotScript(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function runSnapshotScript(string $input): array
    {
        $script = $this->item('dahua.switch.interfaces')['preprocessing'][1]['parameters'][0];
        $process = new Process(['node', '-e', <<<'JS'
            const fs = require('node:fs');
            const payload = JSON.parse(fs.readFileSync(0, 'utf8'));
            try {
                const result = new Function('value', payload.script)(payload.value);
                process.stdout.write(JSON.stringify({ok: true, snapshot: JSON.parse(result)}));
            } catch (error) {
                process.stdout.write(JSON.stringify({ok: false, error: String(error)}));
            }
            JS]);
        $process->setInput(json_encode(['script' => $script, 'value' => $input], JSON_THROW_ON_ERROR));
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
