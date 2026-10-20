import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    filterDevices,
    filterAlerts,
    getDeviceSummary,
} from '../../resources/js/monitoring-filters.js';
import { parseMonitoringPreferences } from '../../resources/js/monitoring-preferences.js';
import { createAlertReportCsv } from '../../resources/js/monitoring-reports.js';
import {
    warehouses as monitoringWarehouses,
    buildInventory,
    buildAlerts,
} from '../../resources/js/monitoring-data.js';
import {
    formatMilliseconds,
    formatCheckedAt,
} from '../../resources/js/monitoring-formatters.js';

const cameras = [
    {
        id: '10731',
        name: 'Camera Gate In',
        location: 'Gate In',
        ip: '192.168.15.9',
        status: 'Online',
    },
    {
        id: 'CCTV-02',
        location: 'Gate Out',
        ip: '192.168.1.102',
        status: 'Offline',
    },
    {
        id: 'CCTV-03',
        location: 'Office',
        ip: '192.168.1.103',
        status: 'Unknown',
    },
];
const warehouses = [{ id: 'krian' }, { id: 'sidoarjo' }];

test('Access Point search and summary include degraded reachable devices and unknown data', () => {
    const accessPoints = [
        { id: '1', name: 'Ruijie-Office', status: 'Online', isReachable: true },
        {
            id: '2',
            name: 'Ruijie-Loading',
            status: 'Warning',
            isReachable: true,
        },
        { id: '3', name: 'Ruijie-CS', status: 'Offline', isReachable: false },
        { id: '4', name: 'Ruijie-AR', status: 'Unknown', isReachable: null },
    ];
    assert.deepEqual(
        filterDevices(accessPoints, 'office').map((device) => device.id),
        ['1'],
    );
    assert.equal(filterDevices(accessPoints, '', 'Reachable').length, 2);
    assert.equal(filterDevices(accessPoints, '', 'Attention').length, 2);
    const summary = getDeviceSummary(accessPoints, 'access', { icon: 'wifi' });
    for (const stat of summary) {
        assert.equal(
            stat.value,
            filterDevices(accessPoints, '', stat.filter).length,
        );
    }
});

test('camera search combines case insensitive location or IP matches with status', () => {
    assert.deepEqual(
        filterDevices(cameras, 'GATE', 'Online').map((camera) => camera.id),
        ['10731'],
    );
    assert.deepEqual(
        filterDevices(cameras, '192.168.1.102', 'Offline').map(
            (camera) => camera.id,
        ),
        ['CCTV-02'],
    );
    assert.deepEqual(filterDevices(cameras, 'missing'), []);
});

test('camera summary and filters distinguish online offline and unknown without recording assumptions', () => {
    const summary = getDeviceSummary(cameras, 'cctv', { icon: 'camera' });
    assert.deepEqual(
        summary.map((stat) => [stat.label, stat.value]),
        [
            ['Total Camera', 3],
            ['Online', 1],
            ['Offline', 1],
            ['Unknown', 1],
        ],
    );
    for (const stat of summary) {
        assert.equal(
            stat.value,
            filterDevices(cameras, '', stat.filter).length,
        );
    }
    assert.deepEqual(
        filterDevices(cameras, '', 'Unknown').map((camera) => camera.id),
        ['CCTV-03'],
    );
    assert.equal(filterDevices(cameras, 'camera gate', 'Online').length, 1);
    assert.ok(
        getDeviceSummary([], 'cctv', { icon: 'camera' }).every(
            (stat) => stat.value === 0,
        ),
    );
});

test('switch selection scopes camera summaries and combines with status and search', () => {
    const groupA = { id: '28', name: 'Krian/CCTV 15.248' };
    const groupB = { id: '29', name: 'Krian/CCTV 10.238' };
    const devices = [
        { ...cameras[0], switchGroups: [groupA] },
        { ...cameras[1], switchGroups: [groupB] },
        { ...cameras[2], switchGroups: [groupA, groupB] },
    ];
    assert.equal(filterDevices(devices).length, 3);
    const switchA = filterDevices(devices, '', 'all', '28');
    assert.equal(switchA.length, 2);
    assert.equal(filterDevices(devices, '', 'all', '29').length, 2);
    assert.equal(filterDevices(devices, '', 'Offline', '28').length, 0);
    assert.equal(filterDevices(devices, 'gate out', 'Offline', '29').length, 1);
    assert.equal(filterDevices(devices, '15.248').length, 2);
    assert.equal(filterDevices(devices, '', 'all', 'missing').length, 0);
    for (const stat of getDeviceSummary(switchA, 'cctv', { icon: 'camera' })) {
        assert.equal(
            stat.value,
            filterDevices(devices, '', stat.filter, '28').length,
        );
    }
    assert.equal(filterDevices(cameras, '', 'all', '28').length, 0);
});

