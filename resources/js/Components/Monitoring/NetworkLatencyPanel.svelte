<script>
    import Icon from '../../MonitoringIcon.svelte';
    import LatencyChart from '../../LatencyChart.svelte';
    let { chartRange = $bindable('1'), warehouseIndex } = $props();
</script>

<article class="panel latency-panel">
    <div class="panel-heading">
        <h2>
            <span class="section-icon blue-icon"><Icon name="chart" size={20} /></span>Network
            Latency
        </h2>
        <label class="compact-select"
            ><select aria-label="Network latency time range" bind:value={chartRange}
                ><option value="1">Last 1 Hour</option><option value="6">Last 6 Hours</option
                ><option value="24">Last 24 Hours</option></select
            ><Icon name="down" size={13} /></label
        >
    </div>
    <LatencyChart range={chartRange} {warehouseIndex} />
</article>
