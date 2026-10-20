<script>
    import DeviceDetailsList from './DeviceDetailsList.svelte';
    import StatusBadge from './StatusBadge.svelte';
    import { formatPercent, formatBytes } from '../../monitoring-formatters.js';

    let { device } = $props();
    const checkedAt = (value) => (value ? new Date(value).toLocaleString('id-ID') : 'Unknown');
    const temperature = (value) => (value === null ? 'Unknown' : `${value}°C`);
</script>

<DeviceDetailsList
    entries={[
        ['Zabbix host ID', device.id],
        ['IP / DNS', device.ip],
        ['Model', device.model ?? 'Unknown'],
        ['DSM', device.version ?? 'Unknown'],
        ['SNMP', device.snmpStatus],
        [
            'Ping',
            device.isReachable === null
                ? 'Belum tersedia'
                : device.isReachable
                  ? 'Reachable'
                  : 'Unreachable',
        ],
        ['CPU', formatPercent(device.cpuPercent)],
        ['Suhu NAS', temperature(device.temperature)],
        ['Terakhir diperiksa', checkedAt(device.lastCheckedAt)],
        ['Backup', device.backupStatus],
    ]}
/>

{#if device.warnings.length}
    <div class="nas-warnings" role="status">
        <strong>Needs attention</strong>
        <ul>
            {#each device.warnings as warning}<li>{warning}</li>{/each}
        </ul>
    </div>
{/if}

<section class="nas-section" aria-label="NAS volumes">
    <h3>Storage volumes</h3>
    {#each device.volumes as volume (volume.index)}
        <article class="volume-metric">
            <div class="metric-heading">
                <strong>{volume.name}</strong><span>{formatPercent(volume.usedPercent)}</span>
            </div>
            <div class="progress-track">
                <span class="blue-bar" style:width={`${volume.usedPercent ?? 0}%`}></span>
            </div>
            <p>
                {formatBytes(volume.usedBytes)} terpakai / {formatBytes(volume.totalBytes)}
            </p>
            <p>{formatBytes(volume.freeBytes)} tersedia</p>
            <small>Checked: {checkedAt(volume.lastCheckedAt)}</small>
        </article>
    {:else}<p class="muted">Data volume belum tersedia.</p>{/each}
</section>

<section class="nas-section" aria-label="NAS system and RAID health">
    <h3>System & RAID</h3>
    {#each [...device.checks, ...device.raids] as check}
        <div class="health-row">
            <span>{check.name}</span><StatusBadge status={check.status} />
        </div>
    {/each}
</section>

<section class="nas-section" aria-label="NAS physical disks">
    <h3>Physical disks ({device.disks.length})</h3>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Disk</th><th>Status</th><th>Suhu</th><th>Bad sector</th></tr></thead>
            <tbody
                >{#each device.disks as disk (disk.index)}
                    <tr
                        ><td
                            >{disk.name}<small
                                >{disk.model ?? 'Unknown'} · {disk.type ?? 'Unknown'}</small
                            ></td
                        >
                        <td><StatusBadge status={disk.status} /></td><td
                            >{temperature(disk.temperature)}</td
                        ><td>{disk.badSectors ?? 'Unknown'}</td></tr
                    >
                {:else}<tr><td colspan="4">Data disk belum tersedia.</td></tr>{/each}</tbody
            >
        </table>
    </div>
</section>

<details class="nas-section">
    <summary>SMART ({device.smart.total} items)</summary>
    <p>
        {device.smart.ok} OK · {device.smart.warning} Warning · {device.smart.unknown} Unknown
    </p>
    <div class="table-scroll smart-scroll">
        <table aria-label="NAS SMART attributes">
            <thead><tr><th>Attribute / device</th><th>Status</th><th>Value</th></tr></thead>
            <tbody
                >{#each device.smart.entries as item (item.id)}
                    <tr
                        ><td>{item.name}<small>{checkedAt(item.lastCheckedAt)}</small></td><td
                            ><StatusBadge status={item.status} /></td
                        ><td>{item.value ?? 'Unknown'}</td></tr
                    >
                {:else}<tr><td colspan="3">Data SMART belum tersedia.</td></tr>{/each}</tbody
            >
        </table>
    </div>
</details>

<details class="nas-section">
    <summary>Physical memory</summary>
    <p>Total: {formatBytes(device.memory.totalBytes)}</p>
    <p>Free: {formatBytes(device.memory.freeBytes)}</p>
    <p>Cache: {formatBytes(device.memory.cachedBytes)}</p>
    <p>Buffer: {formatBytes(device.memory.bufferBytes)}</p>
    <small>Free adalah RAM fisik bebas; cache dan buffer ditampilkan terpisah.</small>
</details>

<div class="detail-notice">
    Status koneksi memakai SNMP; ping ditampilkan jika tersedia. Backup belum memiliki sumber data.
    Warning mengikuti kondisi perangkat dan batas dashboard.
</div>

<style>
    .nas-section {
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
    .volume-metric + .volume-metric {
        margin-top: 16px;
    }
    .metric-heading,
    .health-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        font-size: 12px;
    }
    .health-row + .health-row {
        margin-top: 10px;
    }
    .progress-track {
        margin: 8px 0;
    }
    p {
        font-size: 12px;
        margin: 8px 0;
    }
    small {
        color: #77839a;
        font-size: 10px;
    }
    .nas-warnings {
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
    .table-scroll {
        overflow: auto;
    }
    .smart-scroll {
        max-height: 320px;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11px;
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
        white-space: nowrap;
    }
</style>
