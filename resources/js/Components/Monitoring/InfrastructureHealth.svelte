<script>
    import Icon from '../../MonitoringIcon.svelte';
    import StatusBadge from './StatusBadge.svelte';
    import { formatPercent } from '../../monitoring-formatters.js';
    let { inventory, temperatureThreshold, navigate } = $props();
    let nas = $derived(inventory.nas[0]);
    let ups = $derived(inventory.ups[0]);
</script>

<section class="infrastructure-grid" aria-label="Infrastructure health">
    <button class="panel infrastructure-card nas-card" onclick={() => navigate('nas', nas?.id)}
        ><span class="metric-icon"><Icon name="server" size={29} /></span>
        <div class="infrastructure-content">
            <h2>
                NAS{#if nas}<small class="nas-name"
                        >{nas.name}{inventory.nas.length > 1
                            ? ` · ${inventory.nas.length} devices`
                            : ''}</small
                    >{/if}
            </h2>
            <div class="online-label">
                <span
                    class="status-dot"
                    class:green={nas?.status === 'Online'}
                    class:red={nas?.status === 'Offline'}
                    class:orange={nas?.status === 'Warning'}
                ></span>{nas?.status ?? 'Unknown'}
            </div>
            <div class="nas-metrics">
                <div class="storage-metric">
                    <span>Storage Usage <b>{formatPercent(nas?.storageUsage)}</b></span>
                    <div class="progress-track">
                        <span class="blue-bar" style:width={`${nas?.storageUsage ?? 0}%`}></span>
                    </div>
                </div>
                <div>
                    <span>Health</span><StatusBadge status={nas?.health ?? 'Unknown'} />
                </div>
                <div>
                    <span>Backup</span><StatusBadge
                        status={nas?.backupStatus ?? 'Belum tersedia'}
                    />
                </div>
            </div>
        </div></button
    >
    <button class="panel infrastructure-card" onclick={() => navigate('temperature')}
        ><span class="metric-icon"><Icon name="temperature" size={29} /></span>
        <div class="infrastructure-content">
            <h2>Temperature</h2>
            <div class="temperature-metrics">
                {#each inventory.temperature as item}<div>
                        <span>{item.location}</span><strong
                            ><i
                                class="status-dot"
                                class:orange={item.temperature !== null &&
                                    item.temperature >=
                                        (item.warningThreshold ?? temperatureThreshold)}
                                class:green={item.temperature !== null &&
                                    item.temperature <
                                        (item.warningThreshold ?? temperatureThreshold)}
                            ></i>{item.temperature === null
                                ? 'Unknown'
                                : `${item.temperature}°C`}</strong
                        >
                    </div>{/each}
            </div>
        </div></button
    >
    <button class="panel infrastructure-card" onclick={() => navigate('ups', ups.id)}
        ><span class="metric-icon"><Icon name="battery" size={29} /></span>
        <div class="infrastructure-content">
            <div class="metric-heading">
                <h2>UPS</h2>
                <Icon name="chevron" size={17} />
            </div>
            <div class="online-label">
                <span
                    class="status-dot"
                    class:green={ups.status === 'Online'}
                    class:red={ups.status === 'Offline'}
                ></span>{ups.status}
            </div>
            <div class="ups-metrics">
                <div>
                    <span>Load</span><strong>{ups.load}%</strong>
                    <div class="progress-track">
                        <span class="blue-bar" style:width={`${ups.load}%`}></span>
                    </div>
                </div>
                <div>
                    <span>Battery</span><strong>{ups.battery}%</strong>
                    <div class="progress-track">
                        <span class="green-bar" style:width={`${ups.battery}%`}></span>
                    </div>
                </div>
                <div>
                    <span>Runtime</span><strong class="runtime-value">{ups.runtime} min</strong>
                </div>
            </div>
        </div></button
    >
</section>

<style>
    .nas-name {
        font-size: 10px;
        font-weight: 400;
        color: #77839a;
        margin-left: 8px;
    }
</style>
