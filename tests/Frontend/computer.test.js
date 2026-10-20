import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    formatPercent,
    formatBytes,
    formatUptime,
    formatBitrate,
} from '../../resources/js/monitoring-formatters.js';
import { filterDevices, getDeviceSummary } from '../../resources/js/monitoring-filters.js';

test('computer metrics use binary storage, decimal traffic and preprocessed uptime', () => {
    assert.equal(formatBytes(23172087808), '21.58 GiB');
    assert.equal(formatBytes(255034900480), '237.52 GiB');
    assert.equal(formatBitrate(84360), '84.36 Kbps');
    assert.equal(formatBitrate(1000000000), '1.00 Gbps');
    assert.equal(formatUptime(100499), '1d 3h 54m 59s');
    assert.equal(formatUptime(0), '0h 0m 0s');
    assert.equal(formatPercent(67.66848798599084), '67.7%');
    for (const format of [formatBytes, formatBitrate, formatUptime, formatPercent]) {
        assert.equal(format(null), 'Unknown');
        assert.equal(format(undefined), 'Unknown');
        assert.equal(format(NaN), 'Unknown');
        assert.equal(format(0).includes('Unknown'), false);
    }
});

test('computer summary filters separate warning reachable computers from offline and unknown', () => {
    const devices = [
        { id: '1', name: 'PC-Server', status: 'Online', isReachable: true },
        { id: '2', name: 'PC-Office', status: 'Warning', isReachable: true },
        { id: '3', name: 'PC-Loading', status: 'Offline', isReachable: false },
        { id: '4', name: 'PC-CS', status: 'Unknown', isReachable: null },
    ];
    assert.equal(filterDevices(devices, 'server')[0].id, '1');
    assert.equal(filterDevices(devices, '', 'Reachable').length, 2);
    const summary = getDeviceSummary(devices, 'computers', { icon: 'computer' });
    for (const item of summary)
        assert.equal(item.value, filterDevices(devices, '', item.filter).length);
});
