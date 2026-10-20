<script>
    import Icon from '../../MonitoringIcon.svelte';
    let {
        warehouses,
        defaultWarehouse = $bindable(),
        temperatureThreshold = $bindable(35),
        savePreferences,
    } = $props();
</script>

<section class="panel settings-panel">
    <div class="panel-heading">
        <h2><Icon name="settings" />Monitoring preferences</h2>
        <span class="badge neutral">This browser</span>
    </div>
    <form onsubmit={savePreferences}>
        <label
            >Default warehouse<span>Open this warehouse the next time you visit.</span><select
                bind:value={defaultWarehouse}
                >{#each warehouses as item}<option value={item.id}>{item.name}</option
                    >{/each}</select
            ></label
        ><label
            >Temperature warning threshold<span
                >Dashboard temperature indicators turn orange at this value.</span
            >
            <div class="threshold-input">
                <input
                    type="number"
                    min="25"
                    max="60"
                    required
                    bind:value={temperatureThreshold}
                /><span>°C</span>
            </div></label
        >
        <div class="settings-notice">
            <Icon name="shield" size={20} />
            <div>
                <strong>Demo workspace</strong>
                <p>
                    These preferences are stored in your browser. Device connections, credentials
                    and real monitoring are not configured yet.
                </p>
            </div>
        </div>
        <button class="primary-button" type="submit"
            ><Icon name="check" size={17} />Save preferences</button
        >
    </form>
</section>
