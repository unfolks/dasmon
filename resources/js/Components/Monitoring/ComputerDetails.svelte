<script>
    import DeviceDetailsList from './DeviceDetailsList.svelte';
    import StatusBadge from './StatusBadge.svelte';
    import {
        formatPercent,
        formatBytes,
        formatUptime,
        formatBitrate,
    } from '../../monitoring-formatters.js';

    let { device } = $props();
    const checkedAt = (value) => (value ? new Date(value).toLocaleString('id-ID') : 'Unknown');
</script>

<DeviceDetailsList
    entries={[
        ['Zabbix host ID', device.id],
        ['System name', device.systemName || 'Unknown'],
        ['IP / DNS', device.ip],
        [
            'Ping',
            device.pingReachable === true
                ? 'Reachable'
                : device.pingReachable === false
                  ? 'Unreachable'
                  : 'Unknown',
        ],
        ['Monitoring', device.monitoringType],
        ['Connection', device.monitoringStatus],
        ['CPU', formatPercent(device.cpuPercent)],
        ['CPU checked', checkedAt(device.cpuLastCheckedAt)],
        ['Latency', device.latencyMs === null ? 'Unknown' : `${device.latencyMs} ms`],
        ['Packet loss', formatPercent(device.packetLossPercent)],
        ['System uptime', formatUptime(device.uptimeSeconds)],
        ['Network uptime', formatUptime(device.networkUptimeSeconds)],
        ['Monitoring checked', checkedAt(device.lastCheckedAt)],
        ['Ping checked', checkedAt(device.pingLastCheckedAt)],
    ]}
/>

{#if device.warnings.length}
    <div class="computer-warnings" role="status">
        <strong>Needs attention</strong>
        <ul>
            {#each device.warnings as warning}<li>{warning}</li>{/each}
        </ul>
    </div>
{/if}

{#each [{ label: 'Memory', entries: device.memory }, { label: 'Disks', entries: device.disks }] as section}
    <section class="computer-section" aria-label={section.label}>
        <h3>{section.label}</h3>
        {#each section.entries as storage (storage.index)}
            <article class="storage-metric">
                <div class="storage-heading">
                    <strong>{storage.name}</strong><span>{formatPercent(storage.usedPercent)}</span>
                </div>
                <div class="progress-track">
                    <span class="blue-bar" style:width={`${storage.usedPercent ?? 0}%`}></span>
                </div>
                <p>{formatBytes(storage.usedBytes)} / {formatBytes(storage.totalBytes)}</p>
                <small>Checked: {checkedAt(storage.lastCheckedAt)}</small>
            </article>
        {:else}<p class="muted">Data belum tersedia.</p>{/each}
    </section>
{/each}

<details class="computer-section">
    <summary>Network interfaces ({device.interfaces.length})</summary>
    <p class="network-note">
        Traffic ditampilkan per interface. Interface virtual dapat mencatat traffic yang sama.
    </p>
    <div class="interface-scroll">
        <table aria-label="Computer network interfaces">
            <thead
                ><tr><th>Interface</th><th>Status</th><th>In</th><th>Out</th><th>Link speed</th></tr
                ></thead
            >
            <tbody
                >{#each device.interfaces as network (network.index)}
                    <tr
                        ><td>{network.name}<small>{network.source} · {network.index}</small></td><td
                            ><StatusBadge status={network.status} /></td
                        ><td>{formatBitrate(network.inBps)}</td><td
                            >{formatBitrate(network.outBps)}</td
                        ><td>{formatBitrate(network.speedBps)}</td></tr
                    >
                {:else}<tr><td colspan="5">Data belum tersedia.</td></tr>{/each}</tbody
            >
        </table>
    </div>
</details>
<details class="computer-section">
    <summary>System description</summary>
    <p class="system-description">{device.systemDescription || 'Data belum tersedia.'}</p>
</details>
<div class="detail-notice">
    Status Unknown berarti metrik belum lengkap, tidak didukung, atau kedaluwarsa. Warning mengikuti
    batas dashboard, bukan daftar problem Zabbix.
</div>

<style>
    .computer-section {
        padding: 16px 18px;
        border-top: 1px solid #eef0f5;
    }
    h3,
    summary {
        font-size: 13px;
        font-weight: 650;
        margin: 0 0 12px;
    }
    summary {
        cursor: pointer;
        margin-bottom: 0;
    }
    .storage-metric + .storage-metric {
        margin-top: 16px;
    }
    .storage-heading {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
    }
    .progress-track {
        margin: 8px 0;
    }
    p {
        font-size: 12px;
        margin: 6px 0;
    }
    small {
        color: #77839a;
        font-size: 10px;
    }
    .computer-warnings {
        margin: 0 18px 16px;
        padding: 12px;
        background: #fff6e9;
        border-radius: 8px;
        color: #875713;
        font-size: 12px;
    }
    ul {
        margin: 8px 0 0;
        padding-left: 18px;
    }
    .network-note {
        color: #77839a;
        margin: 12px 0;
    }
    .interface-scroll {
        overflow: auto;
        max-height: 320px;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11px;
        white-space: nowrap;
    }
    th,
    td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #eef0f5;
    }
    td small {
        display: block;
        margin-top: 4px;
    }
    .system-description {
        overflow-wrap: anywhere;
        margin-top: 12px;
    }
</style>
