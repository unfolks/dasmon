import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildNasTemperatures } from '../../resources/js/monitoring-nas.js';
import { formatBytes, formatPercent } from '../../resources/js/monitoring-formatters.js';
import { filterDevices, getDeviceSummary } from '../../resources/js/monitoring-filters.js';

test('NAS summary separates missing metrics from devices requiring attention without assuming ping', () => {
    const devices = [
        { id: '1', name: 'Nas', status: 'Online', isReachable: null },
        { id: '2', name: 'Nas-Office', status: 'Warning', isReachable: null },
        { id: '3', name: 'Nas-Offline', status: 'Offline', isReachable: false },
        { id: '4', name: 'Nas-Pending', status: 'Unknown', isReachable: null },
    ];
    for (const stat of getDeviceSummary(devices, 'nas', { icon: 'server' })) {
        assert.equal(stat.value, filterDevices(devices, '', stat.filter).length);
    }
    assert.equal(formatBytes(113997365510144), '103.68 TiB');
    assert.equal(formatPercent(68.46), '68.5%');
});

test('NAS temperature uses current site data and NAS threshold while empty and missing values remain unknown', () => {
    assert.equal(buildNasTemperatures([])[0].status, 'Unknown');
    assert.equal(buildNasTemperatures([])[0].temperature, null);
    const [normal, warning, unknown] = buildNasTemperatures([
        { id: '10705', name: 'Nas', ip: '192.168.20.2', temperature: 47 },
        { id: '10706', name: 'Nas-Office', temperature: 60 },
        { id: '10707', name: 'Nas-Pending', temperature: null },
    ]);
    assert.equal(normal.status, 'Online');
    assert.equal(normal.id, 'nas-temperature-10705');
    assert.equal(normal.temperature, 47);
    assert.equal(warning.status, 'Warning');
    assert.equal(unknown.status, 'Unknown');
    assert.equal(buildNasTemperatures([{ id: '2', temperature: 47 }], 45)[0].status, 'Warning');
});
