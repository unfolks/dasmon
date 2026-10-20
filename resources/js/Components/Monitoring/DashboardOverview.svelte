<script>
    import Icon from '../../MonitoringIcon.svelte';
    import DeviceSummaryCards from './DeviceSummaryCards.svelte';
    import InfrastructureHealth from './InfrastructureHealth.svelte';
    import RecentAlerts from './RecentAlerts.svelte';
    import NetworkLatencyPanel from './NetworkLatencyPanel.svelte';

    let {
        inventory,
        alerts,
        warehouseIndex,
        temperatureThreshold,
        chartRange = $bindable('1'),
        navigate,
    } = $props();
    let offlineCameraCount = $derived(
        inventory.cctv.filter((device) => device.status === 'Offline').length,
    );
    let unknownCameraCount = $derived(
        inventory.cctv.filter((device) => device.status === 'Unknown').length,
    );
</script>

<DeviceSummaryCards {inventory} {navigate} />
<InfrastructureHealth {inventory} {temperatureThreshold} {navigate} />
<section class="dashboard-bottom">
    <RecentAlerts {alerts} {offlineCameraCount} {unknownCameraCount} cameraCount={inventory.cctv.length} {navigate} />
    <NetworkLatencyPanel {warehouseIndex} bind:chartRange />
</section>
<div class="overview-note">
    <Icon name="shield" size={15} />
    <span>One workspace. Every device. A clearer view of your infrastructure.</span>
</div>
