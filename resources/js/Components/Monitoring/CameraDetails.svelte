<script>
    import DeviceDetailsList from './DeviceDetailsList.svelte';
    import Icon from '../../MonitoringIcon.svelte';
    import {
        formatMilliseconds,
        formatPercent,
        formatCheckedAt,
    } from '../../monitoring-formatters.js';
    let { selectedDevice } = $props();
</script>

<DeviceDetailsList
    entries={[
        ['Camera name', selectedDevice.name],
        ['Zabbix host ID', selectedDevice.id],
        [
            'Switch',
            (selectedDevice.switchGroups ?? [])
                .map((group) => group.name)
                .join(', ') || 'Unknown',
        ],
        ['Location', selectedDevice.location],
        ['IP / DNS', selectedDevice.ip],
        ['Monitoring', 'ICMP ping · Zabbix'],
        ['Status', selectedDevice.status],
        ['Latency', formatMilliseconds(selectedDevice.latencyMs)],
        ['Packet loss', formatPercent(selectedDevice.packetLossPercent)],
        ['Last checked', formatCheckedAt(selectedDevice.lastCheckedAt)],
    ]}
/>
<div class="detail-notice" class:danger={selectedDevice.status === 'Offline'}>
    <Icon
        name={selectedDevice.status === 'Offline' ? 'signalOff' : 'shield'}
        size={18}
    />
    <span>{selectedDevice.detail}</span>
</div>
<div class="detail-notice">
    <Icon name="globe" size={18} />
    <span
        >Online berarti kamera merespons ping. Offline berarti tidak merespons
        ping; daya kamera, video, dan recording belum dapat dipastikan melalui
        ICMP.</span
    >
</div>
