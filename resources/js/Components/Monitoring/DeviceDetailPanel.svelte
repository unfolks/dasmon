<script>
    import StatusBadge from './StatusBadge.svelte';
    import DeviceDetailsList from './DeviceDetailsList.svelte';
    import Icon from '../../MonitoringIcon.svelte';
    import CameraDetails from './CameraDetails.svelte';
    import ComputerDetails from './ComputerDetails.svelte';
    import NasDetails from './NasDetails.svelte';
    let { selectedDevice, currentNavigation, activeSection, alerts, closeDetails } = $props();
</script>

<aside
    class="panel device-detail"
    aria-label={`${selectedDevice.name ?? selectedDevice.id} details`}
>
    <div class="detail-heading">
        <span class="metric-icon"><Icon name={currentNavigation.icon} size={24} /></span>
        <div>
            <h2>
                {selectedDevice.name ?? selectedDevice.id}<StatusBadge
                    status={selectedDevice.status}
                    dot
                />
            </h2>
            <p>{selectedDevice.location}</p>
        </div>
        <button
            class="icon-button close-detail"
            aria-label="Close device details"
            onclick={closeDetails}><Icon name="close" size={17} /></button
        >
    </div>
    {#if activeSection === 'cctv'}
        <CameraDetails {selectedDevice} />
    {:else if activeSection === 'computers'}
        <ComputerDetails device={selectedDevice} />
    {:else if activeSection === 'nas'}
        <NasDetails device={selectedDevice} />
    {:else if activeSection === 'access'}
        <DeviceDetailsList
            entries={[
                ['Zabbix host ID', selectedDevice.id],
                ['IP / DNS', selectedDevice.ip],
                ['Health', selectedDevice.health],
                [
                    'Latency',
                    selectedDevice.latencyMs === null
                        ? 'Unknown'
                        : `${selectedDevice.latencyMs} ms`,
                ],
                [
                    'Packet loss',
                    selectedDevice.packetLossPercent === null
                        ? 'Unknown'
                        : `${selectedDevice.packetLossPercent}%`,
                ],
                [
                    'Last checked',
                    selectedDevice.lastCheckedAt
                        ? new Date(selectedDevice.lastCheckedAt).toLocaleString('id-ID')
                        : 'Unknown',
                ],
            ]}
        />
        <div class="detail-notice">
            <Icon name="shield" size={18} /><span
                >ICMP dari Zabbix. Reachability menunjukkan respons ping, bukan jumlah klien Wi-Fi
                atau throughput.</span
            >
        </div>
    {:else}
        <DeviceDetailsList
            entries={[
                ['Device', selectedDevice.id],
                ['Location', selectedDevice.location],
                ['IP address', selectedDevice.ip],
                ['Status', selectedDevice.status],
                ['Health', selectedDevice.detail],
            ]}
        />
        <div class="detail-notice">
            <Icon name="shield" size={18} /><span
                >Demo inventory. Connect a monitoring source to show real device data.</span
            >
        </div>
    {/if}
</aside>