test('warehouse demos never create cameras or CCTV alerts while live data is unavailable', () => {
    for (const warehouse of monitoringWarehouses) {
        assert.deepEqual(buildInventory(warehouse).cctv, []);
        assert.equal(
            buildAlerts(warehouse).some((alert) => alert.section === 'cctv'),
            false,
        );
    }
});

test('camera metrics preserve zero and submillisecond latency and show missing values as unknown', () => {
    assert.equal(formatMilliseconds(0.246), '0.246 ms');
    assert.equal(formatMilliseconds(0), '0 ms');
    for (const value of [undefined, null, NaN, Infinity]) {
        assert.equal(formatMilliseconds(value), 'Unknown');
    }
    for (const value of [undefined, null, '', 'invalid']) {
        assert.equal(formatCheckedAt(value), 'Unknown');
    }
});

test('alert severity and search are applied together for the report export', () => {
    const alerts = [
        {
            device: 'CCTV-02',
            category: 'CCTV',
            message: 'Camera offline',
            status: 'Critical',
        },
        {
            device: 'AP-07',
            category: 'Access Point',
            message: 'High latency',
            status: 'Warning',
        },
        {
            device: 'CCTV-03',
            category: 'CCTV',
            message: 'Recording stopped',
            status: 'Warning',
        },
    ];
    assert.deepEqual(filterAlerts(alerts, 'cctv', 'Warning'), [alerts[2]]);
    assert.deepEqual(filterAlerts(alerts, 'latency', 'Critical'), []);
});

test('CSV preserves commas, quotes and line breaks in alert messages', () => {
    const csv = createAlertReportCsv(
        [
            {
                time: '14:21',
                device: 'CCTV-02',
                category: 'CCTV',
                message: 'Camera "Gate, Out"\noffline',
                status: 'Critical',
            },
        ],
        'Warehouse, North',
    );

    assert.equal(
        csv,
        '\uFEFF"Time","Device","Category","Message","Status","Warehouse"\r\n"14:21","CCTV-02","CCTV","Camera ""Gate, Out""\noffline","Critical","Warehouse, North"',
    );
    assert.equal(
        createAlertReportCsv([], 'Krian'),
        '\uFEFF"Time","Device","Category","Message","Status","Warehouse"',
    );
});

test('corrupt or obsolete browser preferences fall back to usable defaults', () => {
    const defaults = { defaultWarehouse: 'krian', temperatureThreshold: 35 };
    assert.deepEqual(
        parseMonitoringPreferences('{broken', warehouses),
        defaults,
    );
    assert.deepEqual(parseMonitoringPreferences('null', warehouses), defaults);
    assert.deepEqual(
        parseMonitoringPreferences(
            '{"defaultWarehouse":"deleted","temperatureThreshold":40}',
            warehouses,
        ),
        defaults,
    );
});

test('browser preferences retain a valid warehouse but reject invalid temperature thresholds', () => {
    assert.deepEqual(
        parseMonitoringPreferences(
            '{"defaultWarehouse":"sidoarjo","temperatureThreshold":40}',
            warehouses,
        ),
        { defaultWarehouse: 'sidoarjo', temperatureThreshold: 40 },
    );
    for (const threshold of [24, 61, '40', null]) {
        const preferences = JSON.stringify({
            defaultWarehouse: 'sidoarjo',
            temperatureThreshold: threshold,
        });
        assert.deepEqual(parseMonitoringPreferences(preferences, warehouses), {
            defaultWarehouse: 'sidoarjo',
            temperatureThreshold: 35,
        });
    }
});
