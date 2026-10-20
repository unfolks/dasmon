<script>
    import Icon from '../../MonitoringIcon.svelte';
    let {
        activeSection,
        currentNavigation,
        warehouses,
        warehouseId = $bindable(),
        mobileMenuOpen = $bindable(false),
        updatedAt,
        navigate,
        changeWarehouse,
        refreshPreview,
    } = $props();
</script>

<header class="topbar">
    <div class="heading-group">
        <button
            class="mobile-menu icon-button"
            aria-label="Open navigation"
            onclick={() => (mobileMenuOpen = true)}><Icon name="menu" /></button
        >
        <div>
            {#if activeSection === 'dashboard'}
                <div class="eyebrow">WORKSPACE OVERVIEW</div>
                <h1>IT Hardware Monitoring</h1>
                <p>
                    Real-time monitoring for CCTV, Network, Computers, Access Point, NAS,
                    Temperature and UPS
                </p>
            {:else}
                <div class="breadcrumb">
                    <button onclick={() => navigate('dashboard')}>IT Hardware Monitoring</button
                    ><Icon name="chevron" size={13} /><span>{currentNavigation.label}</span>
                </div>
                <div class="page-heading">
                    <span class="heading-icon"
                        ><Icon name={currentNavigation.icon} size={25} /></span
                    >
                    <div>
                        <h1>
                            {['reports', 'settings'].includes(activeSection)
                                ? currentNavigation.label
                                : activeSection === 'cctv'
                                  ? 'CCTV Monitoring'
                                  : `${currentNavigation.label} Monitoring`}
                        </h1>
                        <p>
                            {activeSection === 'cctv'
                                ? 'Monitor reachability of each CCTV camera using ICMP ping'
                                : activeSection === 'reports'
                                  ? 'Review hardware alerts and export monitoring reports'
                                  : activeSection === 'settings'
                                    ? 'Customize your local monitoring preferences'
                                    : `Monitor status and health of ${currentNavigation.label.toLowerCase()} devices`}
                        </p>
                    </div>
                </div>
            {/if}
        </div>
    </div>
    <div class="topbar-actions">
        <div class="last-update">
            <span class="status-dot green"></span>
            <div>
                <span>Last manual refresh</span><strong>{updatedAt}</strong>
            </div>
            <button
                class="refresh-button"
                aria-label="Refresh monitoring data"
                title="Refresh CCTV, Access Point, Computers and NAS"
                onclick={refreshPreview}><Icon name="refresh" size={15} /></button
            >
        </div>
        <label class="warehouse-selector"
            ><Icon name="pin" size={18} /><select
                aria-label="Warehouse"
                bind:value={warehouseId}
                onchange={changeWarehouse}
                >{#each warehouses as item}<option value={item.id}>{item.name}</option
                    >{/each}</select
            ><Icon name="down" size={15} /></label
        >
    </div>
</header>
